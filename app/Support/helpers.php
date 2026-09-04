<?php

declare(strict_types=1);

use Rentivo\Components\View;
use Rentivo\Security\Csrf;
use Rentivo\Support\Config;
use Rentivo\Support\Currency;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Str;

/**
 * Global view helpers. These are intentionally the only globals in the
 * project: they keep templates readable without letting templates reach into
 * services or the database.
 */

if (!function_exists('e')) {
    /** Centralized HTML escaping used by every template. */
    function e(mixed $value): string
    {
        return Str::escape($value);
    }
}

if (!function_exists('component')) {
    /**
     * Renders a reusable view component.
     *
     * @param array<string,mixed> $props
     */
    function component(string $name, array $props = []): string
    {
        return View::instance()->component($name, $props);
    }
}

if (!function_exists('render_component')) {
    /**
     * Echoes a component. Used where the return value is not needed.
     *
     * @param array<string,mixed> $props
     */
    function render_component(string $name, array $props = []): void
    {
        echo View::instance()->component($name, $props);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('money')) {
    /** Formats fils as "BHD 25.500". */
    function money(int $fils): string
    {
        return Currency::format($fils);
    }
}

if (!function_exists('money_amount')) {
    /** Formats fils as "25.500" without the currency code. */
    function money_amount(int $fils): string
    {
        return Currency::amount($fils);
    }
}

if (!function_exists('datetime_display')) {
    function datetime_display(?string $dbValue, string $format = 'd M Y, H:i'): string
    {
        return DateTimeHelper::display($dbValue, $format);
    }
}

if (!function_exists('date_display')) {
    function date_display(?string $dbValue): string
    {
        return DateTimeHelper::displayDate($dbValue);
    }
}

if (!function_exists('relative_time')) {
    function relative_time(?string $dbValue): string
    {
        return DateTimeHelper::relative($dbValue);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('asset')) {
    /** Cache-busted URL for a file under public/. */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = View::instance()->sharedValue('publicPath', '') . $path;

        $version = is_string($file) && is_file($file) ? (string) filemtime($file) : null;

        return $path . ($version === null ? '' : '?v=' . $version);
    }
}

if (!function_exists('url')) {
    /** Absolute URL for an application path. */
    function url(string $path = '/'): string
    {
        return rtrim((string) Config::get('url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('query_string')) {
    /**
     * Builds a query string from the current filters plus overrides, dropping
     * empty values so shared URLs stay clean.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $overrides
     */
    function query_string(array $base, array $overrides = []): string
    {
        $merged = array_merge($base, $overrides);

        $merged = array_filter(
            $merged,
            static fn ($value) => $value !== null && $value !== '' && $value !== []
        );

        return $merged === [] ? '' : '?' . http_build_query($merged);
    }
}

if (!function_exists('old')) {
    /**
     * Repopulates a form field after a validation failure.
     *
     * @param array<string,mixed> $old
     */
    function old(array $old, string $key, mixed $default = ''): mixed
    {
        return $old[$key] ?? $default;
    }
}

if (!function_exists('class_names')) {
    /**
     * Conditional class list builder: class_names(['btn' => true, 'btn--x' => $flag]).
     *
     * @param array<string,bool>|list<string> $classes
     */
    function class_names(array $classes): string
    {
        $result = [];

        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if ($value !== '' && $value !== null) {
                    $result[] = (string) $value;
                }
            } elseif ($value) {
                $result[] = $key;
            }
        }

        return implode(' ', $result);
    }
}

if (!function_exists('attributes')) {
    /**
     * Renders an escaped HTML attribute string. Boolean true renders the bare
     * attribute; null/false omit it entirely.
     *
     * @param array<string,mixed> $attributes
     */
    function attributes(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $parts[] = e($name);
                continue;
            }

            $parts[] = e($name) . '="' . e($value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}
