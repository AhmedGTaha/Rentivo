<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Test 11 and §62–§65: schema guarantees.
 *
 * These assert the structural properties the application relies on, so a
 * future migration cannot silently weaken them.
 */
final class SchemaIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();
    }

    private function schema(): string
    {
        return $this->db()->databaseName();
    }

    /** @return list<array<string,mixed>> */
    private function columns(): array
    {
        return $this->db()->select(
            'SELECT table_name AS t, column_name AS c, data_type AS d, column_type AS ct
             FROM information_schema.columns
             WHERE table_schema = ?',
            [$this->schema()]
        );
    }

    // -----------------------------------------------------------------
    // SRS Test 11: money is always an integer
    // -----------------------------------------------------------------

    /**
     * No monetary column may be a float, double or decimal anywhere in the
     * schema — every amount is an integer number of fils.
     */
    public function testNoMonetaryColumnUsesFloatingPoint(): void
    {
        $monetary = array_filter(
            $this->columns(),
            static fn (array $column): bool => str_contains((string) $column['c'], 'fils')
                || str_contains((string) $column['c'], 'price')
                || str_contains((string) $column['c'], 'amount')
                || str_contains((string) $column['c'], 'rate')
        );

        self::assertNotEmpty($monetary, 'Expected to find monetary columns.');

        foreach ($monetary as $column) {
            self::assertContains(
                strtolower((string) $column['d']),
                ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'],
                sprintf('%s.%s is %s; money must be an integer.', $column['t'], $column['c'], $column['d'])
            );
        }
    }

    /** Nothing anywhere in the schema uses an approximate numeric type. */
    public function testSchemaContainsNoFloatOrDoubleColumns(): void
    {
        $approximate = array_filter(
            $this->columns(),
            static fn (array $column): bool => in_array(
                strtolower((string) $column['d']),
                ['float', 'double', 'real'],
                true
            )
        );

        self::assertSame([], array_values(array_map(
            static fn (array $c): string => $c['t'] . '.' . $c['c'],
            $approximate
        )));
    }

    /** Every fils column is unsigned: a negative amount cannot be stored. */
    public function testFilsColumnsAreUnsigned(): void
    {
        foreach ($this->columns() as $column) {
            if (!str_contains((string) $column['c'], 'fils')) {
                continue;
            }

            self::assertStringContainsString(
                'unsigned',
                strtolower((string) $column['ct']),
                $column['t'] . '.' . $column['c'] . ' should be unsigned'
            );
        }
    }

    // -----------------------------------------------------------------
    // Tables, engine and charset
    // -----------------------------------------------------------------

    public function testEverySrsTableExists(): void
    {
        foreach ([
            'users', 'user_profiles',
            'organizations', 'organization_users',
            'employee_invitations', 'permissions', 'employee_permissions',
            'employee_invitation_permissions',
            'locations', 'car_categories', 'cars', 'car_images',
            'favorites',
            'organization_customers',
            'bookings', 'rentals', 'rental_inspection_images',
            'user_documents', 'document_reviews',
            'notifications',
            'activity_logs',
        ] as $table) {
            self::assertTrue($this->db()->tableExists($table), $table . ' is missing');
        }
    }

    public function testAllTablesUseInnoDbAndUtf8mb4(): void
    {
        $tables = $this->db()->select(
            'SELECT table_name AS t, engine AS e, table_collation AS c
             FROM information_schema.tables
             WHERE table_schema = ? AND table_type = ?',
            [$this->schema(), 'BASE TABLE']
        );

        self::assertNotEmpty($tables);

        foreach ($tables as $table) {
            self::assertSame('InnoDB', $table['e'], $table['t'] . ' must use InnoDB');
            self::assertStringStartsWith('utf8mb4', (string) $table['c'], $table['t'] . ' must be utf8mb4');
        }
    }

    // -----------------------------------------------------------------
    // Constraints (SRS §63)
    // -----------------------------------------------------------------

    private function hasUniqueOn(string $table, array $columns): bool
    {
        $rows = $this->db()->select(
            'SELECT index_name AS i, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND non_unique = 0
             GROUP BY index_name',
            [$this->schema(), $table]
        );

        $target = implode(',', $columns);

        foreach ($rows as $row) {
            if (strtolower((string) $row['cols']) === strtolower($target)) {
                return true;
            }
        }

        return false;
    }

    public function testRequiredUniqueConstraintsExist(): void
    {
        $expected = [
            ['users', ['google_id']],
            ['users', ['email']],
            ['organizations', ['slug']],
            ['organization_users', ['organization_id', 'user_id']],
            ['permissions', ['key']],
            ['employee_permissions', ['organization_user_id', 'permission_id']],
            ['employee_invitation_permissions', ['invitation_id', 'permission_id']],
            ['car_categories', ['organization_id', 'name']],
            ['cars', ['organization_id', 'slug']],
            ['favorites', ['user_id', 'car_id']],
            ['organization_customers', ['organization_id', 'user_id']],
            ['bookings', ['reference']],
            ['rentals', ['booking_id']],
            ['document_reviews', ['organization_id', 'document_id']],
        ];

        foreach ($expected as [$table, $columns]) {
            self::assertTrue(
                $this->hasUniqueOn($table, $columns),
                sprintf('%s is missing a unique constraint on (%s)', $table, implode(', ', $columns))
            );
        }
    }

    /** The unique constraints are enforced by the database, not just declared. */
    public function testUniqueConstraintsAreActuallyEnforced(): void
    {
        $userId = $this->createUser('unique@example.test');
        $organization = $this->createOrganization('Unique Agency', $userId);
        $car = $this->createCar((int) $organization['id']);

        $this->db()->insert('favorites', [
            'user_id'    => $userId,
            'car_id'     => (int) $car['id'],
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->expectException(\PDOException::class);

        $this->db()->insert('favorites', [
            'user_id'    => $userId,
            'car_id'     => (int) $car['id'],
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    // -----------------------------------------------------------------
    // Indexes (SRS §65)
    // -----------------------------------------------------------------

    private function indexedColumns(string $table): array
    {
        $rows = $this->db()->select(
            'SELECT DISTINCT column_name AS c
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ?',
            [$this->schema(), $table]
        );

        return array_map(static fn (array $row): string => (string) $row['c'], $rows);
    }

    public function testRequiredIndexesExist(): void
    {
        $expected = [
            'cars'               => ['organization_id', 'status', 'brand', 'category_id', 'location_id'],
            'bookings'           => ['organization_id', 'user_id', 'car_id', 'status', 'pickup_at', 'return_at'],
            'organization_users' => ['user_id', 'organization_id'],
            'notifications'      => ['user_id', 'read_at'],
            'activity_logs'      => ['organization_id', 'created_at'],
        ];

        foreach ($expected as $table => $columns) {
            $indexed = $this->indexedColumns($table);

            foreach ($columns as $column) {
                self::assertContains(
                    $column,
                    $indexed,
                    sprintf('%s.%s should be indexed', $table, $column)
                );
            }
        }
    }

    /**
     * The composite index that makes the availability query efficient:
     * (car_id, status, pickup_at, return_at).
     */
    public function testBookingAvailabilityCompositeIndexExists(): void
    {
        $rows = $this->db()->select(
            'SELECT index_name AS i, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ?
             GROUP BY index_name',
            [$this->schema(), 'bookings']
        );

        $found = false;

        foreach ($rows as $row) {
            if (strtolower((string) $row['cols']) === 'car_id,status,pickup_at,return_at') {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'bookings needs a (car_id, status, pickup_at, return_at) index.');
    }

    // -----------------------------------------------------------------
    // Foreign keys (SRS §64)
    // -----------------------------------------------------------------

    public function testHistoricalRecordsAreProtectedFromCascadingDeletes(): void
    {
        $rules = $this->db()->select(
            'SELECT rc.table_name AS t, rc.constraint_name AS n, rc.delete_rule AS d,
                    kcu.column_name AS c, kcu.referenced_table_name AS rt
             FROM information_schema.referential_constraints rc
             INNER JOIN information_schema.key_column_usage kcu
                     ON kcu.constraint_name = rc.constraint_name
                    AND kcu.constraint_schema = rc.constraint_schema
             WHERE rc.constraint_schema = ?',
            [$this->schema()]
        );

        self::assertNotEmpty($rules, 'Expected foreign keys to exist.');

        // Bookings and rentals must never be destroyed by deleting a parent.
        foreach ($rules as $rule) {
            if (!in_array($rule['t'], ['bookings', 'rentals'], true)) {
                continue;
            }

            self::assertNotSame(
                'CASCADE',
                $rule['d'],
                sprintf('%s.%s must not cascade on delete from %s', $rule['t'], $rule['c'], $rule['rt'])
            );
        }
    }

    /** Archiving a car keeps its bookings intact. */
    public function testArchivingACarPreservesItsBookings(): void
    {
        $adminId = $this->createUser('admin@example.test');
        $customerId = $this->createUserWithPhone('customer@example.test');
        $organization = $this->createOrganization('History Agency', $adminId);
        $car = $this->createCar((int) $organization['id']);

        /** @var \Rentivo\Repositories\BookingRepository $bookings */
        $bookings = $this->app->get(\Rentivo\Repositories\BookingRepository::class);

        $bookings->create([
            'reference'                => $bookings->nextReference(),
            'organization_id'          => (int) $organization['id'],
            'user_id'                  => $customerId,
            'car_id'                   => (int) $car['id'],
            'pickup_at'                => gmdate('Y-m-d H:i:s', time() - 172800),
            'return_at'                => gmdate('Y-m-d H:i:s', time() - 86400),
            'rental_days'              => 1,
            'daily_rate_snapshot_fils' => 20000,
            'subtotal_fils'            => 20000,
            'total_fils'               => 20000,
            'status'                   => 'completed',
        ]);

        $this->app->get(\Rentivo\Repositories\CarRepository::class)
            ->archive((int) $car['id'], (int) $organization['id']);

        self::assertSame(
            1,
            (int) $this->db()->scalar('SELECT COUNT(*) FROM bookings WHERE car_id = ?', [(int) $car['id']])
        );
    }
}
