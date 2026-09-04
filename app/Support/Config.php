<?php

declare(strict_types=1);

namespace Rentivo\Support;

use RuntimeException;

/**
 * Immutable configuration container with dot-notation lookup.
 */
final class Config
{
    private static ?self $instance = null;

    /** @param array<string,mixed> $items */
    public function __construct(private array $items)
    {
    }

    /** @param array<string,mixed> $items */
    public static function setInstance(array $items): self
    {
        return self::$instance = new self($items);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Configuration has not been loaded.');
        }

        return self::$instance;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::instance()->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function isProduction(): bool
    {
        return self::get('env') === 'production';
    }

    public static function isLocal(): bool
    {
        return in_array(self::get('env'), ['local', 'development', 'testing'], true);
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('debug', false);
    }
}
