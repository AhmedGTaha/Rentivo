<?php

declare(strict_types=1);

namespace Rentivo\Services;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Rental duration and price calculation.
 *
 * All arithmetic is integer arithmetic on fils. The only division is the
 * duration calculation, which operates on second counts and is immediately
 * ceiled to a whole number of days.
 */
final class PricingService
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * rental_days = max(1, ceil((return_at - pickup_at) / 24 hours))
     *
     * @throws InvalidArgumentException when the window is not ordered.
     */
    public function rentalDays(DateTimeInterface $pickupAt, DateTimeInterface $returnAt): int
    {
        $seconds = $returnAt->getTimestamp() - $pickupAt->getTimestamp();

        if ($seconds <= 0) {
            throw new InvalidArgumentException('The return time must be after the pickup time.');
        }

        return max(1, (int) ceil($seconds / self::SECONDS_PER_DAY));
    }

    /** subtotal_fils = rental_days * daily_rate_snapshot_fils */
    public function subtotalFils(int $rentalDays, int $dailyRateFils): int
    {
        return max(0, $rentalDays) * max(0, $dailyRateFils);
    }

    public function totalFils(int $subtotalFils, int $additionalChargesFils = 0): int
    {
        return max(0, $subtotalFils) + max(0, $additionalChargesFils);
    }

    /**
     * Full price breakdown for a requested window at a given rate.
     *
     * The rate passed in is the snapshot that will be persisted with the
     * booking, so later changes to the car's rate never affect this booking.
     *
     * @return array{
     *     rental_days:int,
     *     daily_rate_fils:int,
     *     subtotal_fils:int,
     *     additional_charges_fils:int,
     *     total_fils:int
     * }
     */
    public function quote(
        DateTimeInterface $pickupAt,
        DateTimeInterface $returnAt,
        int $dailyRateFils,
        int $additionalChargesFils = 0
    ): array {
        $days = $this->rentalDays($pickupAt, $returnAt);
        $subtotal = $this->subtotalFils($days, $dailyRateFils);

        return [
            'rental_days'             => $days,
            'daily_rate_fils'         => $dailyRateFils,
            'subtotal_fils'           => $subtotal,
            'additional_charges_fils' => max(0, $additionalChargesFils),
            'total_fils'              => $this->totalFils($subtotal, $additionalChargesFils),
        ];
    }

    /**
     * Recomputes a stored booking's total after additional charges change,
     * always reusing the original rate snapshot.
     *
     * @param array<string,mixed> $booking
     */
    public function recalculateTotal(array $booking, int $additionalChargesFils): int
    {
        return $this->totalFils((int) $booking['subtotal_fils'], $additionalChargesFils);
    }
}
