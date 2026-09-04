<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Aggregate queries backing the management dashboard and reports.
 *
 * Every statement here carries an organization_id predicate; there is no
 * cross-organization aggregate anywhere in V1.
 */
final class ReportRepository extends Repository
{
    /**
     * Booking counts by status for one organization, optionally within a
     * date range.
     *
     * @return array<string,int>
     */
    public function bookingStatusCounts(int $organizationId, ?string $from = null, ?string $to = null): array
    {
        [$range, $bindings] = $this->range($from, $to);

        $rows = $this->db->select(
            'SELECT `status`, COUNT(*) AS `total`
             FROM `bookings`
             WHERE `organization_id` = ?' . $range . '
             GROUP BY `status`',
            [$organizationId, ...$bindings]
        );

        $counts = array_fill_keys(
            ['pending', 'confirmed', 'rejected', 'ready_for_pickup', 'active', 'completed', 'cancelled', 'no_show'],
            0
        );

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * Recorded revenue in fils.
     *
     * Only completed rentals count toward revenue, so cancelled or pending
     * bookings never inflate the figure.
     */
    public function revenueFils(int $organizationId, ?string $from = null, ?string $to = null): int
    {
        [$range, $bindings] = $this->range($from, $to, 'completed_at');

        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(`total_fils`), 0)
             FROM `bookings`
             WHERE `organization_id` = ? AND `status` = \'completed\'' . $range,
            [$organizationId, ...$bindings]
        );
    }

    /** Revenue for the current business month, for the dashboard tile. */
    public function revenueForPeriodFils(int $organizationId, string $from, string $to): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(`total_fils`), 0)
             FROM `bookings`
             WHERE `organization_id` = ?
               AND `status` = \'completed\'
               AND `completed_at` >= ? AND `completed_at` < ?',
            [$organizationId, $from, $to]
        );
    }

    /**
     * Most rented cars by completed rental count.
     *
     * @return list<array<string,mixed>>
     */
    public function mostRentedCars(int $organizationId, int $limit = 10): array
    {
        return $this->db->select(
            'SELECT c.`id`, c.`brand`, c.`model`, c.`year`, c.`plate_number`, c.`status`,
                    COUNT(b.`id`) AS `booking_count`,
                    COALESCE(SUM(b.`status` = \'completed\'), 0) AS `completed_count`,
                    COALESCE(SUM(CASE WHEN b.`status` = \'completed\' THEN b.`total_fils` ELSE 0 END), 0) AS `revenue_fils`
             FROM `cars` c
             LEFT JOIN `bookings` b ON b.`car_id` = c.`id` AND b.`organization_id` = c.`organization_id`
             WHERE c.`organization_id` = ?
             GROUP BY c.`id`, c.`brand`, c.`model`, c.`year`, c.`plate_number`, c.`status`
             HAVING `booking_count` > 0
             ORDER BY `completed_count` DESC, `booking_count` DESC
             LIMIT ' . max(1, $limit),
            [$organizationId]
        );
    }

    /**
     * Bookings grouped by business month for the last N months.
     *
     * @return list<array{month:string,total:int,completed:int,revenue_fils:int}>
     */
    public function bookingsByMonth(int $organizationId, int $months = 12): array
    {
        $rows = $this->db->select(
            'SELECT DATE_FORMAT(`created_at`, \'%Y-%m\') AS `month`,
                    COUNT(*) AS `total`,
                    COALESCE(SUM(`status` = \'completed\'), 0) AS `completed`,
                    COALESCE(SUM(CASE WHEN `status` = \'completed\' THEN `total_fils` ELSE 0 END), 0) AS `revenue_fils`
             FROM `bookings`
             WHERE `organization_id` = ?
               AND `created_at` >= DATE_SUB(UTC_DATE(), INTERVAL ? MONTH)
             GROUP BY `month`
             ORDER BY `month` ASC',
            [$organizationId, max(1, $months)]
        );

        return array_map(static fn (array $row): array => [
            'month'        => (string) $row['month'],
            'total'        => (int) $row['total'],
            'completed'    => (int) $row['completed'],
            'revenue_fils' => (int) $row['revenue_fils'],
        ], $rows);
    }

    /** @return array<string,int> */
    public function fleetStatusDistribution(int $organizationId): array
    {
        $rows = $this->db->select(
            'SELECT `status`, COUNT(*) AS `total`
             FROM `cars`
             WHERE `organization_id` = ? AND `archived_at` IS NULL
             GROUP BY `status`',
            [$organizationId]
        );

        $counts = array_fill_keys(['available', 'reserved', 'rented', 'maintenance', 'inactive'], 0);

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countCars(int $organizationId, bool $includeArchived = false): int
    {
        $sql = 'SELECT COUNT(*) FROM `cars` WHERE `organization_id` = ?';

        if (!$includeArchived) {
            $sql .= ' AND `archived_at` IS NULL';
        }

        return (int) $this->db->scalar($sql, [$organizationId]);
    }

    public function countCustomers(int $organizationId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `organization_customers` WHERE `organization_id` = ?',
            [$organizationId]
        );
    }

    public function countActiveRentals(int $organizationId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `bookings` WHERE `organization_id` = ? AND `status` = \'active\'',
            [$organizationId]
        );
    }

    public function countOverdueRentals(int $organizationId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `bookings`
             WHERE `organization_id` = ? AND `status` = \'active\' AND `return_at` < ?',
            [$organizationId, $this->now()]
        );
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function range(?string $from, ?string $to, string $column = 'created_at'): array
    {
        $sql = '';
        $bindings = [];

        if ($from !== null && $from !== '') {
            $sql .= ' AND `' . $column . '` >= ?';
            $bindings[] = $from;
        }

        if ($to !== null && $to !== '') {
            $sql .= ' AND `' . $column . '` < ?';
            $bindings[] = $to;
        }

        return [$sql, $bindings];
    }
}
