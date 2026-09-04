<?php

declare(strict_types=1);

namespace Rentivo\Support;

use Throwable;

/**
 * Minimal file logger. Technical detail always lands in storage/logs and
 * never in an HTTP response for production environments.
 */
final class Logger
{
    private static ?string $directory = null;

    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, "/\\");
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function exception(Throwable $e, array $context = []): void
    {
        self::write('ERROR', $e::class . ': ' . $e->getMessage(), $context + [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (self::$directory === null) {
            return;
        }

        if (!is_dir(self::$directory)) {
            @mkdir(self::$directory, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            gmdate('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents(
            self::$directory . '/app-' . gmdate('Y-m-d') . '.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
