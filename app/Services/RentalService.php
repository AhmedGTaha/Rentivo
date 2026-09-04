<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Database\Connection;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\RentalRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Support\DateTimeHelper;

/**
 * Pickup and return.
 *
 * Both operations are atomic: the booking status, the car status and the
 * rental record always move together or not at all.
 */
final class RentalService
{
    public function __construct(
        private Connection $db,
        private RentalRepository $rentals,
        private BookingRepository $bookings,
        private CarRepository $cars,
        private BookingService $bookingService,
        private FileStorageService $storage,
        private ImageService $images,
        private AuditService $audit,
        private NotificationService $notifications
    ) {
    }

    /**
     * Completes a pickup.
     *
     * @param array{mileage:int,fuel_percentage:int,condition:?string} $inspection
     * @param list<array{name:string,type:string,tmp_name:string,error:int,size:int}> $photos
     *
     * @return array<string,mixed> The created rental.
     *
     * @throws BookingException
     */
    public function checkout(
        OrganizationContext $context,
        string $reference,
        array $inspection,
        array $photos = []
    ): array {
        $context->authorize(Permissions::BOOKINGS_CHECKOUT);

        $rentalId = $this->db->transaction(function () use ($context, $reference, $inspection): int {
            $booking = $this->bookings->findInOrganization($reference, $context->organizationId());

            if ($booking === null) {
                throw new BookingException('Booking not found.');
            }

            if ((string) $booking['status'] !== BookingStatus::READY_FOR_PICKUP) {
                throw new BookingException(
                    'A booking must be marked ready for pickup before it can be checked out.'
                );
            }

            if (!BookingStatus::canTransition((string) $booking['status'], BookingStatus::ACTIVE)) {
                throw new BookingException('This booking cannot be checked out.');
            }

            $existing = $this->rentals->findByBooking((int) $booking['id'], $context->organizationId());

            if ($existing !== null) {
                throw new BookingException('This booking has already been checked out.');
            }

            $car = $this->cars->lockForUpdate((int) $booking['car_id'], $context->organizationId());

            if ($car === null) {
                throw new BookingException('The car for this booking could not be found.');
            }

            $now = DateTimeHelper::nowDb();

            $rentalId = $this->rentals->create([
                'booking_id'                => (int) $booking['id'],
                'organization_id'           => $context->organizationId(),
                'car_id'                    => (int) $booking['car_id'],
                'customer_user_id'          => (int) $booking['user_id'],
                'checkout_employee_user_id' => $context->userId(),
                'checkout_at'               => $now,
                'expected_return_at'        => (string) $booking['return_at'],
                'checkout_mileage'          => $inspection['mileage'],
                'checkout_fuel_percentage'  => $inspection['fuel_percentage'],
                'checkout_condition'        => $inspection['condition'],
            ]);

            $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
                'status' => BookingStatus::ACTIVE,
            ]);

            $this->cars->update((int) $car['id'], $context->organizationId(), [
                'status'  => 'rented',
                'mileage' => $inspection['mileage'],
            ]);

            $this->audit->record(
                $context->organizationId(),
                $context->userId(),
                AuditService::CHECKOUT_COMPLETED,
                'booking',
                (int) $booking['id'],
                [
                    'reference' => $booking['reference'],
                    'mileage'   => $inspection['mileage'],
                    'fuel'      => $inspection['fuel_percentage'],
                ]
            );

            return $rentalId;
        });

        // Photo processing happens after the transaction commits so image work
        // never holds database locks.
        $this->storeInspectionPhotos($context, $rentalId, 'checkout', $photos);

        return $this->rentals->findInOrganization($rentalId, $context->organizationId()) ?? [];
    }

    /**
     * Completes a return.
     *
     * @param array{
     *     mileage:int,
     *     fuel_percentage:int,
     *     condition:?string,
     *     damage_notes:?string,
     *     additional_charges_fils:int,
     *     final_car_status:string
     * } $inspection
     * @param list<array{name:string,type:string,tmp_name:string,error:int,size:int}> $photos
     *
     * @throws BookingException
     */
    public function completeReturn(
        OrganizationContext $context,
        string $reference,
        array $inspection,
        array $photos = []
    ): array {
        $context->authorize(Permissions::BOOKINGS_COMPLETE_RETURN);

        if (!in_array($inspection['final_car_status'], ['available', 'maintenance'], true)) {
            throw new BookingException('The car must be returned to service or sent to maintenance.');
        }

        $result = $this->db->transaction(function () use ($context, $reference, $inspection): array {
            $booking = $this->bookings->findInOrganization($reference, $context->organizationId());

            if ($booking === null) {
                throw new BookingException('Booking not found.');
            }

            if (!BookingStatus::canTransition((string) $booking['status'], BookingStatus::COMPLETED)) {
                throw new BookingException('Only an active rental can be completed.');
            }

            $rental = $this->rentals->findByBooking((int) $booking['id'], $context->organizationId());

            if ($rental === null) {
                throw new BookingException('No rental record exists for this booking.');
            }

            if ($rental['actual_return_at'] !== null) {
                throw new BookingException('This rental has already been returned.');
            }

            // The odometer cannot run backwards.
            if ($inspection['mileage'] < (int) $rental['checkout_mileage']) {
                throw new BookingException(sprintf(
                    'Return mileage (%s) cannot be lower than the checkout mileage (%s).',
                    number_format($inspection['mileage']),
                    number_format((int) $rental['checkout_mileage'])
                ));
            }

            $now = DateTimeHelper::nowDb();
            $charges = max(0, $inspection['additional_charges_fils']);

            $this->rentals->update((int) $rental['id'], $context->organizationId(), [
                'return_employee_user_id' => $context->userId(),
                'actual_return_at'        => $now,
                'return_mileage'          => $inspection['mileage'],
                'return_fuel_percentage'  => $inspection['fuel_percentage'],
                'return_condition'        => $inspection['condition'],
                'damage_notes'            => $inspection['damage_notes'],
                'additional_charges_fils' => $charges,
            ]);

            // Additional charges recompute the total from the original
            // subtotal; the rate snapshot is never revisited.
            $total = $this->bookingService->pricing()->recalculateTotal($booking, $charges);

            $this->bookings->updateInOrganization((int) $booking['id'], $context->organizationId(), [
                'status'                  => BookingStatus::COMPLETED,
                'additional_charges_fils' => $charges,
                'total_fils'              => $total,
                'completed_at'            => $now,
            ]);

            $this->cars->update((int) $booking['car_id'], $context->organizationId(), [
                'status'  => $inspection['final_car_status'],
                'mileage' => $inspection['mileage'],
            ]);

            $this->audit->record(
                $context->organizationId(),
                $context->userId(),
                AuditService::RETURN_COMPLETED,
                'booking',
                (int) $booking['id'],
                [
                    'reference'               => $booking['reference'],
                    'return_mileage'          => $inspection['mileage'],
                    'additional_charges_fils' => $charges,
                    'final_car_status'        => $inspection['final_car_status'],
                ]
            );

            return ['rental_id' => (int) $rental['id'], 'booking_id' => (int) $booking['id']];
        });

        $this->storeInspectionPhotos($context, $result['rental_id'], 'return', $photos);

        return $this->rentals->findInOrganization($result['rental_id'], $context->organizationId()) ?? [];
    }

    /**
     * Stores private inspection photographs under storage/private.
     *
     * @param list<array{name:string,type:string,tmp_name:string,error:int,size:int}> $photos
     */
    private function storeInspectionPhotos(
        OrganizationContext $context,
        int $rentalId,
        string $phase,
        array $photos
    ): void {
        if ($photos === []) {
            return;
        }

        $directory = sprintf('inspections/%d/%d/%s', $context->organizationId(), $rentalId, $phase);

        foreach (array_slice($photos, 0, 10) as $photo) {
            try {
                $path = $this->images->storeUploadedImage(
                    $photo,
                    FileStorageService::DISK_PRIVATE,
                    $directory,
                    1600
                );

                $this->rentals->addInspectionImage($rentalId, $phase, $path);
            } catch (UploadException) {
                // A rejected photo must not undo a completed pickup/return.
                continue;
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function inspectionImages(int $rentalId): array
    {
        return $this->rentals->inspectionImages($rentalId);
    }

    public function rentals(): RentalRepository
    {
        return $this->rentals;
    }
}
