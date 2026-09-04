<?php

declare(strict_types=1);

namespace Rentivo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rentivo\Services\BookingStatus;

/**
 * The booking state machine (SRS §36).
 *
 * These tests pin every allowed edge and assert that everything else is
 * refused, so an invalid transition cannot be introduced unnoticed.
 */
final class BookingStatusTest extends TestCase
{
    #[DataProvider('allowedTransitionProvider')]
    public function testAllowedTransitions(string $from, string $to): void
    {
        self::assertTrue(
            BookingStatus::canTransition($from, $to),
            sprintf('%s → %s should be allowed', $from, $to)
        );
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function allowedTransitionProvider(): array
    {
        return [
            'pending to confirmed'          => [BookingStatus::PENDING, BookingStatus::CONFIRMED],
            'pending to rejected'           => [BookingStatus::PENDING, BookingStatus::REJECTED],
            'pending to cancelled'          => [BookingStatus::PENDING, BookingStatus::CANCELLED],
            'confirmed to ready'            => [BookingStatus::CONFIRMED, BookingStatus::READY_FOR_PICKUP],
            'confirmed to cancelled'        => [BookingStatus::CONFIRMED, BookingStatus::CANCELLED],
            'ready to active'               => [BookingStatus::READY_FOR_PICKUP, BookingStatus::ACTIVE],
            'ready to cancelled'            => [BookingStatus::READY_FOR_PICKUP, BookingStatus::CANCELLED],
            'ready to no show'              => [BookingStatus::READY_FOR_PICKUP, BookingStatus::NO_SHOW],
            'active to completed'           => [BookingStatus::ACTIVE, BookingStatus::COMPLETED],
        ];
    }

    #[DataProvider('forbiddenTransitionProvider')]
    public function testForbiddenTransitions(string $from, string $to): void
    {
        self::assertFalse(
            BookingStatus::canTransition($from, $to),
            sprintf('%s → %s must be refused', $from, $to)
        );
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function forbiddenTransitionProvider(): array
    {
        return [
            'cannot skip confirmation'      => [BookingStatus::PENDING, BookingStatus::ACTIVE],
            'cannot skip to completed'      => [BookingStatus::PENDING, BookingStatus::COMPLETED],
            'cannot skip pickup'            => [BookingStatus::CONFIRMED, BookingStatus::ACTIVE],
            'cannot complete before active' => [BookingStatus::READY_FOR_PICKUP, BookingStatus::COMPLETED],
            'cannot cancel an active rental' => [BookingStatus::ACTIVE, BookingStatus::CANCELLED],
            'cannot reject an active rental' => [BookingStatus::ACTIVE, BookingStatus::REJECTED],
            'cannot revive a completed'     => [BookingStatus::COMPLETED, BookingStatus::ACTIVE],
            'cannot revive a cancelled'     => [BookingStatus::CANCELLED, BookingStatus::CONFIRMED],
            'cannot revive a rejected'      => [BookingStatus::REJECTED, BookingStatus::CONFIRMED],
            'cannot revive a no show'       => [BookingStatus::NO_SHOW, BookingStatus::ACTIVE],
            'cannot re-confirm'             => [BookingStatus::CONFIRMED, BookingStatus::CONFIRMED],
            'unknown source status'         => ['nonsense', BookingStatus::CONFIRMED],
            'unknown target status'         => [BookingStatus::PENDING, 'nonsense'],
        ];
    }

    #[DataProvider('terminalProvider')]
    public function testTerminalStatusesHaveNoOnwardTransitions(string $status): void
    {
        self::assertTrue(BookingStatus::isTerminal($status));
        self::assertSame([], BookingStatus::allowedTransitions($status));
    }

    /** @return array<string, array{0:string}> */
    public static function terminalProvider(): array
    {
        return [
            'completed' => [BookingStatus::COMPLETED],
            'rejected'  => [BookingStatus::REJECTED],
            'cancelled' => [BookingStatus::CANCELLED],
            'no show'   => [BookingStatus::NO_SHOW],
        ];
    }

    /**
     * Pending must never block availability; the three operational statuses
     * always must (SRS §38).
     */
    public function testBlockingStatuses(): void
    {
        self::assertTrue(BookingStatus::isBlocking(BookingStatus::CONFIRMED));
        self::assertTrue(BookingStatus::isBlocking(BookingStatus::READY_FOR_PICKUP));
        self::assertTrue(BookingStatus::isBlocking(BookingStatus::ACTIVE));

        self::assertFalse(BookingStatus::isBlocking(BookingStatus::PENDING));
        self::assertFalse(BookingStatus::isBlocking(BookingStatus::COMPLETED));
        self::assertFalse(BookingStatus::isBlocking(BookingStatus::CANCELLED));
        self::assertFalse(BookingStatus::isBlocking(BookingStatus::REJECTED));
        self::assertFalse(BookingStatus::isBlocking(BookingStatus::NO_SHOW));
    }

    /** Customers may only cancel from pending or confirmed (SRS §41). */
    public function testCustomerCancellationWindow(): void
    {
        self::assertTrue(BookingStatus::customerMayCancel(BookingStatus::PENDING));
        self::assertTrue(BookingStatus::customerMayCancel(BookingStatus::CONFIRMED));

        foreach ([
            BookingStatus::READY_FOR_PICKUP,
            BookingStatus::ACTIVE,
            BookingStatus::COMPLETED,
            BookingStatus::REJECTED,
            BookingStatus::CANCELLED,
            BookingStatus::NO_SHOW,
        ] as $status) {
            self::assertFalse(
                BookingStatus::customerMayCancel($status),
                $status . ' must not be customer-cancellable'
            );
        }
    }

    public function testEveryStatusHasALabelAndTone(): void
    {
        foreach (BookingStatus::all() as $status) {
            self::assertNotSame('', BookingStatus::label($status));
            self::assertContains(
                BookingStatus::tone($status),
                ['neutral', 'success', 'warning', 'danger', 'info']
            );
        }
    }

    public function testPaymentStatuses(): void
    {
        self::assertSame(['unpaid', 'paid', 'refunded'], BookingStatus::paymentStatuses());
        self::assertSame('Paid', BookingStatus::paymentLabel(BookingStatus::PAYMENT_PAID));
    }
}
