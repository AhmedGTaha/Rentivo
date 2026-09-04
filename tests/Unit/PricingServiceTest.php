<?php

declare(strict_types=1);

namespace Rentivo\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rentivo\Services\PricingService;

/**
 * Rental day counting and pricing.
 *
 * SRS §34: rental_days = max(1, ceil((return_at - pickup_at) / 24 hours)).
 */
final class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        $this->pricing = new PricingService();
    }

    #[DataProvider('durationProvider')]
    public function testRentalDayCalculation(string $pickup, string $return, int $expectedDays): void
    {
        self::assertSame(
            $expectedDays,
            $this->pricing->rentalDays(
                new DateTimeImmutable($pickup, new \DateTimeZone('UTC')),
                new DateTimeImmutable($return, new \DateTimeZone('UTC'))
            )
        );
    }

    /** @return array<string, array{0:string, 1:string, 2:int}> */
    public static function durationProvider(): array
    {
        return [
            // The two worked examples from the SRS.
            'exactly 24 hours is one day'   => ['2026-09-10 10:00', '2026-09-11 10:00', 1],
            'a few hours over is two days'  => ['2026-09-10 10:00', '2026-09-11 13:00', 2],

            'one minute is still one day'   => ['2026-09-10 10:00', '2026-09-10 10:01', 1],
            'one second under a day'        => ['2026-09-10 10:00', '2026-09-11 09:59', 1],
            'one second over a day'         => ['2026-09-10 10:00', '2026-09-11 10:01', 2],
            'exactly 72 hours'              => ['2026-09-10 10:00', '2026-09-13 10:00', 3],
            'a week and an hour'            => ['2026-09-10 10:00', '2026-09-17 11:00', 8],
            'across a month boundary'       => ['2026-09-29 08:00', '2026-10-02 08:00', 3],
        ];
    }

    public function testRejectsAReturnThatIsNotAfterPickup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pricing->rentalDays(
            new DateTimeImmutable('2026-09-11 10:00'),
            new DateTimeImmutable('2026-09-10 10:00')
        );
    }

    public function testRejectsAZeroLengthPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $moment = new DateTimeImmutable('2026-09-10 10:00');

        $this->pricing->rentalDays($moment, $moment);
    }

    public function testSubtotalIsDaysTimesRate(): void
    {
        self::assertSame(60000, $this->pricing->subtotalFils(3, 20000));
        self::assertSame(0, $this->pricing->subtotalFils(0, 20000));
    }

    public function testQuoteProducesACompleteBreakdown(): void
    {
        $quote = $this->pricing->quote(
            new DateTimeImmutable('2026-09-10 10:00', new \DateTimeZone('UTC')),
            new DateTimeImmutable('2026-09-13 10:00', new \DateTimeZone('UTC')),
            25500
        );

        self::assertSame(3, $quote['rental_days']);
        self::assertSame(25500, $quote['daily_rate_fils']);
        self::assertSame(76500, $quote['subtotal_fils']);
        self::assertSame(0, $quote['additional_charges_fils']);
        self::assertSame(76500, $quote['total_fils']);

        // Everything persisted must be an integer, never a float.
        foreach ($quote as $value) {
            self::assertIsInt($value);
        }
    }

    public function testAdditionalChargesAreAddedToTheTotal(): void
    {
        $quote = $this->pricing->quote(
            new DateTimeImmutable('2026-09-10 10:00', new \DateTimeZone('UTC')),
            new DateTimeImmutable('2026-09-11 10:00', new \DateTimeZone('UTC')),
            20000,
            5500
        );

        self::assertSame(20000, $quote['subtotal_fils']);
        self::assertSame(25500, $quote['total_fils']);
    }

    /**
     * A recalculated total always reuses the stored subtotal, which is derived
     * from the rate snapshot rather than the car's current price.
     */
    public function testRecalculateUsesTheStoredSubtotal(): void
    {
        $booking = ['subtotal_fils' => 60000];

        self::assertSame(60000, $this->pricing->recalculateTotal($booking, 0));
        self::assertSame(72500, $this->pricing->recalculateTotal($booking, 12500));
        self::assertSame(60000, $this->pricing->recalculateTotal($booking, -100));
    }
}
