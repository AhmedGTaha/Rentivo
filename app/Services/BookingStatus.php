<?php

declare(strict_types=1);

namespace Rentivo\Services;

/**
 * The booking state machine.
 *
 * Transition validation lives here and nowhere else; controllers and services
 * ask this class rather than re-implementing the rules.
 */
final class BookingStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const REJECTED = 'rejected';
    public const READY_FOR_PICKUP = 'ready_for_pickup';
    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no_show';

    /** Statuses that reserve the car for their window. */
    public const BLOCKING = [self::CONFIRMED, self::READY_FOR_PICKUP, self::ACTIVE];

    /** Statuses a customer is allowed to cancel from. */
    public const CUSTOMER_CANCELLABLE = [self::PENDING, self::CONFIRMED];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::PENDING          => [self::CONFIRMED, self::REJECTED, self::CANCELLED],
        self::CONFIRMED        => [self::READY_FOR_PICKUP, self::CANCELLED],
        self::READY_FOR_PICKUP => [self::ACTIVE, self::CANCELLED, self::NO_SHOW],
        self::ACTIVE           => [self::COMPLETED],
        self::COMPLETED        => [],
        self::REJECTED         => [],
        self::CANCELLED        => [],
        self::NO_SHOW          => [],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public static function exists(string $status): bool
    {
        return array_key_exists($status, self::TRANSITIONS);
    }

    /** @return list<string> Statuses reachable from $status. */
    public static function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedTransitions($from), true);
    }

    public static function isTerminal(string $status): bool
    {
        return self::exists($status) && self::allowedTransitions($status) === [];
    }

    public static function isBlocking(string $status): bool
    {
        return in_array($status, self::BLOCKING, true);
    }

    public static function customerMayCancel(string $status): bool
    {
        return in_array($status, self::CUSTOMER_CANCELLABLE, true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING          => 'Pending',
            self::CONFIRMED        => 'Confirmed',
            self::REJECTED         => 'Rejected',
            self::READY_FOR_PICKUP => 'Ready for pickup',
            self::ACTIVE           => 'Active',
            self::COMPLETED        => 'Completed',
            self::CANCELLED        => 'Cancelled',
            self::NO_SHOW          => 'No show',
            default                => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /** Badge tone used by the shared status badge component. */
    public static function tone(string $status): string
    {
        return match ($status) {
            self::PENDING                            => 'warning',
            self::CONFIRMED, self::READY_FOR_PICKUP  => 'info',
            self::ACTIVE                             => 'success',
            self::COMPLETED                          => 'neutral',
            self::REJECTED, self::CANCELLED, self::NO_SHOW => 'danger',
            default                                  => 'neutral',
        };
    }

    /** @return array<string,string> status => label, for filter selects. */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $status) {
            $options[$status] = self::label($status);
        }

        return $options;
    }

    // -----------------------------------------------------------------
    // Payment status
    // -----------------------------------------------------------------

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';

    /** @return list<string> */
    public static function paymentStatuses(): array
    {
        return [self::PAYMENT_UNPAID, self::PAYMENT_PAID, self::PAYMENT_REFUNDED];
    }

    public static function paymentLabel(string $status): string
    {
        return match ($status) {
            self::PAYMENT_UNPAID   => 'Unpaid',
            self::PAYMENT_PAID     => 'Paid',
            self::PAYMENT_REFUNDED => 'Refunded',
            default                => ucfirst($status),
        };
    }

    public static function paymentTone(string $status): string
    {
        return match ($status) {
            self::PAYMENT_PAID     => 'success',
            self::PAYMENT_REFUNDED => 'info',
            default                => 'warning',
        };
    }
}
