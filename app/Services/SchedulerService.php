<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Repositories\BookingRepository;
use Rentivo\Security\RateLimiter;
use Rentivo\Support\DateTimeHelper;

/**
 * Hourly maintenance work, designed to be safe to run repeatedly.
 *
 * Idempotency comes from the unique dedupe_key on notifications: a reminder
 * for a given booking can only ever be inserted once, so a scheduler that runs
 * twice in the same hour (or catches up after downtime) produces no duplicates.
 */
final class SchedulerService
{
    /** Notify about a pickup this many hours ahead. */
    private const PICKUP_LOOKAHEAD_HOURS = 24;

    /** Notify about a return this many hours ahead. */
    private const RETURN_LOOKAHEAD_HOURS = 12;

    public function __construct(
        private BookingRepository $bookings,
        private NotificationService $notifications,
        private RateLimiter $rateLimiter
    ) {
    }

    /**
     * Runs every scheduled task.
     *
     * @return array{pickup_reminders:int,return_reminders:int,overdue:int,pruned_rate_limits:int}
     */
    public function run(): array
    {
        return [
            'pickup_reminders'   => $this->sendPickupReminders(),
            'return_reminders'   => $this->sendReturnReminders(),
            'overdue'            => $this->flagOverdueRentals(),
            'pruned_rate_limits' => $this->rateLimiter->prune(),
        ];
    }

    /**
     * Reminds customers about pickups happening within the lookahead window.
     */
    public function sendPickupReminders(): int
    {
        $now = DateTimeHelper::now();

        $bookings = $this->bookings->dueForPickupReminder(
            $now->format(DateTimeHelper::DB_FORMAT),
            $now->modify('+' . self::PICKUP_LOOKAHEAD_HOURS . ' hours')->format(DateTimeHelper::DB_FORMAT)
        );

        $sent = 0;

        foreach ($bookings as $booking) {
            if ($this->notifications->pickupReminder($booking) > 0) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendReturnReminders(): int
    {
        $now = DateTimeHelper::now();

        $bookings = $this->bookings->dueForReturnReminder(
            $now->format(DateTimeHelper::DB_FORMAT),
            $now->modify('+' . self::RETURN_LOOKAHEAD_HOURS . ' hours')->format(DateTimeHelper::DB_FORMAT)
        );

        $sent = 0;

        foreach ($bookings as $booking) {
            if ($this->notifications->returnReminder($booking) > 0) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Notifies about rentals whose return time has passed.
     *
     * Overdue is a derived condition, not a stored booking status, so nothing
     * is written to the booking itself here.
     */
    public function flagOverdueRentals(): int
    {
        $sent = 0;

        foreach ($this->bookings->overdueBookings() as $booking) {
            if ($this->notifications->rentalOverdue($booking) > 0) {
                $sent++;
            }
        }

        return $sent;
    }
}
