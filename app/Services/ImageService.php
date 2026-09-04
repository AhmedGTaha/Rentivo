<?php

declare(strict_types=1);

namespace Rentivo\Services;

use GdImage;
use Rentivo\Support\Config;

/**
 * Validates and processes uploaded images.
 *
 * Validation is layered and every layer must pass:
 *   1. PHP reported a successful upload
 *   2. the file is within the configured size limit
 *   3. finfo reports an accepted image MIME type
 *   4. getimagesize() confirms real image dimensions
 *   5. GD can actually decode the bytes
 *
 * Output is re-encoded (preferring WebP) from the decoded pixel data, which
 * also strips any metadata or appended payload the source may have carried.
 */
final class ImageService
{
    private const ACCEPTED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private FileStorageService $storage)
    {
    }

    public function storageService(): FileStorageService
    {
        return $this->storage;
    }

    /**
     * Validates, resizes and stores an uploaded image.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param string $disk FileStorageService::DISK_*
     *
     * @return string Relative stored path.
     *
     * @throws UploadException
     */
    public function storeUploadedImage(
        array $file,
        string $disk,
        string $relativeDirectory,
        ?int $maxEdge = null
    ): string {
        $this->assertUploadOk($file);

        $maxBytes = (int) Config::get('uploads.max_image_bytes', 8 * 1024 * 1024);

        if ($file['size'] > $maxBytes) {
            throw new UploadException(
                'Image is too large. Maximum size is ' . $this->humanBytes($maxBytes) . '.'
            );
        }

        $mime = $this->detectMime($file['tmp_name']);

        if (!isset(self::ACCEPTED_MIME[$mime])) {
            throw new UploadException('Only JPEG, PNG and WebP images are accepted.');
        }

        $dimensions = @getimagesize($file['tmp_name']);

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new UploadException('That file could not be read as an image.');
        }

        $image = $this->decode($file['tmp_name'], $mime);

        if ($image === null) {
            throw new UploadException('That image could not be processed.');
        }

        $maxEdge ??= (int) Config::get('uploads.image_max_edge', 1800);
        $image = $this->resizeToMaxEdge($image, $maxEdge);

        [$contents, $extension] = $this->encode($image);
        imagedestroy($image);

        return $this->storage->putContents(
            $disk,
            $relativeDirectory,
            $this->storage->randomFilename($extension),
            $contents
        );
    }

    /**
     * Validates and stores a private customer document. PDFs are accepted in
     * addition to images and are stored byte-for-byte after validation.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{path:string,mime:string,size:int}
     *
     * @throws UploadException
     */
    public function storePrivateDocument(array $file, string $relativeDirectory): array
    {
        $this->assertUploadOk($file);

        $maxBytes = (int) Config::get('uploads.max_document_bytes', 10 * 1024 * 1024);

        if ($file['size'] > $maxBytes) {
            throw new UploadException(
                'Document is too large. Maximum size is ' . $this->humanBytes($maxBytes) . '.'
            );
        }

        $mime = $this->detectMime($file['tmp_name']);

        if ($mime === 'application/pdf') {
            $path = $this->storage->storeUploadedFile(
                FileStorageService::DISK_PRIVATE,
                $relativeDirectory,
                $file['tmp_name'],
                'pdf'
            );

            return ['path' => $path, 'mime' => 'application/pdf', 'size' => $file['size']];
        }

        if (!isset(self::ACCEPTED_MIME[$mime])) {
            throw new UploadException('Documents must be a JPEG, PNG, WebP or PDF file.');
        }

        // Re-encode images so nothing executable can hide inside the file.
        $path = $this->storeUploadedImage(
            $file,
            FileStorageService::DISK_PRIVATE,
            $relativeDirectory,
            2400
        );

        return [
            'path' => $path,
            'mime' => 'image/webp',
            'size' => $this->storage->size(FileStorageService::DISK_PRIVATE, $path),
        ];
    }

    /** @param array{error:int,tmp_name:string,size:int} $file */
    private function assertUploadOk(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum upload size.',
                UPLOAD_ERR_PARTIAL                        => 'The upload did not complete. Please try again.',
                UPLOAD_ERR_NO_FILE                        => 'No file was selected.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the file.',
                default                                   => 'The upload failed. Please try again.',
            });
        }

        if ($file['tmp_name'] === '' || !is_file($file['tmp_name'])) {
            throw new UploadException('The upload could not be read.');
        }
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function decode(string $path, string $mime): ?GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };

        return $image instanceof GdImage ? $image : null;
    }

    /** Downscales so the longest edge is at most $maxEdge. Never upscales. */
    private function resizeToMaxEdge(GdImage $image, int $maxEdge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxEdge) {
            return $image;
        }

        $scale = $maxEdge / $longest;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagedestroy($image);

        return $resized;
    }

    /**
     * Encodes to WebP when the build supports it, otherwise JPEG.
     *
     * @return array{0:string,1:string} [binary contents, extension]
     */
    private function encode(GdImage $image): array
    {
        $quality = (int) Config::get('uploads.webp_quality', 82);

        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($image, null, $quality);
            $contents = (string) ob_get_clean();

            if ($contents !== '') {
                return [$contents, 'webp'];
            }

            ob_start();
        }

        // Flatten alpha onto white before JPEG encoding.
        $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefilledrectangle($flattened, 0, 0, imagesx($image), imagesy($image), $white);
        imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        imagejpeg($flattened, null, max(60, $quality));
        imagedestroy($flattened);

        return [(string) ob_get_clean(), 'jpg'];
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
