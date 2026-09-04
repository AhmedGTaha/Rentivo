<?php

declare(strict_types=1);

namespace Rentivo\Support;

/**
 * Small string helpers used across views and services.
 */
final class Str
{
    /**
     * Centralized HTML escaping. Every untrusted value rendered by a view
     * passes through here (usually via the e() global helper).
     */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function limit(?string $value, int $length, string $suffix = '…'): string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $length)) . $suffix;
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }

    /** Converts a snake/dot key such as "bookings.manage_payment" to a label. */
    public static function humanize(string $value): string
    {
        return ucfirst(str_replace(['_', '.', '-'], [' ', ' ', ' '], $value));
    }

    public static function randomHex(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
