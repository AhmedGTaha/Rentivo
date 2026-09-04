<?php

declare(strict_types=1);

namespace Rentivo\Support;

/**
 * URL slug generation with a pluggable uniqueness check so callers can scope
 * uniqueness however they need (globally for organizations, per-organization
 * for cars).
 */
final class Slug
{
    public static function make(string $value): string
    {
        $value = trim($value);

        if (function_exists('transliterator_transliterate')) {
            $transliterated = @transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if (is_string($transliterated)) {
                $value = $transliterated;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'item' : $value;
    }

    /**
     * @param callable(string):bool $exists Returns true when the slug is taken.
     */
    public static function unique(string $value, callable $exists, int $maxLength = 190): string
    {
        $base = substr(self::make($value), 0, $maxLength);
        $slug = $base;
        $suffix = 2;

        while ($exists($slug)) {
            $suffix_str = '-' . $suffix;
            $slug = substr($base, 0, $maxLength - strlen($suffix_str)) . $suffix_str;
            $suffix++;
        }

        return $slug;
    }
}
