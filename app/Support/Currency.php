<?php

declare(strict_types=1);

namespace Rentivo\Support;

use InvalidArgumentException;

/**
 * All money in Rentivo is stored and calculated as an integer number of fils.
 * 1 BHD = 1000 fils, so 25.500 BHD is persisted as 25500.
 *
 * Floating point never touches a stored monetary value; the only float usage
 * is parsing human input, which is immediately rounded to an integer.
 */
final class Currency
{
    public const CODE = 'BHD';
    public const FILS_PER_UNIT = 1000;
    public const DECIMALS = 3;

    /** Formats fils as "25.500" (no currency code). */
    public static function amount(int $fils): string
    {
        $negative = $fils < 0;
        $fils = abs($fils);

        $whole = intdiv($fils, self::FILS_PER_UNIT);
        $fraction = $fils % self::FILS_PER_UNIT;

        return ($negative ? '-' : '')
            . number_format($whole, 0, '.', ',')
            . '.'
            . str_pad((string) $fraction, self::DECIMALS, '0', STR_PAD_LEFT);
    }

    /** Formats fils as "BHD 25.500". */
    public static function format(int $fils): string
    {
        return self::CODE . ' ' . self::amount($fils);
    }

    /**
     * Parses user input such as "25.5", "25.500" or "25" into fils.
     *
     * @throws InvalidArgumentException when the value is not a valid amount.
     */
    public static function toFils(string $value): int
    {
        $value = trim(str_replace([',', ' ', "\u{00A0}"], '', $value));

        if ($value === '' || !preg_match('/^-?\d+(\.\d{1,3})?$/', $value)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, self::DECIMALS), self::DECIMALS, '0');

        $fils = ((int) $whole) * self::FILS_PER_UNIT + (int) $fraction;

        return $negative ? -$fils : $fils;
    }

    /** Safe parse used by form handling; returns null instead of throwing. */
    public static function tryToFils(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return self::toFils($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Value suitable for pre-filling a numeric input (e.g. "25.500"). */
    public static function toInput(int $fils): string
    {
        return number_format($fils / self::FILS_PER_UNIT, self::DECIMALS, '.', '');
    }
}
