<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Http\Kernel;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Security\Csrf;
use Rentivo\Security\Permissions;
use Rentivo\Services\BookingStatus;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Tests\TestCase;

/**
 * Every management route, driven through the real kernel, against a user who
 * lacks the required permission.
 *
 * This is the check that catches an action whose guard lives only in the view:
 * the request is made directly, with a valid CSRF token, so nothing but the
 * server-side authorization can stop it.
 */
final class ManagementRouteGuardTest extends TestCase
{
    private Kernel $kernel;
    private array $organization;
    private int $adminId;
    private int $employeeId;
    private array $car;
    private string $reference;
    private int $categoryId;
    private int $locationId;
    private int $membershipId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->kernel = new Kernel($this->app);

        $this->adminId = $this->createUser('admin@example.test', 'Admin');
        $this->employeeId = $this->createUser('employee@example.test', 'Employee');
        $customerId = $this->createUserWithPhone('customer@example.test', 'Customer');

        $this->organization = $this->createOrganization('Guarded Agency', $this->adminId);
        $this->car = $this->createCar((int) $this->organization['id']);

        // The employee can see the console but may change nothing.
        $this->membershipId = $this->addEmployee(
            (int) $this->organization['id'],
            $this->employeeId,
            [Permissions::CARS_VIEW, Permissions::BOOKINGS_VIEW, Permissions::CUSTOMERS_VIEW]
        );

        $this->categoryId = $this->app->get(\Rentivo\Repositories\CategoryRepository::class)
            ->create((int) $this->organization['id'], 'Economy', 'economy');

