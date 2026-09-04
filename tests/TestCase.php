<?php

declare(strict_types=1);

namespace Rentivo\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Rentivo\Application;
use Rentivo\Bootstrap;
use Rentivo\Database\Connection;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Repositories\PermissionRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\OrganizationContext;
use Rentivo\Services\MailService;
use Rentivo\Support\Slug;

/**
 * Shared test scaffolding.
 *
 * Each test gets a freshly truncated database and a fresh session, so no test
 * can depend on another's leftovers.
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    /** Tables emptied between tests, ordered so foreign keys stay satisfiable. */
    private const TRUNCATE = [
        'activity_logs',
        'notifications',
        'rental_inspection_images',
        'rentals',
        'bookings',
        'booking_reference_counters',
        'document_reviews',
        'user_documents',
        'favorites',
        'car_images',
        'cars',
        'car_categories',
        'locations',
        'organization_customers',
        'employee_invitation_permissions',
        'employee_invitations',
        'employee_permissions',
        'organization_users',
        'organizations',
        'user_profiles',
        'users',
        'rate_limits',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = Bootstrap::boot(dirname(__DIR__), true);

        // Mail must never leave the machine during tests.
        $this->app->get(MailService::class)->captureOnly();

        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];

        if ($this->databaseAvailable()) {
            $this->truncateTables();
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        parent::tearDown();
    }

    protected function databaseAvailable(): bool
    {
        return defined('RENTIVO_TEST_DATABASE_READY') && RENTIVO_TEST_DATABASE_READY === true;
    }

    /** Skips the test when MySQL was unavailable at bootstrap. */
    protected function requiresDatabase(): void
    {
        if (!$this->databaseAvailable()) {
            $reason = defined('RENTIVO_TEST_DATABASE_ERROR')
                ? (string) RENTIVO_TEST_DATABASE_ERROR
                : 'unknown error';

            $this->markTestSkipped('Database not available: ' . $reason);
        }
    }

    protected function db(): Connection
    {
        return $this->app->get(Connection::class);
    }

    private function truncateTables(): void
    {
        $pdo = $this->db()->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TRUNCATE as $table) {
            $pdo->exec('TRUNCATE TABLE `' . $table . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    // -----------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------

    protected function createUser(string $email, string $name = 'Test User'): int
    {
        /** @var UserRepository $users */
        $users = $this->app->get(UserRepository::class);

        $userId = $users->createFromGoogle([
            'google_id'      => 'google-' . substr(hash('sha256', $email), 0, 20),
            'email'          => $email,
            'name'           => $name,
            'avatar'         => null,
            'email_verified' => true,
        ]);

        $users->profileOrCreate($userId);

        return $userId;
    }

    protected function createUserWithPhone(string $email, string $name = 'Test User'): int
    {
        $userId = $this->createUser($email, $name);

        $this->app->get(UserRepository::class)->updateProfile($userId, ['phone' => '+973 3000 0000']);

        return $userId;
    }

    /**
     * @return array<string,mixed> The organization row.
     */
    protected function createOrganization(string $name, int $adminUserId): array
    {
        /** @var OrganizationRepository $organizations */
        $organizations = $this->app->get(OrganizationRepository::class);
        /** @var OrganizationUserRepository $members */
        $members = $this->app->get(OrganizationUserRepository::class);

        $organizationId = $organizations->create([
            'name'          => $name,
            'slug'          => Slug::unique($name, static fn (string $s): bool => $organizations->slugExists($s)),
            'contact_email' => strtolower(Slug::make($name)) . '@example.test',
            'phone'         => '+973 1700 0000',
            'is_active'     => 1,
        ]);

        $members->create($organizationId, $adminUserId, OrganizationContext::ROLE_ADMIN);

        return $organizations->find($organizationId) ?? [];
    }

    /**
     * Adds an employee with an explicit permission set.
     *
     * @param list<string> $permissionKeys
     * @return int The organization_users id.
     */
    protected function addEmployee(int $organizationId, int $userId, array $permissionKeys): int
    {
        /** @var OrganizationUserRepository $members */
        $members = $this->app->get(OrganizationUserRepository::class);
        /** @var PermissionRepository $permissions */
        $permissions = $this->app->get(PermissionRepository::class);

        $membershipId = $members->create($organizationId, $userId, OrganizationContext::ROLE_EMPLOYEE);
        $members->syncPermissions($membershipId, $permissions->idsForKeys($permissionKeys));

        return $membershipId;
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed> The car row.
     */
    protected function createCar(int $organizationId, array $overrides = []): array
    {
        /** @var CarRepository $cars */
        $cars = $this->app->get(CarRepository::class);

        $data = array_merge([
            'brand'           => 'Toyota',
            'model'           => 'Corolla',
            'year'            => 2024,
            'transmission'    => 'automatic',
            'fuel_type'       => 'petrol',
            'seats'           => 5,
            'doors'           => 4,
            'daily_rate_fils' => 20000,
            'status'          => 'available',
        ], $overrides);

        $data['slug'] = Slug::unique(
            $data['brand'] . ' ' . $data['model'] . ' ' . $data['year'],
            static fn (string $s): bool => $cars->slugExists($organizationId, $s)
        );

        $carId = $cars->create($organizationId, $data);

        return $cars->findInOrganization($carId, $organizationId) ?? [];
    }

    /**
     * Signs the given user in and begins a fresh request cycle.
     *
     * Authorization caches a resolved context for the lifetime of a request,
     * so switching identity — or re-reading permissions after changing them —
     * must start a new one, exactly as a real HTTP request would.
     */
    protected function actingAs(int $userId): void
    {
        $_SESSION['_auth_user_id'] = $userId;

        $this->app->get(\Rentivo\Auth\SessionAuth::class)->refresh();
        $this->startNewRequest();
    }

    /**
     * Discards per-request caches without touching the session, modelling the
     * next HTTP request from the same signed-in user.
     */
    protected function startNewRequest(): void
    {
        $container = $this->app->container();

        $container->bind(Authorization::class, static fn ($c): Authorization => new Authorization(
            $c->get(\Rentivo\Auth\SessionAuth::class),
            $c->get(OrganizationRepository::class),
            $c->get(OrganizationUserRepository::class)
        ));

        $this->app->get(\Rentivo\Auth\SessionAuth::class)->refresh();
    }

    /**
     * Resolves the organization context the way a management request would.
     */
    protected function contextFor(string $slug): OrganizationContext
    {
        /** @var Authorization $authorization */
        $authorization = $this->app->get(Authorization::class);

        return $authorization->organizationContext($slug);
    }
}
