<?php

declare(strict_types=1);

namespace Rentivo\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rentivo\Support\Currency;

/**
 * Money is integer fils everywhere. These tests pin the conversion and
 * formatting rules that the whole pricing chain depends on.
 */
final class CurrencyTest extends TestCase
{
    #[DataProvider('amountProvider')]
    public function testFormatsFilsWithThreeDecimals(int $fils, string $expected): void
    {
        self::assertSame($expected, Currency::amount($fils));
    }

    /** @return array<string, array{0:int, 1:string}> */
    public static function amountProvider(): array
    {
        return [
            'whole dinar'      => [25000, '25.000'],
            'srs example'      => [25500, '25.500'],
            'half dinar'       => [500, '0.500'],
            'single fil'       => [1, '0.001'],
            'zero'             => [0, '0.000'],
            'thousands'        => [1234567, '1,234.567'],
            'negative refund'  => [-25500, '-25.500'],
        ];
    }

    public function testFormatIncludesCurrencyCode(): void
    {
        self::assertSame('BHD 25.500', Currency::format(25500));
    }

    #[DataProvider('parseProvider')]
    public function testParsesHumanInputToFils(string $input, int $expected): void
    {
        self::assertSame($expected, Currency::toFils($input));
    }

    /** @return array<string, array{0:string, 1:int}> */
    public static function parseProvider(): array
    {
        return [
            'three decimals'  => ['25.500', 25500],
            'one decimal'     => ['25.5', 25500],
            'two decimals'    => ['25.50', 25500],
            'no decimals'     => ['25', 25000],
            'zero'            => ['0', 0],
            'sub-dinar'       => ['0.001', 1],
            'thousand comma'  => ['1,234.567', 1234567],
            'padded'          => ['  12.250  ', 12250],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidAmounts(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Currency::toFils($input);
    }

    /** @return array<string, array{0:string}> */
    public static function invalidProvider(): array
    {
        return [
            'empty'          => [''],
            'letters'        => ['abc'],
            'too precise'    => ['1.2345'],
            'mixed'          => ['12.5x'],
            'two dots'       => ['1.2.3'],
        ];
    }

    public function testTryToFilsReturnsNullInsteadOfThrowing(): void
    {
        self::assertNull(Currency::tryToFils('nonsense'));
        self::assertNull(Currency::tryToFils(null));
        self::assertNull(Currency::tryToFils('   '));
        self::assertSame(25500, Currency::tryToFils('25.5'));
    }

    /**
     * Round-tripping must be lossless: a stored value rendered into a form and
     * submitted back has to produce the identical integer.
     */
    public function testInputRoundTripIsLossless(): void
    {
        foreach ([0, 1, 500, 12000, 25500, 98750, 1234567] as $fils) {
            self::assertSame($fils, Currency::toFils(Currency::toInput($fils)));
        }
    }
}
