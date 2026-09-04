<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Http\Kernel;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Security\Csrf;
use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Tests 1, 2 and 12, exercised through the real HTTP
 * kernel: anonymous browsing, authentication only at booking, and CSRF.
 */
final class HttpKernelTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->kernel = new Kernel($this->app);
    }

    /** @param array<string,mixed> $query */
    private function get(string $path, array $query = []): Response
    {
        return $this->kernel->handle(new Request('GET', Request::normalisePath($path), $query));
    }

    /** @param array<string,mixed> $body */
    private function post(string $path, array $body = []): Response
    {
        return $this->kernel->handle(new Request('POST', Request::normalisePath($path), [], $body));
    }

    /** Adds a valid CSRF token to a request body. */
    private function withToken(array $body = []): array
    {
        return $body + [Csrf::FIELD => Csrf::token()];
    }

    // -----------------------------------------------------------------
    // SRS Test 1: anonymous browsing
    // -----------------------------------------------------------------

    public function testPublicPagesAreReachableWithoutAnAccount(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Public Agency', $admin);
        $car = $this->createCar((int) $organization['id']);

        foreach ([
            '/',
            '/cars',
            '/agencies',
            '/login',
            '/agency/' . $organization['slug'],
            '/agency/' . $organization['slug'] . '/cars',
            '/cars/' . $car['slug'],
        ] as $path) {
            $response = $this->get($path);

            self::assertSame(200, $response->status(), $path . ' should be public');
            self::assertNotSame('', $response->content());
        }
    }

    public function testFilteringAndSortingWorkAnonymously(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Filter Agency', $admin);

        $this->createCar((int) $organization['id'], [
            'brand' => 'Toyota', 'model' => 'Corolla', 'daily_rate_fils' => 12000,
        ]);
        $this->createCar((int) $organization['id'], [
            'brand' => 'Nissan', 'model' => 'Patrol', 'daily_rate_fils' => 40000, 'transmission' => 'manual',
        ]);

        $cheap = $this->get('/cars', ['max_price' => '20']);
        self::assertSame(200, $cheap->status());
        self::assertStringContainsString('Corolla', $cheap->content());
        self::assertStringNotContainsString('Patrol', $cheap->content());

        $manual = $this->get('/cars', ['transmission' => 'manual']);
        self::assertStringContainsString('Patrol', $manual->content());
        self::assertStringNotContainsString('Corolla', $manual->content());

        $search = $this->get('/cars', ['q' => 'Toyota']);
        self::assertStringContainsString('Corolla', $search->content());

        self::assertSame(200, $this->get('/cars', ['sort' => 'price_asc'])->status());
        self::assertSame(200, $this->get('/cars', ['sort' => 'popular'])->status());
    }

    public function testUnknownPathReturns404(): void
    {
        $response = $this->get('/no-such-page');

        self::assertSame(404, $response->status());
        self::assertStringContainsString('not found', strtolower($response->content()));
    }

    public function testUnlistedCarReturns404(): void
    {
        self::assertSame(404, $this->get('/cars/does-not-exist')->status());
        self::assertSame(404, $this->get('/agency/does-not-exist')->status());
    }

    /** An inactive organization disappears from the public marketplace. */
    public function testInactiveOrganizationIsNotPubliclyReachable(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Hidden Agency', $admin);
        $car = $this->createCar((int) $organization['id']);

        self::assertSame(200, $this->get('/agency/' . $organization['slug'])->status());

        $this->db()->update('organizations', ['is_active' => 0], ['id' => (int) $organization['id']]);

        self::assertSame(404, $this->get('/agency/' . $organization['slug'])->status());
        self::assertSame(404, $this->get('/cars/' . $car['slug'])->status());
    }

    // -----------------------------------------------------------------
    // SRS Test 12: CSRF
    // -----------------------------------------------------------------

    public function testStateChangingRequestWithoutATokenIsRejected(): void
    {
        $response = $this->post('/logout');

        self::assertSame(403, $response->status());
    }

    public function testStateChangingRequestWithAWrongTokenIsRejected(): void
    {
        Csrf::token();

        $response = $this->post('/logout', [Csrf::FIELD => str_repeat('a', 64)]);

        self::assertSame(403, $response->status());
    }

    public function testStateChangingRequestWithAValidTokenPasses(): void
    {
        $response = $this->post('/logout', $this->withToken());

        // 302 back to the homepage: the CSRF gate let it through.
        self::assertSame(302, $response->status());
        self::assertSame('/', $response->header('Location'));
    }

    /** Every POST route is gated, not just the ones a form happens to use. */
    public function testCsrfAppliesToEveryStateChangingRoute(): void
    {
        foreach ([
            '/logout',
            '/organizations',
            '/favorites/some-car/toggle',
            '/account/documents',
            '/account/notifications/read-all',
        ] as $path) {
            self::assertSame(403, $this->post($path)->status(), $path . ' must require CSRF');
        }
    }

    public function testGetRequestsDoNotRequireAToken(): void
    {
        self::assertSame(200, $this->get('/cars')->status());
    }

    // -----------------------------------------------------------------
    // SRS Test 2: authentication only at booking
    // -----------------------------------------------------------------

    /**
     * A guest pressing "Book" is sent to sign in, and their whole selection is
     * preserved so they return to checkout rather than a generic dashboard.
     */
    public function testGuestBookingAttemptPreservesTheSelection(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Booking Agency', $admin);
        $car = $this->createCar((int) $organization['id']);

        $pickup = gmdate('Y-m-d\TH:i', time() + 172800);
        $return = gmdate('Y-m-d\TH:i', time() + 345600);

        $response = $this->get('/cars/' . $car['slug'] . '/book', [
            'pickup_at' => $pickup,
            'return_at' => $return,
        ]);

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->header('Location'));

        // The selection survives in the session, tied to this car.
        $stored = $_SESSION['_intended_booking'] ?? null;
        self::assertIsArray($stored);
        self::assertSame($car['slug'], $stored['car_slug']);
        self::assertSame($pickup, $stored['pickup_at']);
        self::assertSame($return, $stored['return_at']);

        // And the intended URL points back at checkout, not the dashboard.
        $intended = $_SESSION['_intended_url'] ?? '';
        self::assertStringStartsWith('/cars/' . $car['slug'] . '/book', $intended);
    }

    /**
     * The second half of SRS Test 2: after signing in, the visitor lands back
     * on checkout with their selection intact and can submit the booking.
     */
    public function testSignedInVisitorCompletesTheBookingTheyStarted(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Round Trip Agency', $admin);
        $car = $this->createCar((int) $organization['id'], ['daily_rate_fils' => 20000]);

        $pickup = gmdate('Y-m-d\TH:i', time() + 172800);
        $return = gmdate('Y-m-d\TH:i', time() + 345600);

        // 1. A guest presses "Book" and is sent to sign in.
        $this->get('/cars/' . $car['slug'] . '/book', [
            'pickup_at' => $pickup,
            'return_at' => $return,
        ]);

        $intended = $_SESSION['_intended_url'] ?? '';
        self::assertStringStartsWith('/cars/' . $car['slug'] . '/book', $intended);

        // 2. They authenticate. The session keeps the stored selection.
        $customerId = $this->createUserWithPhone('customer@example.test', 'Customer');
        $this->actingAs($customerId);

        // 3. They return to the intended URL: the checkout page, pre-filled.
        $checkout = $this->kernel->handle(
            new Request('GET', Request::normalisePath(parse_url($intended, PHP_URL_PATH)),
                $this->queryFrom($intended))
        );

        self::assertSame(200, $checkout->status());
        self::assertStringContainsString('Complete your booking', $checkout->content());
        self::assertStringContainsString($pickup, $checkout->content(), 'The chosen dates should be pre-filled.');

        // 4. They submit, and a pending booking is created.
        $submit = $this->post('/cars/' . $car['slug'] . '/book', $this->withToken([
            'pickup_at' => $pickup,
            'return_at' => $return,
        ]));

        self::assertSame(302, $submit->status());

        $location = (string) $submit->header('Location');
        self::assertStringStartsWith('/account/bookings/BK-', $location);

        $reference = basename($location);
        $booking = $this->app->get(\Rentivo\Repositories\BookingRepository::class)
            ->findForUser($reference, $customerId);

        self::assertNotNull($booking);
        self::assertSame('pending', $booking['status']);
        self::assertSame(2, (int) $booking['rental_days']);
        self::assertSame(40000, (int) $booking['total_fils']);

        // 5. The stored selection has been consumed.
        self::assertArrayNotHasKey('_intended_booking', $_SESSION);

        // 6. The booking is visible on their account.
        self::assertSame(200, $this->get($location)->status());
    }

    /** @return array<string,mixed> */
    private function queryFrom(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /** A booking cannot be submitted without a phone number. */
    public function testBookingIsBlockedUntilAPhoneNumberIsAdded(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Phone Agency', $admin);
        $car = $this->createCar((int) $organization['id']);

        // Created without a phone number.
        $customerId = $this->createUser('nophone@example.test', 'No Phone');
        $this->actingAs($customerId);

        $response = $this->post('/cars/' . $car['slug'] . '/book', $this->withToken([
            'pickup_at' => gmdate('Y-m-d\TH:i', time() + 172800),
            'return_at' => gmdate('Y-m-d\TH:i', time() + 345600),
        ]));

        self::assertSame(302, $response->status());
        self::assertStringStartsWith('/account/profile', (string) $response->header('Location'));

        self::assertSame(
            0,
            (int) $this->db()->scalar('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$customerId])
        );
    }

    /** Account pages redirect a guest to sign in rather than erroring. */
    public function testAccountPagesRedirectGuestsToLogin(): void
    {
        foreach ([
            '/account',
            '/account/bookings',
            '/account/favorites',
            '/account/documents',
            '/account/notifications',
            '/account/profile',
            '/organizations/create',
        ] as $path) {
            $response = $this->get($path);

            self::assertSame(302, $response->status(), $path . ' should redirect a guest');
            self::assertSame('/login', $response->header('Location'));
        }
    }

    public function testSignedInCustomerReachesTheirAccount(): void
    {
        $userId = $this->createUserWithPhone('customer@example.test', 'Customer');
        $this->actingAs($userId);

        $response = $this->get('/account');

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Customer', $response->content());
    }

    // -----------------------------------------------------------------
    // Management access
    // -----------------------------------------------------------------

    public function testGuestCannotReachManagement(): void
    {
        $admin = $this->createUser('admin@example.test');
        $organization = $this->createOrganization('Managed Agency', $admin);

        $response = $this->get('/manage/' . $organization['slug']);

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->header('Location'));
    }

    /** A signed-in non-member gets 404, not a management page. */
    public function testNonMemberCannotReachAnotherOrganizationsManagement(): void
    {
        $adminA = $this->createUser('admin.a@example.test');
        $adminB = $this->createUser('admin.b@example.test');

        $orgB = $this->createOrganization('Beta Agency', $adminB);

        $this->actingAs($adminA);

        self::assertSame(404, $this->get('/manage/' . $orgB['slug'])->status());
        self::assertSame(404, $this->get('/manage/' . $orgB['slug'] . '/cars')->status());
        self::assertSame(404, $this->get('/manage/' . $orgB['slug'] . '/bookings')->status());
        self::assertSame(404, $this->get('/manage/' . $orgB['slug'] . '/employees')->status());
        self::assertSame(404, $this->get('/manage/' . $orgB['slug'] . '/reports')->status());
        self::assertSame(404, $this->get('/manage/' . $orgB['slug'] . '/settings')->status());
    }

    public function testAdminReachesTheirOwnManagementPages(): void
    {
        $admin = $this->createUser('admin@example.test', 'Owner');
        $organization = $this->createOrganization('Owned Agency', $admin);

        $this->actingAs($admin);

        foreach ([
            '',
            '/cars',
            '/cars/create',
            '/categories',
            '/locations',
            '/bookings',
            '/customers',
            '/documents',
            '/employees',
            '/employees/invite',
            '/reports',
            '/activity',
            '/settings',
        ] as $suffix) {
            $response = $this->get('/manage/' . $organization['slug'] . $suffix);

            self::assertSame(200, $response->status(), $suffix . ' should render for the admin');
        }
    }

    /** The dev gallery is only reachable outside production. */
    public function testComponentGalleryIsAvailableInDevelopment(): void
    {
        self::assertSame(200, $this->get('/dev/components')->status());
    }

    /** Responses carry the defensive headers regardless of route. */
    public function testHtmlResponsesDeclareTheirContentType(): void
    {
        $response = $this->get('/cars');

        self::assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
    }
}
