<?php

declare(strict_types=1);

namespace Rentivo\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Timestamps are stored in UTC and displayed in the business timezone
 * (Asia/Bahrain by default). All conversion goes through this helper.
 */
final class DateTimeHelper
{
    public const DB_FORMAT = 'Y-m-d H:i:s';

    public static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    public static function business(): DateTimeZone
    {
        return new DateTimeZone((string) Config::get('timezone', 'Asia/Bahrain'));
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::utc());
    }

    /** Current time formatted for a DATETIME column. */
    public static function nowDb(): string
    {
        return self::now()->format(self::DB_FORMAT);
    }

    public static function toDb(DateTimeInterface $value): string
    {
        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(self::utc())
            ->format(self::DB_FORMAT);
    }

    /** Reads a UTC DATETIME value from the database. */
    public static function fromDb(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(self::DB_FORMAT, $value, self::utc());

        return $parsed === false ? null : $parsed;
    }

    /**
     * Parses a datetime-local form value ("2026-09-10T10:00") which the user
     * entered in business time, returning a UTC instant.
     */
    public static function fromInput(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = str_replace('T', ' ', trim($value));

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, self::business());
            if ($parsed !== false) {
                return $parsed->setTimezone(self::utc());
            }
        }

        return null;
    }

    /** Formats a UTC instant for a datetime-local input in business time. */
    public static function toInput(?DateTimeInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(self::business())
            ->format('Y-m-d\TH:i');
    }

    public static function display(?string $dbValue, string $format = 'd M Y, H:i'): string
    {
        $parsed = self::fromDb($dbValue);

        if ($parsed === null) {
            return '—';
        }

        return $parsed->setTimezone(self::business())->format($format);
    }

    public static function displayDate(?string $dbValue): string
    {
        return self::display($dbValue, 'd M Y');
    }

    /** Human relative time such as "3 hours ago"; falls back to a date. */
    public static function relative(?string $dbValue): string
    {
        $parsed = self::fromDb($dbValue);

        if ($parsed === null) {
            return '—';
        }

        $seconds = self::now()->getTimestamp() - $parsed->getTimestamp();

        if ($seconds < 60) {
            return 'just now';
        }
        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
        }
        if ($seconds < 86400) {
            $h = intdiv($seconds, 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        if ($seconds < 604800) {
            $d = intdiv($seconds, 86400);
            return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
        }

        return self::displayDate($dbValue);
    }

    /** Start/end of the business day containing $moment, expressed in UTC. */
    public static function businessDayBounds(?DateTimeImmutable $moment = null): array
    {
        $moment = ($moment ?? self::now())->setTimezone(self::business());

        $start = $moment->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        return [
            $start->setTimezone(self::utc())->format(self::DB_FORMAT),
            $end->setTimezone(self::utc())->format(self::DB_FORMAT),
        ];
    }
}