        $this->locationId = $this->app->get(\Rentivo\Repositories\LocationRepository::class)
            ->create((int) $this->organization['id'], [
                'name' => 'Depot', 'address' => 'Somewhere', 'is_active' => 1,
            ]);

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);
        $this->reference = $bookings->nextReference();

        $bookings->create([
            'reference'                => $this->reference,
            'organization_id'          => (int) $this->organization['id'],
            'user_id'                  => $customerId,
            'car_id'                   => (int) $this->car['id'],
            'pickup_at'                => DateTimeHelper::toDb(DateTimeHelper::now()->modify('+2 days')),
            'return_at'                => DateTimeHelper::toDb(DateTimeHelper::now()->modify('+4 days')),
            'rental_days'              => 2,
            'daily_rate_snapshot_fils' => 20000,
            'subtotal_fils'            => 40000,
            'total_fils'               => 40000,
            'status'                   => BookingStatus::PENDING,
        ]);
    }

    private function post(string $path, array $body = []): Response
    {
        return $this->kernel->handle(new Request(
            'POST',
            Request::normalisePath($path),
            [],
            $body + [Csrf::FIELD => Csrf::token()]
        ));
    }

    private function get(string $path): Response
    {
        return $this->kernel->handle(new Request('GET', Request::normalisePath($path)));
    }

    private function base(): string
    {
        return '/manage/' . $this->organization['slug'];
    }

    /**
     * Every state-changing management route the restricted employee must not
     * be able to use.
     *
     * @return array<string, array{0:string, 1:array<string,mixed>}>
     */
    private function guardedPostRoutes(): array
    {
        $base = $this->base();
        $carId = (int) $this->car['id'];
        $reference = rawurlencode($this->reference);

        return [
            'create car'        => [$base . '/cars', ['brand' => 'X', 'model' => 'Y', 'year' => '2024']],
            'update car'        => [$base . '/cars/' . $carId, ['brand' => 'X', 'model' => 'Y', 'year' => '2024']],
            'change car status' => [$base . '/cars/' . $carId . '/status', ['status' => 'maintenance']],
            'archive car'       => [$base . '/cars/' . $carId . '/archive', []],
            'restore car'       => [$base . '/cars/' . $carId . '/restore', []],
            'upload images'     => [$base . '/cars/' . $carId . '/images', []],
            'reorder images'    => [$base . '/cars/' . $carId . '/images/reorder', []],

            'create category'   => [$base . '/categories', ['name' => 'Sneaky']],
            'update category'   => [$base . '/categories/' . $this->categoryId, ['name' => 'Sneaky']],
            'delete category'   => [$base . '/categories/' . $this->categoryId . '/delete', []],

            'create location'   => [$base . '/locations', ['name' => 'X', 'address' => 'Y']],
            'update location'   => [$base . '/locations/' . $this->locationId, ['name' => 'X', 'address' => 'Y']],

            'confirm booking'   => [$base . '/bookings/' . $reference . '/confirm', []],
            'reject booking'    => [$base . '/bookings/' . $reference . '/reject', []],
            'cancel booking'    => [$base . '/bookings/' . $reference . '/cancel', []],
            'ready for pickup'  => [$base . '/bookings/' . $reference . '/ready', []],
            'mark no-show'      => [$base . '/bookings/' . $reference . '/no-show', []],
            'change payment'    => [$base . '/bookings/' . $reference . '/payment', ['payment_status' => 'paid']],

            'edit customer notes' => [$base . '/customers/1/notes', ['internal_notes' => 'x']],

            'invite employee'   => [$base . '/employees/invite', ['email' => 'x@example.test']],
            'change permissions' => [$base . '/employees/' . $this->membershipId . '/permissions', []],
            'remove employee'   => [$base . '/employees/' . $this->membershipId . '/remove', []],
            'revoke invitation' => [$base . '/employees/invitations/1/revoke', []],

            'change settings'   => [$base . '/settings', ['name' => 'Hijacked']],
        ];
    }

    /**
     * The employee holds cars.view, bookings.view and customers.view and
     * nothing else, so every mutating route must refuse them.
     */
    public function testRestrictedEmployeeCannotReachAnyMutatingRoute(): void
    {
        $this->actingAs($this->employeeId);

        foreach ($this->guardedPostRoutes() as $label => [$path, $body]) {
            $response = $this->post($path, $body);

            self::assertContains(
                $response->status(),
                [403, 404],
                sprintf('"%s" (%s) returned %d; it must be refused.', $label, $path, $response->status())
            );
        }
    }

    /** Nothing was mutated as a side effect of those attempts. */
    public function testNothingWasChangedByTheRefusedRequests(): void
    {
        $this->actingAs($this->employeeId);

        foreach ($this->guardedPostRoutes() as [$path, $body]) {
            $this->post($path, $body);
        }

        $car = $this->app->get(\Rentivo\Repositories\CarRepository::class)
            ->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);

        self::assertSame('Toyota', $car['brand']);
        self::assertSame('available', $car['status']);
        self::assertNull($car['archived_at']);

        $booking = $this->app->get(BookingRepository::class)
            ->findInOrganization($this->reference, (int) $this->organization['id']);

        self::assertSame(BookingStatus::PENDING, $booking['status']);
        self::assertSame('unpaid', $booking['payment_status']);

        $organization = $this->app->get(\Rentivo\Repositories\OrganizationRepository::class)
            ->find((int) $this->organization['id']);

        self::assertSame('Guarded Agency', $organization['name']);

        // The employee's own permissions are unchanged.
        $permissions = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->permissionKeysFor($this->membershipId);

        sort($permissions);
        self::assertSame(['bookings.view', 'cars.view', 'customers.view'], $permissions);
    }

    /** Read-only pages the employee is allowed to open still work. */
    public function testPermittedPagesRemainReachable(): void
    {
        $this->actingAs($this->employeeId);

        foreach (['', '/cars', '/bookings', '/customers'] as $suffix) {
            self::assertSame(
                200,
                $this->get($this->base() . $suffix)->status(),
                $suffix . ' should be readable with view permissions'
            );
        }
    }

    /** Admin-only areas are refused even to a permissioned employee. */
    public function testAdminOnlyPagesAreRefusedToEmployees(): void
    {
        $this->actingAs($this->employeeId);

        foreach (['/employees', '/employees/invite', '/settings'] as $suffix) {
            self::assertSame(
                403,
                $this->get($this->base() . $suffix)->status(),
                $suffix . ' must be admin-only'
            );
        }
    }

    /** Pages needing a permission the employee lacks are refused. */
    public function testPagesRequiringMissingPermissionsAreRefused(): void
    {
        $this->actingAs($this->employeeId);

        foreach (['/reports', '/activity', '/documents'] as $suffix) {
            self::assertSame(
                403,
                $this->get($this->base() . $suffix)->status(),
                $suffix . ' should require a permission the employee lacks'
            );
        }
    }

    /** The same routes all succeed for the admin, proving they are reachable. */
    public function testAdminCanUseTheRoutesTheEmployeeCannot(): void
    {
        $this->actingAs($this->adminId);

        // A representative mutating action: confirming the booking.
        $response = $this->post($this->base() . '/bookings/' . rawurlencode($this->reference) . '/confirm');

        self::assertSame(302, $response->status());

        $booking = $this->app->get(BookingRepository::class)
            ->findInOrganization($this->reference, (int) $this->organization['id']);

        self::assertSame(BookingStatus::CONFIRMED, $booking['status']);
    }
}
