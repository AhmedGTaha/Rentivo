<?php

declare(strict_types=1);

namespace Rentivo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rentivo\Http\Response;
use Rentivo\Security\Csrf;
use Rentivo\Security\Permissions;
use Rentivo\Support\Str;

/**
 * CSRF tokens, output escaping, redirect safety and the permission catalogue.
 */
final class SecurityPrimitivesTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    // -----------------------------------------------------------------
    // CSRF
    // -----------------------------------------------------------------

    public function testTokenIsStableWithinASession(): void
    {
        $first = Csrf::token();

        self::assertSame($first, Csrf::token());
        self::assertSame(64, strlen($first));
    }

    public function testValidTokenIsAccepted(): void
    {
        self::assertTrue(Csrf::isValid(Csrf::token()));
    }

    public function testInvalidTokensAreRejected(): void
    {
        Csrf::token();

        self::assertFalse(Csrf::isValid(null));
        self::assertFalse(Csrf::isValid(''));
        self::assertFalse(Csrf::isValid('wrong'));
        self::assertFalse(Csrf::isValid(str_repeat('a', 64)));
    }

    public function testValidationFailsWhenNoTokenWasIssued(): void
    {
        self::assertFalse(Csrf::isValid(str_repeat('a', 64)));
    }

    /** Rotating after login stops a pre-auth token from being replayed. */
    public function testRotationInvalidatesThePreviousToken(): void
    {
        $original = Csrf::token();
        $rotated = Csrf::rotate();

        self::assertNotSame($original, $rotated);
        self::assertFalse(Csrf::isValid($original));
        self::assertTrue(Csrf::isValid($rotated));
    }

    public function testFieldRendersAnEscapedHiddenInput(): void
    {
        $field = Csrf::field();

        self::assertStringContainsString('type="hidden"', $field);
        self::assertStringContainsString('name="' . Csrf::FIELD . '"', $field);
        self::assertStringContainsString(Csrf::token(), $field);
    }

    // -----------------------------------------------------------------
    // Output escaping
    // -----------------------------------------------------------------

    public function testEscapesHtmlControlCharacters(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            Str::escape('<script>alert(1)</script>')
        );

        self::assertSame('&quot;quoted&quot;', Str::escape('"quoted"'));
        self::assertSame('&#039;single&#039;', Str::escape("'single'"));
        self::assertSame('a &amp; b', Str::escape('a & b'));
    }

    public function testEscapeHandlesNonStrings(): void
    {
        self::assertSame('', Str::escape(null));
        self::assertSame('42', Str::escape(42));
        self::assertSame('1', Str::escape(true));
        self::assertSame('0', Str::escape(false));
    }

    public function testEscapePreservesValidUtf8(): void
    {
        self::assertSame('Manama Motors — الرفاع', Str::escape('Manama Motors — الرفاع'));
    }

    // -----------------------------------------------------------------
    // Redirect safety
    // -----------------------------------------------------------------

    public function testInAppPathsAreAccepted(): void
    {
        self::assertSame('/account', Response::safeLocation('/account'));
        self::assertSame('/cars/toyota?x=1', Response::safeLocation('/cars/toyota?x=1'));
    }

    /**
     * Anything that could send a visitor to another origin collapses to the
     * homepage rather than becoming an open redirect.
     */
    public function testOffSiteRedirectsAreRefused(): void
    {
        foreach ([
            'https://evil.example/steal',
            '//evil.example/steal',
            'http://evil.example',
            'javascript:alert(1)',
            '',
            'account',
        ] as $candidate) {
            self::assertSame('/', Response::safeLocation($candidate), $candidate . ' must not be followed');
        }
    }

    public function testHeaderInjectionIsRefused(): void
    {
        self::assertSame('/', Response::safeLocation("/account\r\nSet-Cookie: a=b"));
        self::assertSame('/', Response::safeLocation("/account\nLocation: https://evil.example"));
    }

    // -----------------------------------------------------------------
    // Permission catalogue
    // -----------------------------------------------------------------

    /** The exact 19 keys the SRS requires. */
    public function testCatalogueMatchesTheSpecification(): void
    {
        $expected = [
            'cars.view', 'cars.create', 'cars.edit', 'cars.archive', 'cars.manage_images',
            'bookings.view', 'bookings.confirm', 'bookings.reject', 'bookings.cancel',
            'bookings.checkout', 'bookings.complete_return', 'bookings.manage_payment',
            'customers.view', 'customers.edit_notes',
            'documents.view', 'documents.verify',
            'locations.view', 'locations.manage',
            'reports.view',
        ];

        $actual = Permissions::all();

        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual);
        self::assertCount(19, Permissions::all());
    }

    /** Unknown keys submitted by a tampered form are dropped, not granted. */
    public function testFilterValidDiscardsUnknownKeys(): void
    {
        $filtered = Permissions::filterValid([
            'cars.view',
            'organization.delete',
            'bookings.confirm',
            '*',
            'admin',
            'cars.view',
        ]);

        self::assertSame(['cars.view', 'bookings.confirm'], $filtered);
    }

    public function testEveryPermissionHasALabel(): void
    {
        foreach (Permissions::all() as $key) {
            self::assertNotSame($key, Permissions::label($key), $key . ' should have a human label');
        }
    }
}
