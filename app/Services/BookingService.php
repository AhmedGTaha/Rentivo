<?php

declare(strict_types=1);

namespace Rentivo\Services;

use DateTimeImmutable;
use Rentivo\Database\Connection;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Support\DateTimeHelper;

/**
 * The booking lifecycle.
 *
 * Two invariants are enforced here rather than in controllers:
 *
 *   - Every status change goes through transition(), which consults
 *     BookingStatus and refuses anything the state machine disallows.
 *   - Confirmation re-checks availability inside a transaction with the car
 *     row locked, so two staff members confirming overlapping requests at the
 *     same moment cannot both succeed.
 */
final class BookingService
{
    public function __construct(
        private Connection $db,
        private BookingRepository $bookings,
        private CarRepository $cars,
        private OrganizationCustomerRepository $organizationCustomers,
        private AvailabilityService $availability,
        private PricingService $pricing,
        private NotificationService $notifications,
        private AuditService $audit,
        private OrganizationUserRepository $members
    ) {
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    /**
     * Creates a pending booking for a customer.
     *
     * New bookings are never auto-confirmed: they always start pending and
     * wait for an organization decision.
     *
     * @param array<string,mixed> $car A public car row.
     *
     * @return array<string,mixed> The created booking.
     *
     * @throws BookingException
     */
    public function createForCustomer(
        array $car,
        int $userId,
        DateTimeImmutable $pickupAt,
        DateTimeImmutable $returnAt,
        ?int $pickupLocationId,
        ?int $returnLocationId,
        ?string $customerNotes = null
    ): array {
        if ($returnAt <= $pickupAt) {
            throw new BookingException('The return time must be after the pickup time.');
        }

        if ($pickupAt < DateTimeHelper::now()->modify('-1 hour')) {
            throw new BookingException('The pickup time cannot be in the past.');
        }

        $organizationId = (int) $car['organization_id'];
        $carId = (int) $car['id'];

        return $this->db->transaction(function () use (
            $car,
            $carId,
            $organizationId,
            $userId,
            $pickupAt,
            $returnAt,
            $pickupLocationId,
            $returnLocationId,
            $customerNotes
        ): array {
            // Lock the car so the availability snapshot cannot shift while we
            // build the booking.
            $locked = $this->cars->lockForUpdate($carId, $organizationId);

            if ($locked === null) {
                throw new BookingException('That car is no longer available.');
            }

            $check = $this->availability->check($locked, $pickupAt, $returnAt);

            if (!$check['available']) {
                throw BookingException::conflict(
                    $check['reason'] ?? 'That car is not available for the selected dates.',
                    $check['conflicts']
                );
            }

            // The rate is snapshotted now; later price changes never rewrite
            // this booking's totals.
            $quote = $this->pricing->quote($pickupAt, $returnAt, (int) $locked['daily_rate_fils']);

            $reference = $this->bookings->nextReference();

            $bookingId = $this->bookings->create([
                'reference'                => $reference,
                'organization_id'          => $organizationId,
                'user_id'                  => $userId,
                'car_id'                   => $carId,
                'pickup_location_id'       => $pickupLocationId,
                'return_location_id'       => $returnLocationId,
                'pickup_at'                => DateTimeHelper::toDb($pickupAt),
                'return_at'                => DateTimeHelper::toDb($returnAt),
                'rental_days'              => $quote['rental_days'],
                'daily_rate_snapshot_fils' => $quote['daily_rate_fils'],
                'subtotal_fils'            => $quote['subtotal_fils'],
                'additional_charges_fils'  => 0,
                'total_fils'               => $quote['total_fils'],
                'status'                   => BookingStatus::PENDING,
                'payment_status'           => BookingStatus::PAYMENT_UNPAID,
                'payment_method'           => 'pay_at_pickup',
                'customer_notes'           => $customerNotes,
            ]);

            // First booking with this agency establishes the customer record.
            $this->organizationCustomers->ensure($organizationId, $userId);

            $booking = $this->bookings->findById($bookingId);

            if ($booking === null) {
                throw new BookingException('The booking could not be created.');
            }

            $this->audit->record(
                $organizationId,
                $userId,
                AuditService::BOOKING_CREATED,
                'booking',
                $bookingId,
                ['reference' => $reference, 'total_fils' => $quote['total_fils']]
            );

            return $booking;
        });
    }

    /**
     * Sends the post-creation notifications. Called outside the creating
     * transaction so notification work never holds row locks.
     *
     * @param array<string,mixed> $booking
     */
    public function announceCreation(array $booking, string $customerName): void
    {
        $this->notifications->bookingSubmitted($booking, (string) $booking['organization_name']);

        $staff = $this->members->staffUserIdsWithPermission(
            (int) $booking['organization_id'],
            Permissions::BOOKINGS_CONFIRM
        );

        if ($staff !== []) {
            $this->notifications->bookingAwaitingReview($staff, $booking, $customerName);
        }
    }

    // -----------------------------------------------------------------
    // Management transitions
    // -----------------------------------------------------------------

    /**
     * Confirms a pending booking.
     *
     * Concurrency-safe by construction: BEGIN → lock car row → re-query
     * blocking bookings FOR UPDATE → decide → write → audit → COMMIT.
     *
     * @return array<string,mixed> The refreshed booking.
     *
     * @throws BookingException
     */
    public function confirm(OrganizationContext $context, string $reference): array
    {
        $context->authorize(Permissions::BOOKINGS_CONFIRM);

        return $this->db->transaction(function () use ($context, $reference): array {
            $booking = $this->bookings->findInOrganization($reference, $context->organizationId());

            if ($booking === null) {
                throw new BookingException('Booking not found.');
            }

            $this->assertTransition($booking, BookingStatus::CONFIRMED);

            // Lock the car row before evaluating conflicts.
            $car = $this->cars->lockForUpdate((int) $booking['car_id'], $context->organizationId());

            if ($car === null) {
                throw new BookingException('The car for this booking is no longer available.');
            }

            if (!$this->availability->isOperationallyRentable($car)) {
                throw new BookingException('This car is not currently rentable. Update its status first.');
            }

            $pickupAt = DateTimeHelper::fromDb((string) $booking['pickup_at']);
            $returnAt = DateTimeHelper::fromDb((string) $booking['return_at']);

            if ($pickupAt === null || $returnAt === null) {
                throw new BookingException('This booking has an invalid rental period.');
            }

            $conflicts = $this->availability->lockedConflicts(
                (int) $booking['car_id'],
                $pickupAt,
                $returnAt,
                (int) $booking['id']
            );

            if ($conflicts !== []) {
                throw BookingException::conflict(
                    'This car already has a confirmed booking that overlaps these dates.',
                    $conflicts
                );
            }

            $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
                'status'       => BookingStatus::CONFIRMED,
                'confirmed_at' => DateTimeHelper::nowDb(),
            ]);

            // Reserve the car when the confirmed rental is imminent.
            if ((string) $car['status'] === 'available') {
                $this->cars->setStatus((int) $car['id'], $context->organizationId(), 'reserved');
            }

            $this->audit->record(
                $context->organizationId(),
                $context->userId(),
                AuditService::BOOKING_CONFIRMED,
                'booking',
                (int) $booking['id'],
                ['reference' => $booking['reference']]
            );

            return $this->bookings->findInOrganization($reference, $context->organizationId()) ?? $booking;
        });
    }

    /** @throws BookingException */
    public function reject(OrganizationContext $context, string $reference, ?string $reason): array
    {
        $context->authorize(Permissions::BOOKINGS_REJECT);

        $booking = $this->requireBooking($context, $reference);
        $this->assertTransition($booking, BookingStatus::REJECTED);

        $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
            'status'           => BookingStatus::REJECTED,
            'rejection_reason' => $reason,
        ]);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::BOOKING_REJECTED,
            'booking',
            (int) $booking['id'],
            ['reference' => $booking['reference'], 'reason' => $reason]
        );

        $this->notifications->bookingRejected($booking, $context->name(), $reason);

        return $this->requireBooking($context, $reference);
    }

    /** @throws BookingException */
    public function cancelByStaff(OrganizationContext $context, string $reference, ?string $reason): array
    {
        $context->authorize(Permissions::BOOKINGS_CANCEL);

        $booking = $this->requireBooking($context, $reference);
        $this->assertTransition($booking, BookingStatus::CANCELLED);

        $this->db->transaction(function () use ($context, $booking, $reason): void {
            $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
                'status'               => BookingStatus::CANCELLED,
                'cancellation_reason'  => $reason,
                'cancelled_by_user_id' => $context->userId(),
                'cancelled_at'         => DateTimeHelper::nowDb(),
            ]);

            $this->releaseReservedCar((int) $booking['car_id'], $context->organizationId());
        });

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::BOOKING_CANCELLED,
            'booking',
            (int) $booking['id'],
            ['reference' => $booking['reference'], 'reason' => $reason, 'by' => 'staff']
        );

        $this->notifications->bookingCancelled($booking, $context->name(), false, $reason);

        return $this->requireBooking($context, $reference);
    }

    /** @throws BookingException */
    public function markReadyForPickup(OrganizationContext $context, string $reference): array
    {
        $context->authorize(Permissions::BOOKINGS_CHECKOUT);

        $booking = $this->requireBooking($context, $reference);
        $this->assertTransition($booking, BookingStatus::READY_FOR_PICKUP);

        $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
            'status' => BookingStatus::READY_FOR_PICKUP,
        ]);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::BOOKING_READY_FOR_PICKUP,
            'booking',
            (int) $booking['id'],
            ['reference' => $booking['reference']]
        );

        return $this->requireBooking($context, $reference);
    }

    /** @throws BookingException */
    public function markNoShow(OrganizationContext $context, string $reference, ?string $reason): array
    {
        $context->authorize(Permissions::BOOKINGS_CANCEL);

        $booking = $this->requireBooking($context, $reference);
        $this->assertTransition($booking, BookingStatus::NO_SHOW);

        $this->db->transaction(function () use ($context, $booking, $reason): void {
            $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
                'status'              => BookingStatus::NO_SHOW,
                'cancellation_reason' => $reason,
            ]);

            $this->releaseReservedCar((int) $booking['car_id'], $context->organizationId());
        });

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::BOOKING_NO_SHOW,
            'booking',
            (int) $booking['id'],
            ['reference' => $booking['reference']]
        );

        return $this->requireBooking($context, $reference);
    }

    /** @throws BookingException */
    public function updatePaymentStatus(OrganizationContext $context, string $reference, string $paymentStatus): array
    {
        $context->authorize(Permissions::BOOKINGS_MANAGE_PAYMENT);

        if (!in_array($paymentStatus, BookingStatus::paymentStatuses(), true)) {
            throw new BookingException('That payment status is not valid.');
        }

        $booking = $this->requireBooking($context, $reference);

        $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
            'payment_status' => $paymentStatus,
        ]);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::PAYMENT_STATUS_CHANGED,
            'booking',
            (int) $booking['id'],
            [
                'reference' => $booking['reference'],
                'from'      => $booking['payment_status'],
                'to'        => $paymentStatus,
            ]
        );

        return $this->requireBooking($context, $reference);
    }

    public function updateAdminNotes(OrganizationContext $context, string $reference, ?string $notes): array
    {
        $context->authorize(Permissions::BOOKINGS_VIEW);

        $booking = $this->requireBooking($context, $reference);

        $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
            'admin_notes' => $notes,
        ]);

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::BOOKING_NOTES_UPDATED,
            'booking',
            (int) $booking['id'],
            ['reference' => $booking['reference']]
        );

        return $this->requireBooking($context, $reference);
    }

    // -----------------------------------------------------------------
    // Customer actions
    // -----------------------------------------------------------------

    /**
     * Customer cancellation.
     *
     * Permitted only from pending or confirmed; every later status belongs to
     * the organization's operational flow.
     *
     * @throws BookingException
     */
    public function cancelByCustomer(int $userId, string $reference, ?string $reason): array
    {
        $booking = $this->bookings->findForUser($reference, $userId);

        if ($booking === null) {
            throw new BookingException('Booking not found.');
        }

        if (!BookingStatus::customerMayCancel((string) $booking['status'])) {
            throw new BookingException(
                'This booking can no longer be cancelled online. Please contact the agency directly.'
            );
        }

        $this->db->transaction(function () use ($booking, $userId, $reason): void {
            $this->bookings->updateForUser((int) $booking['id'], $userId, [
                'status'               => BookingStatus::CANCELLED,
                'cancellation_reason'  => $reason,
                'cancelled_by_user_id' => $userId,
                'cancelled_at'         => DateTimeHelper::nowDb(),
            ]);

            $this->releaseReservedCar((int) $booking['car_id'], (int) $booking['organization_id']);
        });

        $this->audit->record(
            (int) $booking['organization_id'],
            $userId,
            AuditService::BOOKING_CANCELLED,
            'booking',
            (int) $booking['id'],
            ['reference' => $booking['reference'], 'reason' => $reason, 'by' => 'customer']
        );

        $this->notifications->bookingCancelled(
            $booking,
            (string) $booking['organization_name'],
            true,
            $reason
        );

        return $this->bookings->findForUser($reference, $userId) ?? $booking;
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * @return array<string,mixed>
     * @throws BookingException
     */
    private function requireBooking(OrganizationContext $context, string $reference): array
    {
        $booking = $this->bookings->findInOrganization($reference, $context->organizationId());

        if ($booking === null) {
            throw new BookingException('Booking not found.');
        }

        return $booking;
    }

    /**
     * @param array<string,mixed> $booking
     * @throws BookingException
     */
    private function assertTransition(array $booking, string $to): void
    {
        $from = (string) $booking['status'];

        if (!BookingStatus::canTransition($from, $to)) {
            throw new BookingException(sprintf(
                'A %s booking cannot be moved to %s.',
                strtolower(BookingStatus::label($from)),
                strtolower(BookingStatus::label($to))
            ));
        }
    }

    /**
     * Returns a car to "available" when it was only being held for a booking
     * that is no longer going ahead, and nothing else still blocks it.
     */
    private function releaseReservedCar(int $carId, int $organizationId): void
    {
        $car = $this->cars->findInOrganization($carId, $organizationId);

        if ($car === null || (string) $car['status'] !== 'reserved') {
            return;
        }

        if ($this->availability->upcomingBlockingBookings($carId) === []) {
            $this->cars->setStatus($carId, $organizationId, 'available');
        }
    }

    public function bookings(): BookingRepository
    {
        return $this->bookings;
    }

    public function pricing(): PricingService
    {
        return $this->pricing;
    }

    public function availability(): AvailabilityService
    {
        return $this->availability;
    }
}
