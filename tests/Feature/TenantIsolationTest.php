<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Http\HttpException;
use Rentivo\Repositories\BookingRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Repositories\ReportRepository;
use Rentivo\Security\Permissions;
use Rentivo\Services\CarException;
use Rentivo\Services\CarService;
use Rentivo\Support\Pagination;
use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Test 3: organization isolation.
 *
 * Two organizations are created and staff of A attempt to reach B's data by
 * supplying B's ids and slugs directly. Every attempt must fail, because the
 * organization predicate is part of the query rather than a check applied
 * afterwards.
 */
final class TenantIsolationTest extends TestCase
{
    private array $orgA;
    private array $orgB;
    private int $adminA;
    private int $adminB;
    private int $employeeA;
    private array $carA;
    private array $carB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminA = $this->createUser('admin.a@example.test', 'Admin A');
        $this->adminB = $this->createUser('admin.b@example.test', 'Admin B');
        $this->employeeA = $this->createUser('employee.a@example.test', 'Employee A');

        $this->orgA = $this->createOrganization('Alpha Rentals', $this->adminA);
        $this->orgB = $this->createOrganization('Beta Motors', $this->adminB);

        $this->addEmployee((int) $this->orgA['id'], $this->employeeA, Permissions::all());

        $this->carA = $this->createCar((int) $this->orgA['id'], ['brand' => 'Toyota', 'model' => 'Alpha']);
        $this->carB = $this->createCar((int) $this->orgB['id'], ['brand' => 'Nissan', 'model' => 'Beta']);
    }

    /** A non-member cannot resolve a management context at all. */
    public function testNonMemberCannotResolveAnotherOrganizationContext(): void
    {
        $this->actingAs($this->adminA);

        $this->expectException(HttpException::class);

        $this->contextFor((string) $this->orgB['slug']);
    }

    /** Even a fully-permissioned employee of A is a stranger to B. */
    public function testFullyPermissionedEmployeeCannotReachAnotherOrganization(): void
    {
        $this->actingAs($this->employeeA);

        try {
            $this->contextFor((string) $this->orgB['slug']);
            self::fail('Employee of A resolved a context for B.');
        } catch (HttpException $e) {
            // 404 rather than 403: B's existence is not confirmed to a stranger.
            self::assertSame(404, $e->status());
        }
    }

    public function testCarLookupIsScopedToTheOrganization(): void
    {
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);

        // The same id, asked for under the wrong tenant, does not exist.
        self::assertNotNull($cars->findInOrganization((int) $this->carB['id'], (int) $this->orgB['id']));
        self::assertNull($cars->findInOrganization((int) $this->carB['id'], (int) $this->orgA['id']));
    }

    public function testCarListsNeverLeakAcrossOrganizations(): void
    {
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);

        $listA = $cars->listForOrganization((int) $this->orgA['id'], [], new Pagination(1, 50, 100));

        $ids = array_map(static fn (array $car): int => (int) $car['id'], $listA);

        self::assertContains((int) $this->carA['id'], $ids);
        self::assertNotContains((int) $this->carB['id'], $ids);
    }

    /** Mutating another tenant's car through the service is refused. */
    public function testCannotEditAnotherOrganizationsCar(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);

        /** @var CarService $service */
        $service = $this->app->get(CarService::class);

        $this->expectException(CarException::class);

        $service->update($context, (int) $this->carB['id'], [
            'brand' => 'Hijacked',
            'model' => 'Hijacked',
            'year'  => 2024,
        ]);
    }

    public function testCannotArchiveAnotherOrganizationsCar(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);

        /** @var CarService $service */
        $service = $this->app->get(CarService::class);

        try {
            $service->archive($context, (int) $this->carB['id']);
            self::fail('Archived a car belonging to another organization.');
        } catch (CarException) {
            // Expected.
        }

        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);
        $stillActive = $cars->findInOrganization((int) $this->carB['id'], (int) $this->orgB['id']);

        self::assertNotNull($stillActive);
        self::assertNull($stillActive['archived_at'], 'B\'s car must be untouched.');
    }

    /**
     * A direct write attempt with a mismatched organization affects no rows,
     * which is the property the whole isolation model rests on.
     */
    public function testTenantScopedUpdateAffectsNoRowsForTheWrongOrganization(): void
    {
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);

        $affected = $cars->update(
            (int) $this->carB['id'],
            (int) $this->orgA['id'],
            ['brand' => 'Hijacked']
        );

        self::assertSame(0, $affected);
    }

    public function testBookingsAreScopedToTheOrganization(): void
    {
        $customer = $this->createUserWithPhone('customer@example.test');

        $reference = $this->makeBooking((int) $this->orgB['id'], (int) $this->carB['id'], $customer);

        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);

        self::assertNotNull($bookings->findInOrganization($reference, (int) $this->orgB['id']));
        self::assertNull($bookings->findInOrganization($reference, (int) $this->orgA['id']));
    }

    public function testCategoriesAndLocationsAreScoped(): void
    {
        /** @var CategoryRepository $categories */
        $categories = $this->app->get(CategoryRepository::class);
        /** @var LocationRepository $locations */
        $locations = $this->app->get(LocationRepository::class);

        $categoryB = $categories->create((int) $this->orgB['id'], 'Beta Only', 'beta-only');
        $locationB = $locations->create((int) $this->orgB['id'], [
            'name'      => 'Beta Depot',
            'address'   => 'Somewhere',
            'is_active' => 1,
        ]);

        self::assertNotNull($categories->findInOrganization($categoryB, (int) $this->orgB['id']));
        self::assertNull($categories->findInOrganization($categoryB, (int) $this->orgA['id']));

        self::assertNotNull($locations->findInOrganization($locationB, (int) $this->orgB['id']));
        self::assertNull($locations->findInOrganization($locationB, (int) $this->orgA['id']));

        // A form supplying B's location id inside A's organization is rejected.
        self::assertFalse($locations->belongsToOrganization($locationB, (int) $this->orgA['id']));
    }

    public function testCustomerRecordsAreScoped(): void
    {
        $customer = $this->createUserWithPhone('shared.customer@example.test');

        /** @var OrganizationCustomerRepository $customers */
        $customers = $this->app->get(OrganizationCustomerRepository::class);

        $relationshipB = $customers->ensure((int) $this->orgB['id'], $customer);

        self::assertNotNull($customers->findInOrganization($relationshipB, (int) $this->orgB['id']));
        self::assertNull($customers->findInOrganization($relationshipB, (int) $this->orgA['id']));

        self::assertTrue($customers->relationshipExists((int) $this->orgB['id'], $customer));
        self::assertFalse($customers->relationshipExists((int) $this->orgA['id'], $customer));
    }

    public function testEmployeeRecordsAreScoped(): void
    {
        /** @var OrganizationUserRepository $members */
        $members = $this->app->get(OrganizationUserRepository::class);

        $membershipB = $members->findMembership((int) $this->orgB['id'], $this->adminB);
        self::assertNotNull($membershipB);

        // A cannot address B's membership row.
        self::assertNull($members->findInOrganization((int) $membershipB['id'], (int) $this->orgA['id']));
        self::assertSame(0, $members->remove((int) $membershipB['id'], (int) $this->orgA['id']));

        // The membership survived the attempt.
        self::assertNotNull($members->findMembership((int) $this->orgB['id'], $this->adminB));
    }

    public function testDocumentReviewQueueOnlyContainsOwnCustomers(): void
    {
        $customer = $this->createUserWithPhone('doc.customer@example.test');

        /** @var OrganizationCustomerRepository $customers */
        $customers = $this->app->get(OrganizationCustomerRepository::class);
        $customers->ensure((int) $this->orgB['id'], $customer);

        /** @var DocumentRepository $documents */
        $documents = $this->app->get(DocumentRepository::class);

        $documentId = $documents->create([
            'user_id'       => $customer,
            'document_type' => 'driving_license',
            'file_path'     => 'documents/' . $customer . '/test.webp',
            'mime_type'     => 'image/webp',
            'file_size'     => 1024,
        ]);

        // B has the relationship, so B can see it; A never can.
        self::assertNotNull($documents->findDocumentForOrganization($documentId, (int) $this->orgB['id']));
        self::assertNull($documents->findDocumentForOrganization($documentId, (int) $this->orgA['id']));

        self::assertSame(1, $documents->countForOrganization((int) $this->orgB['id'], null, null));
        self::assertSame(0, $documents->countForOrganization((int) $this->orgA['id'], null, null));
    }

    public function testReportsCountOnlyTheOwnOrganization(): void
    {
        $customer = $this->createUserWithPhone('report.customer@example.test');

        $this->makeBooking((int) $this->orgB['id'], (int) $this->carB['id'], $customer);
        $this->makeBooking((int) $this->orgB['id'], (int) $this->carB['id'], $customer);

        /** @var ReportRepository $reports */
        $reports = $this->app->get(ReportRepository::class);

        self::assertSame(2, $reports->bookingStatusCounts((int) $this->orgB['id'])['total']);
        self::assertSame(0, $reports->bookingStatusCounts((int) $this->orgA['id'])['total']);

        self::assertSame(1, $reports->countCars((int) $this->orgA['id']));
        self::assertSame(1, $reports->countCars((int) $this->orgB['id']));
    }

    /** Creates a pending booking directly, bypassing availability checks. */
    private function makeBooking(int $organizationId, int $carId, int $userId): string
    {
        /** @var BookingRepository $bookings */
        $bookings = $this->app->get(BookingRepository::class);

        $reference = $bookings->nextReference();

        $bookings->create([
            'reference'                => $reference,
            'organization_id'          => $organizationId,
            'user_id'                  => $userId,
            'car_id'                   => $carId,
            'pickup_at'                => gmdate('Y-m-d H:i:s', time() + 86400),
            'return_at'                => gmdate('Y-m-d H:i:s', time() + 259200),
            'rental_days'              => 2,
            'daily_rate_snapshot_fils' => 20000,
            'subtotal_fils'            => 40000,
            'total_fils'               => 40000,
            'status'                   => 'pending',
        ]);

        return $reference;
    }
}
