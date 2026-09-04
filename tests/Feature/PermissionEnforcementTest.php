<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Http\HttpException;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\BookingException;
use Rentivo\Services\BookingService;
use Rentivo\Services\CarService;
use Rentivo\Services\EmployeeException;
use Rentivo\Services\EmployeeService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Test 4: employee permissions.
 *
 * An employee holding cars.view but not cars.edit can list cars and is
 * refused, server-side, when they attempt to edit one.
 */
final class PermissionEnforcementTest extends TestCase
{
    private array $organization;
    private int $adminId;
    private int $viewerId;
    private array $car;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminId = $this->createUser('admin@example.test', 'Admin');
        $this->viewerId = $this->createUser('viewer@example.test', 'Viewer');

        $this->organization = $this->createOrganization('Permissions Agency', $this->adminId);

        // Deliberately narrow: view only, no edit.
        $this->addEmployee((int) $this->organization['id'], $this->viewerId, [
            Permissions::CARS_VIEW,
            Permissions::BOOKINGS_VIEW,
        ]);

        $this->car = $this->createCar((int) $this->organization['id']);
    }

    private function viewerContext(): OrganizationContext
    {
        $this->actingAs($this->viewerId);

        return $this->contextFor((string) $this->organization['slug']);
    }

    private function adminContext(): OrganizationContext
    {
        $this->actingAs($this->adminId);

        return $this->contextFor((string) $this->organization['slug']);
    }

    public function testAdminAuthorityIsImplicitAndComplete(): void
    {
        $context = $this->adminContext();

        self::assertTrue($context->isAdmin());

        foreach (Permissions::all() as $permission) {
            self::assertTrue($context->can($permission), 'Admin should hold ' . $permission);
        }

        // An admin has no permission rows; the authority is implicit.
        $rows = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM employee_permissions ep
             INNER JOIN organization_users ou ON ou.id = ep.organization_user_id
             WHERE ou.user_id = ? AND ou.organization_id = ?',
            [$this->adminId, (int) $this->organization['id']]
        );

        self::assertSame(0, $rows);
    }

    public function testEmployeeHoldsOnlyTheGrantedPermissions(): void
    {
        $context = $this->viewerContext();

        self::assertFalse($context->isAdmin());
        self::assertTrue($context->can(Permissions::CARS_VIEW));
        self::assertTrue($context->can(Permissions::BOOKINGS_VIEW));

        self::assertFalse($context->can(Permissions::CARS_EDIT));
        self::assertFalse($context->can(Permissions::CARS_CREATE));
        self::assertFalse($context->can(Permissions::CARS_ARCHIVE));
        self::assertFalse($context->can(Permissions::BOOKINGS_CONFIRM));
        self::assertFalse($context->can(Permissions::REPORTS_VIEW));
    }

    /** SRS Test 4: the edit attempt is denied by the server, not the UI. */
    public function testEmployeeWithoutEditCannotEditACar(): void
    {
        $context = $this->viewerContext();

        /** @var CarService $cars */
        $cars = $this->app->get(CarService::class);

        try {
            $cars->update($context, (int) $this->car['id'], [
                'brand' => 'Changed',
                'model' => 'Changed',
                'year'  => 2024,
            ]);
            self::fail('An employee without cars.edit edited a car.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status());
        }

        // The car is unchanged.
        $reloaded = $cars->cars()->findInOrganization((int) $this->car['id'], (int) $this->organization['id']);
        self::assertSame('Toyota', $reloaded['brand']);
    }

    public function testEmployeeWithoutCreateCannotAddACar(): void
    {
        $context = $this->viewerContext();

        $this->expectException(HttpException::class);

        $this->app->get(CarService::class)->create($context, [
            'brand'           => 'Sneaky',
            'model'           => 'Car',
            'year'            => 2024,
            'daily_rate_fils' => 10000,
            'transmission'    => 'automatic',
            'fuel_type'       => 'petrol',
            'seats'           => 5,
            'doors'           => 4,
            'status'          => 'available',
        ]);
    }

    public function testEmployeeWithoutArchiveCannotArchiveACar(): void
    {
        $context = $this->viewerContext();

        $this->expectException(HttpException::class);

        $this->app->get(CarService::class)->archive($context, (int) $this->car['id']);
    }

    public function testEmployeeWithoutConfirmCannotConfirmABooking(): void
    {
        $customerId = $this->createUserWithPhone('customer@example.test');

        /** @var BookingService $bookings */
        $bookings = $this->app->get(BookingService::class);

        $booking = $bookings->createForCustomer(
            $this->car,
            $customerId,
            DateTimeHelper::now()->modify('+2 days'),
            DateTimeHelper::now()->modify('+4 days'),
            null,
            null
        );

        $context = $this->viewerContext();

        try {
            $bookings->confirm($context, (string) $booking['reference']);
            self::fail('An employee without bookings.confirm confirmed a booking.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status());
        }
    }

    public function testEmployeeWithoutReportsCannotRunReports(): void
    {
        $context = $this->viewerContext();

        $this->expectException(HttpException::class);

        $this->app->get(\Rentivo\Services\ReportService::class)->organizationReport($context);
    }

    // -----------------------------------------------------------------
    // Admin-only actions (SRS §14)
    // -----------------------------------------------------------------

    /** Employees can never change permissions — not even their own. */
    public function testEmployeeCannotChangePermissions(): void
    {
        $context = $this->viewerContext();

        /** @var EmployeeService $employees */
        $employees = $this->app->get(EmployeeService::class);

        $membership = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->findMembership((int) $this->organization['id'], $this->viewerId);

        try {
            $employees->updatePermissions($context, (int) $membership['id'], Permissions::all());
            self::fail('An employee escalated their own permissions.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status());
        }

        // Their permission set is untouched.
        $this->actingAs($this->viewerId);
        $fresh = $this->contextFor((string) $this->organization['slug']);
        self::assertFalse($fresh->can(Permissions::CARS_EDIT));
    }

    public function testEmployeeCannotInviteOtherEmployees(): void
    {
        $context = $this->viewerContext();

        $this->expectException(HttpException::class);

        $this->app->get(EmployeeService::class)->invite($context, 'someone@example.test', []);
    }

    public function testEmployeeCannotRemoveMembers(): void
    {
        $context = $this->viewerContext();

        $membership = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->findMembership((int) $this->organization['id'], $this->adminId);

        $this->expectException(HttpException::class);

        $this->app->get(EmployeeService::class)->removeMember($context, (int) $membership['id']);
    }

    public function testEmployeeCannotChangeOrganizationSettings(): void
    {
        $context = $this->viewerContext();

        $this->expectException(HttpException::class);

        $context->authorizeAdmin();
    }

    // -----------------------------------------------------------------
    // Admin safeguards
    // -----------------------------------------------------------------

    public function testAdminCannotRemoveThemselves(): void
    {
        $context = $this->adminContext();

        $membership = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->findMembership((int) $this->organization['id'], $this->adminId);

        $this->expectException(EmployeeException::class);

        $this->app->get(EmployeeService::class)->removeMember($context, (int) $membership['id']);
    }

    public function testAdminCanGrantAndRevokePermissions(): void
    {
        $context = $this->adminContext();

        $membership = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->findMembership((int) $this->organization['id'], $this->viewerId);

        /** @var EmployeeService $employees */
        $employees = $this->app->get(EmployeeService::class);

        $employees->updatePermissions($context, (int) $membership['id'], [
            Permissions::CARS_VIEW,
            Permissions::CARS_EDIT,
        ]);

        $this->actingAs($this->viewerId);
        $granted = $this->contextFor((string) $this->organization['slug']);
        self::assertTrue($granted->can(Permissions::CARS_EDIT));

        // Revoking takes effect on the next resolved context.
        $this->actingAs($this->adminId);
        $employees->updatePermissions(
            $this->contextFor((string) $this->organization['slug']),
            (int) $membership['id'],
            [Permissions::CARS_VIEW]
        );

        $this->actingAs($this->viewerId);
        $revoked = $this->contextFor((string) $this->organization['slug']);
        self::assertFalse($revoked->can(Permissions::CARS_EDIT));
    }

    /** Unknown keys are filtered out rather than stored. */
    public function testUnknownPermissionKeysAreNeverGranted(): void
    {
        $context = $this->adminContext();

        $membership = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->findMembership((int) $this->organization['id'], $this->viewerId);

        $this->app->get(EmployeeService::class)->updatePermissions($context, (int) $membership['id'], [
            'cars.view',
            'organization.delete',
            '*',
        ]);

        $this->actingAs($this->viewerId);
        $fresh = $this->contextFor((string) $this->organization['slug']);

        self::assertSame(['cars.view'], $fresh->permissions());
    }

    public function testAdminsCannotBeGivenExplicitPermissionRows(): void
    {
        $context = $this->adminContext();

        $membership = $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->findMembership((int) $this->organization['id'], $this->adminId);

        $this->expectException(EmployeeException::class);

        $this->app->get(EmployeeService::class)->updatePermissions(
            $context,
            (int) $membership['id'],
            [Permissions::CARS_VIEW]
        );
    }
}
