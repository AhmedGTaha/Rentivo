<?php

declare(strict_types=1);

namespace Rentivo\Services;

use DateTimeInterface;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Support\DateTimeHelper;

/**
 * The single authority on "can this car be rented for this window?".
 *
 * Two independent conditions must both hold:
 *
 *   1. Operational state — a car in maintenance or inactive is never rentable.
 *      A car whose current status is `rented` is still rentable for a future
 *      window, because status describes today, not the future.
 *
 *   2. Date overlap — no booking in a blocking status (confirmed,
 *      ready_for_pickup, active) may overlap the requested window. Pending
 *      bookings never block.
 */
final class AvailabilityService
{
    public function __construct(
        private BookingRepository $bookings,
        private CarRepository $cars
    ) {
    }

    /**
     * @param array<string,mixed> $car
     */
    public function isOperationallyRentable(array $car): bool
    {
        if (($car['archived_at'] ?? null) !== null) {
            return false;
        }

        return !in_array((string) $car['status'], CarRepository::UNRENTABLE_STATUSES, true);
    }

    /**
     * @param array<string,mixed> $car
     * @return array{available:bool,reason:?string,conflicts:list<array<string,mixed>>}
     */
    public function check(
        array $car,
        DateTimeInterface $pickupAt,
        DateTimeInterface $returnAt,
        ?int $exceptBookingId = null
    ): array {
        if (!$this->isOperationallyRentable($car)) {
            return [
                'available' => false,
                'reason'    => (string) $car['status'] === 'maintenance'
                    ? 'This car is currently under maintenance.'
                    : 'This car is not available for new rentals.',
                'conflicts' => [],
            ];
        }

        if ($returnAt <= $pickupAt) {
            return [
                'available' => false,
                'reason'    => 'The return time must be after the pickup time.',
                'conflicts' => [],
            ];
        }

        $conflicts = $this->bookings->overlappingBlockingBookings(
            (int) $car['id'],
            DateTimeHelper::toDb($pickupAt),
            DateTimeHelper::toDb($returnAt),
            $exceptBookingId
        );

        if ($conflicts !== []) {
            return [
                'available' => false,
                'reason'    => 'This car is already booked for part of the selected period.',
                'conflicts' => $conflicts,
            ];
        }

        return ['available' => true, 'reason' => null, 'conflicts' => []];
    }

    /**
     * Convenience boolean form for views.
     *
     * @param array<string,mixed> $car
     */
    public function isAvailable(array $car, DateTimeInterface $pickupAt, DateTimeInterface $returnAt): bool
    {
        return $this->check($car, $pickupAt, $returnAt)['available'];
    }

    /**
     * The authoritative check performed inside the confirmation transaction,
     * after the car row has been locked.
     *
     * @return list<array<string,mixed>> Conflicting bookings, empty when clear.
     */
    public function lockedConflicts(
        int $carId,
        DateTimeInterface $pickupAt,
        DateTimeInterface $returnAt,
        ?int $exceptBookingId = null
    ): array {
        return $this->bookings->overlappingBlockingBookingsForUpdate(
            $carId,
            DateTimeHelper::toDb($pickupAt),
            DateTimeHelper::toDb($returnAt),
            $exceptBookingId
        );
    }

    /**
     * Blocking bookings for a car in the near future, used to warn management
     * before putting a car into maintenance.
     *
     * @return list<array<string,mixed>>
     */
    public function upcomingBlockingBookings(int $carId): array
    {
        $now = DateTimeHelper::now();

        return $this->bookings->overlappingBlockingBookings(
            $carId,
            DateTimeHelper::toDb($now),
            DateTimeHelper::toDb($now->modify('+1 year'))
        );
    }

    public function carRepository(): CarRepository
    {
        return $this->cars;
    }
}
