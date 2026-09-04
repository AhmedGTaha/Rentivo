<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Http\HttpException;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\RentalRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\BookingException;
use Rentivo\Services\BookingService;
use Rentivo\Services\BookingStatus;
use Rentivo\Services\RentalService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Test 10: pickup and return.
 *
 * Checkout creates the rental, captures mileage and fuel, makes the car rented
 * and the booking active. Return validates the odometer, closes the booking and
 * sets the car's next status.
 */
final class RentalLifecycleTest extends TestCase
{
    private array $organization;
    private int $adminId;
    private int $customerId;
    private array $car;
    private string $reference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminId = $this->createUser('admin@example.test', 'Admin');
        $this->customerId = $this->createUserWithPhone('customer@example.test', 'Customer');

        $this->organization = $this->createOrganization('Rental Agency', $this->adminId);
        $this->car = $this->createCar((int) $this->organization['id'], ['mileage' => 10000]);

        $booking = $this->app->get(BookingService::class)->createForCustomer(
            $this->car,
            $this->customerId,
            DateTimeHelper::now()->modify('+1 day'),
            DateTimeHelper::now()->modify('+3 days'),
            null,
            null
        );

        $this->reference = (string) $booking['reference'];
    }

    private function context(): OrganizationContext
    {
        $this->actingAs($this->adminId);

        return $this->contextFor((string) $this->organization['slug']);
    }

    /** Advances the booking to ready_for_pickup. */
    private function prepareForPickup(): OrganizationContext
    {
        $context = $this->context();

        $bookings = $this->app->get(BookingService::class);
        $bookings->confirm($context, $this->reference);
        $bookings->markReadyForPickup($context, $this->reference);

        return $context;
    }

    private function rentals(): RentalService
    {
        return $this->app->get(RentalService::class);
    }

    // -----------------------------------------------------------------
    // Pickup
    // -----------------------------------------------------------------

    public function testCheckoutCreatesTheRentalAndActivatesTheBooking(): void
    {
        $context = $this->prepareForPickup();

        $rental = $this->rentals()->checkout($context, $this->reference, [
            'mileage'         => 10500,
            'fuel_percentage' => 100,
            'condition'       => 'Minor scratch on the rear bumper.',
        ]);

        self::assertSame(10500, (int) $rental['checkout_mileage']);
        self::assertSame(100, (int) $rental['checkout_fuel_percentage']);
        self::assertSame($this->adminId, (int) $rental['checkout_employee_user_id']);
        self::assertNull($rental['actual_return_at']);

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);
        $booking = $bookings->findInOrganization($this->reference, (int) $this->organization['id']);
        self::assertSame(BookingStatus::ACTIVE, $booking['status']);

        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $car = $cars->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);
        self::assertSame('rented', $car['status']);
        self::assertSame(10500, (int) $car['mileage']);
    }

    /** A booking must reach ready_for_pickup before it can be checked out. */
    public function testCannotCheckOutABookingThatIsNotReady(): void
    {
        $context = $this->context();
        $this->app->get(BookingService::class)->confirm($context, $this->reference);

        $this->expectException(BookingException::class);

        $this->rentals()->checkout($context, $this->reference, [
            'mileage'         => 10500,
            'fuel_percentage' => 100,
            'condition'       => null,
        ]);
    }

    public function testCannotCheckOutTheSameBookingTwice(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage'         => 10500,
            'fuel_percentage' => 100,
            'condition'       => null,
        ]);

        $this->expectException(BookingException::class);

        $this->rentals()->checkout($context, $this->reference, [
            'mileage'         => 10600,
            'fuel_percentage' => 90,
            'condition'       => null,
        ]);
    }

    public function testCheckoutRequiresThePermission(): void
    {
        $this->prepareForPickup();

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $this->addEmployee((int) $this->organization['id'], $employeeId, [Permissions::BOOKINGS_VIEW]);

        $this->actingAs($employeeId);
        $employeeContext = $this->contextFor((string) $this->organization['slug']);

        $this->expectException(HttpException::class);

        $this->rentals()->checkout($employeeContext, $this->reference, [
            'mileage'         => 10500,
            'fuel_percentage' => 100,
            'condition'       => null,
        ]);
    }

    // -----------------------------------------------------------------
    // Return
    // -----------------------------------------------------------------

    public function testReturnCompletesTheBookingAndFreesTheCar(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage'         => 10500,
            'fuel_percentage' => 100,
            'condition'       => null,
        ]);

        $rental = $this->rentals()->completeReturn($context, $this->reference, [
            'mileage'                 => 10850,
            'fuel_percentage'         => 75,
            'condition'               => 'Returned clean.',
            'damage_notes'            => null,
            'additional_charges_fils' => 0,
            'final_car_status'        => 'available',
        ]);

        self::assertSame(10850, (int) $rental['return_mileage']);
        self::assertSame(75, (int) $rental['return_fuel_percentage']);
        self::assertNotNull($rental['actual_return_at']);
        self::assertSame($this->adminId, (int) $rental['return_employee_user_id']);

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);
        $booking = $bookings->findInOrganization($this->reference, (int) $this->organization['id']);

        self::assertSame(BookingStatus::COMPLETED, $booking['status']);
        self::assertNotNull($booking['completed_at']);

        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $car = $cars->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);

        self::assertSame('available', $car['status']);
        self::assertSame(10850, (int) $car['mileage']);
    }

    public function testReturnCanSendTheCarToMaintenance(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        $this->rentals()->completeReturn($context, $this->reference, [
            'mileage'                 => 10900,
            'fuel_percentage'         => 40,
            'condition'               => null,
            'damage_notes'            => 'Dented nearside door.',
            'additional_charges_fils' => 0,
            'final_car_status'        => 'maintenance',
        ]);

        $car = $this->app->get(CarRepository::class)
            ->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);

        self::assertSame('maintenance', $car['status']);
    }

    /** SRS §48: return_mileage >= checkout_mileage. */
    public function testReturnMileageCannotBeLowerThanCheckoutMileage(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        try {
            $this->rentals()->completeReturn($context, $this->reference, [
                'mileage'                 => 10499,
                'fuel_percentage'         => 80,
                'condition'               => null,
                'damage_notes'            => null,
                'additional_charges_fils' => 0,
                'final_car_status'        => 'available',
            ]);
            self::fail('A return mileage below the checkout mileage was accepted.');
        } catch (BookingException $e) {
            self::assertStringContainsString('cannot be lower', $e->getMessage());
        }

        // The booking is still active; nothing was half-applied.
        $booking = $this->app->get(BookingRepository::class)
            ->findInOrganization($this->reference, (int) $this->organization['id']);

        self::assertSame(BookingStatus::ACTIVE, $booking['status']);
    }

    public function testIdenticalMileageIsAccepted(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        $rental = $this->rentals()->completeReturn($context, $this->reference, [
            'mileage'                 => 10500,
            'fuel_percentage'         => 100,
            'condition'               => null,
            'damage_notes'            => null,
            'additional_charges_fils' => 0,
            'final_car_status'        => 'available',
        ]);

        self::assertSame(10500, (int) $rental['return_mileage']);
    }

    /**
     * Additional charges recompute the total from the stored subtotal, leaving
     * the original rate snapshot alone.
     */
    public function testAdditionalChargesUpdateTheTotalWithoutTouchingTheSnapshot(): void
    {
        $context = $this->prepareForPickup();

        $before = $this->app->get(BookingRepository::class)
            ->findInOrganization($this->reference, (int) $this->organization['id']);

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        $this->rentals()->completeReturn($context, $this->reference, [
            'mileage'                 => 10800,
            'fuel_percentage'         => 50,
            'condition'               => null,
            'damage_notes'            => 'Returned half empty.',
            'additional_charges_fils' => 7500,
            'final_car_status'        => 'available',
        ]);

        $after = $this->app->get(BookingRepository::class)
            ->findInOrganization($this->reference, (int) $this->organization['id']);

        self::assertSame(7500, (int) $after['additional_charges_fils']);
        self::assertSame((int) $before['subtotal_fils'] + 7500, (int) $after['total_fils']);
        self::assertSame(
            (int) $before['daily_rate_snapshot_fils'],
            (int) $after['daily_rate_snapshot_fils'],
            'The rate snapshot must never change.'
        );
    }

    public function testCannotReturnABookingThatWasNeverCheckedOut(): void
    {
        $context = $this->context();
        $this->app->get(BookingService::class)->confirm($context, $this->reference);

        $this->expectException(BookingException::class);

        $this->rentals()->completeReturn($context, $this->reference, [
            'mileage'                 => 10800,
            'fuel_percentage'         => 80,
            'condition'               => null,
            'damage_notes'            => null,
            'additional_charges_fils' => 0,
            'final_car_status'        => 'available',
        ]);
    }

    public function testCannotReturnTwice(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        $inspection = [
            'mileage'                 => 10800,
            'fuel_percentage'         => 80,
            'condition'               => null,
            'damage_notes'            => null,
            'additional_charges_fils' => 0,
            'final_car_status'        => 'available',
        ];

        $this->rentals()->completeReturn($context, $this->reference, $inspection);

        $this->expectException(BookingException::class);
        $this->rentals()->completeReturn($context, $this->reference, $inspection);
    }

    public function testFinalCarStatusIsRestrictedToAvailableOrMaintenance(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        $this->expectException(BookingException::class);

        $this->rentals()->completeReturn($context, $this->reference, [
            'mileage'                 => 10800,
            'fuel_percentage'         => 80,
            'condition'               => null,
            'damage_notes'            => null,
            'additional_charges_fils' => 0,
            'final_car_status'        => 'rented',
        ]);
    }

    /** One booking has at most one rental (SRS §47). */
    public function testRentalIsUniquePerBooking(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        $booking = $this->app->get(BookingRepository::class)
            ->findInOrganization($this->reference, (int) $this->organization['id']);

        $count = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM rentals WHERE booking_id = ?',
            [(int) $booking['id']]
        );

        self::assertSame(1, $count);
    }

    /** Overdue is derived, never stored as a booking status. */
    public function testOverdueIsDerivedFromTheData(): void
    {
        $context = $this->prepareForPickup();

        $this->rentals()->checkout($context, $this->reference, [
            'mileage' => 10500, 'fuel_percentage' => 100, 'condition' => null,
        ]);

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);

        self::assertSame(0, $bookings->countOverdue((int) $this->organization['id']));

        // Move the return date into the past.
        $booking = $bookings->findInOrganization($this->reference, (int) $this->organization['id']);
        $this->db()->update('bookings', [
            'return_at' => DateTimeHelper::now()->modify('-2 hours')->format(DateTimeHelper::DB_FORMAT),
        ], ['id' => (int) $booking['id']]);

        self::assertSame(1, $bookings->countOverdue((int) $this->organization['id']));

        // The status itself is still "active" — there is no overdue state.
        $reloaded = $bookings->findInOrganization($this->reference, (int) $this->organization['id']);
        self::assertSame(BookingStatus::ACTIVE, $reloaded['status']);
    }
}
