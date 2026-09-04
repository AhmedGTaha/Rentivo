<?php

declare(strict_types=1);

namespace Rentivo\Support;

use Dotenv\Dotenv;

/**
 * Thin wrapper around the loaded environment.
 *
 * The application never touches $_ENV/getenv() directly so that environment
 * access stays testable and consistently typed.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        if (is_file($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /** Used by tests to force a re-read after mutating the environment. */
    public static function reset(): void
    {
        self::$loaded = false;
    }
}
