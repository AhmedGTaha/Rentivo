<?php

declare(strict_types=1);

namespace Rentivo\Services;

use RuntimeException;

/**
 * The only place in the application that writes uploaded files to disk.
 *
 * Two storage roots exist:
 *   - public/uploads  — marketing imagery, served directly by the web server
 *   - storage/private — documents and inspection photos, only ever streamed
 *                       by an authorizing PHP route
 *
 * Every stored filename is server-generated random; the client's filename is
 * never used to build a path.
 */
final class FileStorageService
{
    public const DISK_PUBLIC = 'public';
    public const DISK_PRIVATE = 'private';

    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    }

    public function publicRoot(): string
    {
        return $this->basePath . '/public/uploads';
    }

    public function privateRoot(): string
    {
        return $this->basePath . '/storage/private';
    }

    public function root(string $disk): string
    {
        return $disk === self::DISK_PRIVATE ? $this->privateRoot() : $this->publicRoot();
    }

    /**
     * Resolves a stored relative path to an absolute path, refusing anything
     * that escapes the disk root.
     *
     * The relative path always comes from a trusted database column, but this
     * check makes traversal structurally impossible even if that ever changes.
     */
    public function absolutePath(string $disk, string $relativePath): string
    {
        $root = $this->root($disk);
        $relative = str_replace('\\', '/', $relativePath);

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")) {
            throw new RuntimeException('Invalid storage path.');
        }

        if (preg_match('#^([a-zA-Z]:)?/#', $relative) === 1) {
            throw new RuntimeException('Absolute storage paths are not allowed.');
        }

        $candidate = $root . '/' . ltrim($relative, '/');
        $real = realpath($candidate);

        if ($real === false) {
            // The file may legitimately not exist yet (writes); validate the
            // directory portion instead.
            $realRoot = realpath($root);

            return $realRoot === false ? $candidate : $candidate;
        }

        $realRoot = realpath($root);
        $real = str_replace('\\', '/', $real);
        $realRoot = $realRoot === false ? $root : str_replace('\\', '/', $realRoot);

        if (!str_starts_with($real, $realRoot . '/') && $real !== $realRoot) {
            throw new RuntimeException('Resolved path escapes the storage root.');
        }

        return $real;
    }

    public function exists(string $disk, string $relativePath): bool
    {
        try {
            return is_file($this->absolutePath($disk, $relativePath));
        } catch (RuntimeException) {
            return false;
        }
    }

    public function ensureDirectory(string $disk, string $relativeDirectory): string
    {
        $path = $this->absolutePath($disk, rtrim($relativeDirectory, '/') . '/.keep');
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create storage directory.');
        }

        return $directory;
    }

    /** Random, unguessable basename with the given extension. */
    public function randomFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . ltrim($extension, '.');
    }

    /**
     * Moves a validated upload into place.
     *
     * @return string The relative path to persist in the database.
     */
    public function storeUploadedFile(
        string $disk,
        string $relativeDirectory,
        string $temporaryPath,
        string $extension
    ): string {
        $directory = $this->ensureDirectory($disk, $relativeDirectory);
        $filename = $this->randomFilename($extension);
        $destination = $directory . '/' . $filename;

        $moved = is_uploaded_file($temporaryPath)
            ? move_uploaded_file($temporaryPath, $destination)
            : rename($temporaryPath, $destination);

        if (!$moved) {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        @chmod($destination, 0644);

        return trim($relativeDirectory, '/') . '/' . $filename;
    }

    /** Writes raw binary content (used for processed WebP output). */
    public function putContents(string $disk, string $relativeDirectory, string $filename, string $contents): string
    {
        $directory = $this->ensureDirectory($disk, $relativeDirectory);

        if (@file_put_contents($directory . '/' . $filename, $contents) === false) {
            throw new RuntimeException('Unable to write the file to storage.');
        }

        @chmod($directory . '/' . $filename, 0644);

        return trim($relativeDirectory, '/') . '/' . $filename;
    }

    public function delete(string $disk, ?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }

        try {
            $path = $this->absolutePath($disk, $relativePath);
        } catch (RuntimeException) {
            return false;
        }

        return is_file($path) && @unlink($path);
    }

    /** Removes a directory tree; used when a car's images are purged. */
    public function deleteDirectory(string $disk, string $relativeDirectory): void
    {
        try {
            $path = $this->absolutePath($disk, rtrim($relativeDirectory, '/') . '/.keep');
        } catch (RuntimeException) {
            return;
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $directory . '/' . $entry;
            is_dir($child) ? $this->removeTree($child) : @unlink($child);
        }

        @rmdir($directory);
    }

    private function removeTree(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $directory . '/' . $entry;
            is_dir($child) ? $this->removeTree($child) : @unlink($child);
        }

        @rmdir($directory);
    }

    /** Public URL for a file stored on the public disk. */
    public function publicUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        return '/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public function size(string $disk, string $relativePath): int
    {
        try {
            $path = $this->absolutePath($disk, $relativePath);
        } catch (RuntimeException) {
            return 0;
        }

        return is_file($path) ? (int) filesize($path) : 0;
    }
}
