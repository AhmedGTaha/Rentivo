<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use DateTimeImmutable;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Security\Permissions;
use Rentivo\Services\AvailabilityService;
use Rentivo\Services\BookingException;
use Rentivo\Services\BookingService;
use Rentivo\Services\BookingStatus;
use Rentivo\Services\CarService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Tests 6, 7 and 9: booking conflicts, historical
 * pricing and the booking lifecycle.
 */
final class BookingWorkflowTest extends TestCase
{
    private array $organization;
    private int $adminId;
    private int $customerId;
    private int $otherCustomerId;
    private array $car;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminId = $this->createUser('owner@example.test', 'Owner');
        $this->customerId = $this->createUserWithPhone('customer@example.test', 'Customer One');
        $this->otherCustomerId = $this->createUserWithPhone('customer2@example.test', 'Customer Two');

        $this->organization = $this->createOrganization('Bookings Agency', $this->adminId);
        $this->car = $this->createCar((int) $this->organization['id'], ['daily_rate_fils' => 20000]);
    }

    private function bookingService(): BookingService
    {
        return $this->app->get(BookingService::class);
    }

    private function at(string $modifier): DateTimeImmutable
    {
        return DateTimeHelper::now()->modify($modifier);
    }

    /** Creates a pending booking for the given window. */
    private function book(int $userId, string $from, string $to): array
    {
        return $this->bookingService()->createForCustomer(
            $this->car,
            $userId,
            $this->at($from),
            $this->at($to),
            null,
            null
        );
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    public function testNewBookingsStartPendingAndAreNeverAutoConfirmed(): void
    {
        $booking = $this->book($this->customerId, '+2 days', '+4 days');

        self::assertSame(BookingStatus::PENDING, $booking['status']);
        self::assertSame('unpaid', $booking['payment_status']);
        self::assertNull($booking['confirmed_at']);
    }

    public function testReferenceFollowsTheSpecifiedFormatAndIsUnique(): void
    {
        $first = $this->book($this->customerId, '+2 days', '+3 days');
        $second = $this->book($this->otherCustomerId, '+10 days', '+11 days');

        self::assertMatchesRegularExpression('/^BK-\d{4}-\d{6}$/', (string) $first['reference']);
        self::assertNotSame($first['reference'], $second['reference']);
    }

    public function testFirstBookingCreatesTheOrganizationCustomerRelationship(): void
    {
        /** @var OrganizationCustomerRepository $customers */
        $customers = $this->app->get(OrganizationCustomerRepository::class);

        self::assertFalse($customers->relationshipExists((int) $this->organization['id'], $this->customerId));

        $this->book($this->customerId, '+2 days', '+4 days');

        self::assertTrue($customers->relationshipExists((int) $this->organization['id'], $this->customerId));
    }

    public function testPricingIsStoredAsIntegerFils(): void
    {
        // 20.000 BHD/day over exactly two days.
        $booking = $this->book($this->customerId, '+2 days', '+4 days');

        self::assertSame(2, (int) $booking['rental_days']);
        self::assertSame(20000, (int) $booking['daily_rate_snapshot_fils']);
        self::assertSame(40000, (int) $booking['subtotal_fils']);
        self::assertSame(40000, (int) $booking['total_fils']);
    }

    public function testCannotBookAPeriodThatEndsBeforeItStarts(): void
    {
        $this->expectException(BookingException::class);

        $this->book($this->customerId, '+4 days', '+2 days');
    }

    public function testCannotBookACarInMaintenance(): void
    {
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $cars->setStatus((int) $this->car['id'], (int) $this->organization['id'], 'maintenance');

        $this->car = $cars->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);

        $this->expectException(BookingException::class);

        $this->book($this->customerId, '+2 days', '+4 days');
    }

    // -----------------------------------------------------------------
    // SRS Test 7: historical pricing
    // -----------------------------------------------------------------

    /**
     * A later price change must never rewrite an existing booking's totals.
     */
    public function testChangingTheCarRateDoesNotAlterExistingBookings(): void
    {
        $booking = $this->book($this->customerId, '+2 days', '+4 days');

        self::assertSame(20000, (int) $booking['daily_rate_snapshot_fils']);
        self::assertSame(40000, (int) $booking['total_fils']);

        // The agency raises the rate from 20.000 to 25.000 BHD.
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $cars->update((int) $this->car['id'], (int) $this->organization['id'], ['daily_rate_fils' => 25000]);

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);
        $reloaded = $bookings->findForUser((string) $booking['reference'], $this->customerId);

        self::assertSame(20000, (int) $reloaded['daily_rate_snapshot_fils'], 'Snapshot must not move.');
        self::assertSame(40000, (int) $reloaded['total_fils'], 'Historical total must not move.');

        // A new booking picks up the new rate.
        $newBooking = $this->book($this->otherCustomerId, '+20 days', '+22 days');
        self::assertSame(25000, (int) $newBooking['daily_rate_snapshot_fils']);
    }

    // -----------------------------------------------------------------
    // SRS Test 6: booking conflict
    // -----------------------------------------------------------------

    /**
     * Two overlapping pending bookings both exist; the first confirmation wins
     * and the second must fail.
     */
    public function testConfirmingTwoOverlappingBookingsFails(): void
    {
        $first = $this->book($this->customerId, '+2 days', '+5 days');
        $second = $this->book($this->otherCustomerId, '+3 days', '+6 days');

        // Pending bookings never block each other.
        self::assertSame(BookingStatus::PENDING, $first['status']);
        self::assertSame(BookingStatus::PENDING, $second['status']);

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);

        $confirmed = $this->bookingService()->confirm($context, (string) $first['reference']);
        self::assertSame(BookingStatus::CONFIRMED, $confirmed['status']);

        try {
            $this->bookingService()->confirm($context, (string) $second['reference']);
            self::fail('The overlapping second booking was confirmed.');
        } catch (BookingException $e) {
            self::assertTrue($e->hasConflicts());
            self::assertSame((string) $first['reference'], $e->conflicts()[0]['reference']);
        }

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);

        // The second booking is untouched, and only one confirmed booking exists.
        $secondNow = $bookings->findInOrganization((string) $second['reference'], (int) $this->organization['id']);
        self::assertSame(BookingStatus::PENDING, $secondNow['status']);

        $confirmedCount = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM bookings WHERE car_id = ? AND status = 'confirmed'",
            [(int) $this->car['id']]
        );
        self::assertSame(1, $confirmedCount);
    }

    /** Adjacent windows that merely touch do not overlap. */
    public function testTouchingWindowsAreNotAConflict(): void
    {
        $first = $this->book($this->customerId, '+2 days', '+4 days');
        $second = $this->book($this->otherCustomerId, '+4 days', '+6 days');

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);

        $this->bookingService()->confirm($context, (string) $first['reference']);
        $confirmedSecond = $this->bookingService()->confirm($context, (string) $second['reference']);

        self::assertSame(BookingStatus::CONFIRMED, $confirmedSecond['status']);
    }

    /**
     * A confirmed booking blocks the window for anyone else, including at
     * creation time.
     */
    public function testConfirmedBookingBlocksNewOverlappingBookings(): void
    {
        $first = $this->book($this->customerId, '+2 days', '+5 days');

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);
        $this->bookingService()->confirm($context, (string) $first['reference']);

        $this->expectException(BookingException::class);

        $this->book($this->otherCustomerId, '+3 days', '+4 days');
    }

    // -----------------------------------------------------------------
    // SRS Test 9: lifecycle
    // -----------------------------------------------------------------

    public function testFullHappyPathLifecycle(): void
    {
        $booking = $this->book($this->customerId, '+1 day', '+3 days');
        $reference = (string) $booking['reference'];

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);
        $service = $this->bookingService();

        self::assertSame(BookingStatus::CONFIRMED, $service->confirm($context, $reference)['status']);
        self::assertSame(
            BookingStatus::READY_FOR_PICKUP,
            $service->markReadyForPickup($context, $reference)['status']
        );
    }

    public function testInvalidTransitionsAreRefusedServerSide(): void
    {
        $booking = $this->book($this->customerId, '+1 day', '+3 days');
        $reference = (string) $booking['reference'];

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);

        // pending → ready_for_pickup skips confirmation.
        $this->expectException(BookingException::class);
        $this->bookingService()->markReadyForPickup($context, $reference);
    }

    public function testCannotConfirmATerminalBooking(): void
    {
        $booking = $this->book($this->customerId, '+1 day', '+3 days');
        $reference = (string) $booking['reference'];

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);

        $this->bookingService()->reject($context, $reference, 'No vehicles available');

        $this->expectException(BookingException::class);
        $this->bookingService()->confirm($context, $reference);
    }

    // -----------------------------------------------------------------
    // Customer cancellation (SRS §41)
    // -----------------------------------------------------------------

    public function testCustomerCanCancelWhilePending(): void
    {
        $booking = $this->book($this->customerId, '+2 days', '+4 days');

        $cancelled = $this->bookingService()->cancelByCustomer(
            $this->customerId,
            (string) $booking['reference'],
            'Plans changed'
        );

        self::assertSame(BookingStatus::CANCELLED, $cancelled['status']);
        self::assertSame($this->customerId, (int) $cancelled['cancelled_by_user_id']);
        self::assertSame('Plans changed', $cancelled['cancellation_reason']);
        self::assertNotNull($cancelled['cancelled_at']);
    }

    public function testCustomerCanCancelWhileConfirmed(): void
    {
        $booking = $this->book($this->customerId, '+2 days', '+4 days');

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);
        $this->bookingService()->confirm($context, (string) $booking['reference']);

        $cancelled = $this->bookingService()->cancelByCustomer(
            $this->customerId,
            (string) $booking['reference'],
            null
        );

        self::assertSame(BookingStatus::CANCELLED, $cancelled['status']);
    }

    public function testCustomerCannotCancelOnceReadyForPickup(): void
    {
        $booking = $this->book($this->customerId, '+2 days', '+4 days');
        $reference = (string) $booking['reference'];

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);
        $this->bookingService()->confirm($context, $reference);
        $this->bookingService()->markReadyForPickup($context, $reference);

        $this->expectException(BookingException::class);

        $this->bookingService()->cancelByCustomer($this->customerId, $reference, null);
    }

    /** A customer can never cancel a booking that is not theirs. */
    public function testCustomerCannotCancelSomeoneElsesBooking(): void
    {
        $booking = $this->book($this->customerId, '+2 days', '+4 days');

        $this->expectException(BookingException::class);

        $this->bookingService()->cancelByCustomer(
            $this->otherCustomerId,
            (string) $booking['reference'],
            null
        );
    }

    // -----------------------------------------------------------------
    // Availability (SRS §38, §40)
    // -----------------------------------------------------------------

    public function testPendingBookingsDoNotBlockAvailability(): void
    {
        $this->book($this->customerId, '+2 days', '+5 days');

        /** @var AvailabilityService $availability */
        $availability = $this->app->get(AvailabilityService::class);

        self::assertTrue(
            $availability->isAvailable($this->car, $this->at('+3 days'), $this->at('+4 days')),
            'A pending booking must not block the car.'
        );
    }

    /**
     * A car currently marked rented is still bookable for a later window once
     * its blocking rental has ended (SRS §40).
     */
    public function testCurrentlyRentedCarIsAvailableForALaterFreeWindow(): void
    {
        // An in-progress rental started in the past, so it is inserted directly
        // rather than through createForCustomer, which rightly refuses a
        // pickup time that has already passed.
        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);

        $bookings->create([
            'reference'                => $bookings->nextReference(),
            'organization_id'          => (int) $this->organization['id'],
            'user_id'                  => $this->customerId,
            'car_id'                   => (int) $this->car['id'],
            'pickup_at'                => DateTimeHelper::toDb($this->at('-1 day')),
            'return_at'                => DateTimeHelper::toDb($this->at('+1 day')),
            'rental_days'              => 2,
            'daily_rate_snapshot_fils' => 20000,
            'subtotal_fils'            => 40000,
            'total_fils'               => 40000,
            'status'                   => BookingStatus::ACTIVE,
        ]);

        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $cars->setStatus((int) $this->car['id'], (int) $this->organization['id'], 'rented');

        $rentedCar = $cars->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);

        /** @var AvailabilityService $availability */
        $availability = $this->app->get(AvailabilityService::class);

        // Overlapping the current rental: not available.
        self::assertFalse($availability->isAvailable($rentedCar, $this->at('now'), $this->at('+12 hours')));

        // After it ends: available, despite the status still being "rented".
        self::assertTrue($availability->isAvailable($rentedCar, $this->at('+5 days'), $this->at('+7 days')));
    }

    public function testMaintenanceCarsAreNeverAvailable(): void
    {
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $cars->setStatus((int) $this->car['id'], (int) $this->organization['id'], 'maintenance');

        $car = $cars->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);

        /** @var AvailabilityService $availability */
        $availability = $this->app->get(AvailabilityService::class);

        self::assertFalse($availability->isAvailable($car, $this->at('+30 days'), $this->at('+32 days')));
    }

    /** The overlap rule from SRS §38, checked at its exact boundaries. */
    public function testOverlapBoundaries(): void
    {
        $booking = $this->book($this->customerId, '+5 days', '+10 days');

        $this->actingAs($this->adminId);
        $context = $this->contextFor((string) $this->organization['slug']);
        $this->bookingService()->confirm($context, (string) $booking['reference']);

        /** @var AvailabilityService $availability */
        $availability = $this->app->get(AvailabilityService::class);

        // Ends exactly when the booking starts: free.
        self::assertTrue($availability->isAvailable($this->car, $this->at('+3 days'), $this->at('+5 days')));
        // Starts exactly when the booking ends: free.
        self::assertTrue($availability->isAvailable($this->car, $this->at('+10 days'), $this->at('+12 days')));
        // Overlaps by an hour at the start: taken.
        self::assertFalse($availability->isAvailable($this->car, $this->at('+3 days'), $this->at('+5 days +1 hour')));
        // Entirely inside: taken.
        self::assertFalse($availability->isAvailable($this->car, $this->at('+6 days'), $this->at('+7 days')));
        // Entirely surrounding: taken.
        self::assertFalse($availability->isAvailable($this->car, $this->at('+1 day'), $this->at('+20 days')));
    }
}
