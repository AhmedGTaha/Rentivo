<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\NotificationRepository;
use Rentivo\Services\BookingStatus;
use Rentivo\Services\NotificationService;
use Rentivo\Services\SchedulerService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Pagination;
use Rentivo\Tests\TestCase;

/**
 * The hourly scheduler (SRS §54) and the overdue rule (SRS §55).
 *
 * The scheduler must be idempotent: running it repeatedly can never produce a
 * duplicate reminder.
 */
final class SchedulerTest extends TestCase
{
    private array $organization;
    private int $adminId;
    private int $customerId;
    private array $car;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminId = $this->createUser('admin@example.test', 'Admin');
        $this->customerId = $this->createUserWithPhone('customer@example.test', 'Customer');

        $this->organization = $this->createOrganization('Scheduler Agency', $this->adminId);
        $this->car = $this->createCar((int) $this->organization['id']);
    }

    private function scheduler(): SchedulerService
    {
        return $this->app->get(SchedulerService::class);
    }

    private function notifications(): NotificationRepository
    {
        return $this->app->get(NotificationRepository::class);
    }

    /**
     * Inserts a booking directly so past and near-future windows can be built
     * without tripping the "pickup cannot be in the past" rule.
     */
    private function seedBooking(string $status, string $pickupModifier, string $returnModifier): int
    {
        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);

        return $bookings->create([
            'reference'                => $bookings->nextReference(),
            'organization_id'          => (int) $this->organization['id'],
            'user_id'                  => $this->customerId,
            'car_id'                   => (int) $this->car['id'],
            'pickup_at'                => DateTimeHelper::toDb(DateTimeHelper::now()->modify($pickupModifier)),
            'return_at'                => DateTimeHelper::toDb(DateTimeHelper::now()->modify($returnModifier)),
            'rental_days'              => 2,
            'daily_rate_snapshot_fils' => 20000,
            'subtotal_fils'            => 40000,
            'total_fils'               => 40000,
            'status'                   => $status,
        ]);
    }

    private function countOfType(string $type): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM notifications WHERE type = ?',
            [$type]
        );
    }

    // -----------------------------------------------------------------
    // Reminders
    // -----------------------------------------------------------------

    public function testPickupReminderIsSentForAnImminentPickup(): void
    {
        $this->seedBooking(BookingStatus::CONFIRMED, '+6 hours', '+3 days');

        self::assertSame(1, $this->scheduler()->sendPickupReminders());
        self::assertSame(1, $this->countOfType(NotificationService::PICKUP_REMINDER));
    }

    public function testPickupReminderIsNotSentForADistantPickup(): void
    {
        $this->seedBooking(BookingStatus::CONFIRMED, '+10 days', '+12 days');

        self::assertSame(0, $this->scheduler()->sendPickupReminders());
        self::assertSame(0, $this->countOfType(NotificationService::PICKUP_REMINDER));
    }

    /** Only bookings that are actually going ahead get a pickup reminder. */
    public function testPendingBookingsDoNotReceivePickupReminders(): void
    {
        $this->seedBooking(BookingStatus::PENDING, '+6 hours', '+3 days');

        self::assertSame(0, $this->scheduler()->sendPickupReminders());
    }

    public function testReturnReminderIsSentForAnImminentReturn(): void
    {
        $this->seedBooking(BookingStatus::ACTIVE, '-2 days', '+4 hours');

        self::assertSame(1, $this->scheduler()->sendReturnReminders());
        self::assertSame(1, $this->countOfType(NotificationService::RETURN_REMINDER));
    }

    public function testOverdueRentalGeneratesANotification(): void
    {
        $this->seedBooking(BookingStatus::ACTIVE, '-4 days', '-1 day');

        self::assertSame(1, $this->scheduler()->flagOverdueRentals());
        self::assertSame(1, $this->countOfType(NotificationService::RENTAL_OVERDUE));
    }

    public function testCompletedBookingsAreNeverOverdue(): void
    {
        $this->seedBooking(BookingStatus::COMPLETED, '-4 days', '-1 day');

        self::assertSame(0, $this->scheduler()->flagOverdueRentals());
    }

    // -----------------------------------------------------------------
    // Idempotency (SRS §54)
    // -----------------------------------------------------------------

    /**
     * The scheduler is designed for an hourly cron, so repeated runs must not
     * accumulate duplicate reminders.
     */
    public function testRunningTheSchedulerRepeatedlyProducesNoDuplicates(): void
    {
        $this->seedBooking(BookingStatus::CONFIRMED, '+6 hours', '+3 days');
        $this->seedBooking(BookingStatus::ACTIVE, '-2 days', '+4 hours');
        $this->seedBooking(BookingStatus::ACTIVE, '-6 days', '-2 days');

        $first = $this->scheduler()->run();

        self::assertSame(1, $first['pickup_reminders']);
        self::assertSame(1, $first['return_reminders']);
        self::assertSame(1, $first['overdue']);

        // Five more runs, as an hourly cron would produce.
        for ($i = 0; $i < 5; $i++) {
            $repeat = $this->scheduler()->run();

            self::assertSame(0, $repeat['pickup_reminders']);
            self::assertSame(0, $repeat['return_reminders']);
            self::assertSame(0, $repeat['overdue']);
        }

        self::assertSame(1, $this->countOfType(NotificationService::PICKUP_REMINDER));
        self::assertSame(1, $this->countOfType(NotificationService::RETURN_REMINDER));
        self::assertSame(1, $this->countOfType(NotificationService::RENTAL_OVERDUE));

        self::assertSame(3, (int) $this->db()->scalar('SELECT COUNT(*) FROM notifications'));
    }

    /** Distinct bookings each get their own reminder. */
    public function testEachBookingGetsItsOwnReminder(): void
    {
        $this->seedBooking(BookingStatus::CONFIRMED, '+3 hours', '+3 days');
        $this->seedBooking(BookingStatus::CONFIRMED, '+8 hours', '+4 days');

        self::assertSame(2, $this->scheduler()->sendPickupReminders());
        self::assertSame(0, $this->scheduler()->sendPickupReminders());
    }

    // -----------------------------------------------------------------
    // Notification centre
    // -----------------------------------------------------------------

    public function testNotificationsAreScopedToTheirOwner(): void
    {
        $other = $this->createUser('other@example.test', 'Other');

        $this->seedBooking(BookingStatus::CONFIRMED, '+6 hours', '+3 days');
        $this->scheduler()->sendPickupReminders();

        self::assertSame(1, $this->notifications()->countForUser($this->customerId));
        self::assertSame(0, $this->notifications()->countForUser($other));
    }

    public function testMarkingReadIsOwnerScoped(): void
    {
        $other = $this->createUser('other@example.test', 'Other');

        $this->seedBooking(BookingStatus::CONFIRMED, '+6 hours', '+3 days');
        $this->scheduler()->sendPickupReminders();

        $list = $this->notifications()->listForUser($this->customerId, new Pagination(1, 10, 10));
        $notificationId = (int) $list[0]['id'];

        // Another user cannot mark it read.
        self::assertSame(0, $this->notifications()->markRead($notificationId, $other));
        self::assertSame(1, $this->notifications()->unreadCount($this->customerId));

        // The owner can.
        self::assertSame(1, $this->notifications()->markRead($notificationId, $this->customerId));
        self::assertSame(0, $this->notifications()->unreadCount($this->customerId));

        // Marking an already-read notification changes nothing.
        self::assertSame(0, $this->notifications()->markRead($notificationId, $this->customerId));
    }

    public function testMarkAllReadOnlyAffectsTheOwner(): void
    {
        $other = $this->createUserWithPhone('other@example.test', 'Other');

        $this->seedBooking(BookingStatus::CONFIRMED, '+6 hours', '+3 days');
        $this->scheduler()->sendPickupReminders();

        // Give the other user an unrelated notification.
        $this->app->get(NotificationService::class)->notify(
            $other,
            NotificationService::BOOKING_SUBMITTED,
            'Unrelated',
            'A different user\'s notification.'
        );

        self::assertSame(1, $this->notifications()->markAllRead($this->customerId));
        self::assertSame(1, $this->notifications()->unreadCount($other));
    }

    /** Pruning expired rate-limit windows is part of the hourly run. */
    public function testSchedulerPrunesExpiredRateLimitWindows(): void
    {
        $this->db()->insert('rate_limits', [
            'limit_key'  => str_repeat('a', 64),
            'attempts'   => 5,
            'expires_at' => DateTimeHelper::now()->modify('-1 hour')->format(DateTimeHelper::DB_FORMAT),
            'created_at' => DateTimeHelper::nowDb(),
        ]);

        $this->db()->insert('rate_limits', [
            'limit_key'  => str_repeat('b', 64),
            'attempts'   => 1,
            'expires_at' => DateTimeHelper::now()->modify('+1 hour')->format(DateTimeHelper::DB_FORMAT),
            'created_at' => DateTimeHelper::nowDb(),
        ]);

        $result = $this->scheduler()->run();

        self::assertSame(1, $result['pruned_rate_limits']);
        self::assertSame(1, (int) $this->db()->scalar('SELECT COUNT(*) FROM rate_limits'));
    }
}
