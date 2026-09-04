<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Pagination;

/**
 * Bookings.
 *
 * Customer reads are scoped by user_id, management reads by organization_id.
 * There is no "find booking by id" that ignores both.
 */
final class BookingRepository extends Repository
{
    public const BLOCKING_STATUSES = ['confirmed', 'ready_for_pickup', 'active'];

    private const DETAIL_SELECT = 'b.*,
        c.`brand`, c.`model`, c.`year`, c.`slug` AS `car_slug`, c.`transmission`, c.`fuel_type`,
        c.`seats`, c.`color`, c.`plate_number`, c.`status` AS `car_status`,
        o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
        o.`logo_path` AS `organization_logo_path`, o.`primary_color` AS `organization_color`,
        o.`contact_email` AS `organization_contact_email`, o.`phone` AS `organization_phone`,
        o.`rental_terms` AS `organization_rental_terms`,
        pl.`name` AS `pickup_location_name`, pl.`address` AS `pickup_location_address`,
        rl.`name` AS `return_location_name`, rl.`address` AS `return_location_address`,
        (SELECT ci.`file_path` FROM `car_images` ci
         WHERE ci.`car_id` = c.`id`
         ORDER BY ci.`is_primary` DESC, ci.`display_order` ASC, ci.`id` ASC
         LIMIT 1) AS `primary_image`';

    private const DETAIL_JOINS = 'FROM `bookings` b
        INNER JOIN `cars` c ON c.`id` = b.`car_id`
        INNER JOIN `organizations` o ON o.`id` = b.`organization_id`
        LEFT JOIN `locations` pl ON pl.`id` = b.`pickup_location_id`
        LEFT JOIN `locations` rl ON rl.`id` = b.`return_location_id`';

    // -----------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne('SELECT ' . self::DETAIL_SELECT . ' ' . self::DETAIL_JOINS . ' WHERE b.`id` = ?', [$id]);
    }

    /** Customer-scoped lookup by public reference. */
    public function findForUser(string $reference, int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::DETAIL_SELECT . ' ' . self::DETAIL_JOINS . '
             WHERE b.`reference` = ? AND b.`user_id` = ?',
            [$reference, $userId]
        );
    }

    /** Management-scoped lookup by public reference. */
    public function findInOrganization(string $reference, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::DETAIL_SELECT . ',
                    u.`name` AS `customer_name`, u.`email` AS `customer_email`,
                    u.`google_avatar_url` AS `customer_avatar`,
                    p.`phone` AS `customer_phone`, p.`nationality` AS `customer_nationality`
             ' . self::DETAIL_JOINS . '
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             LEFT JOIN `user_profiles` p ON p.`user_id` = b.`user_id`
             WHERE b.`reference` = ? AND b.`organization_id` = ?',
            [$reference, $organizationId]
        );
    }

    /** Row lock taken while confirming, so two confirmations cannot race. */
    public function lockById(int $id, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `bookings` WHERE `id` = ? AND `organization_id` = ? FOR UPDATE',
            [$id, $organizationId]
        );
    }

    // -----------------------------------------------------------------
    // Availability
    // -----------------------------------------------------------------

    /**
     * Blocking bookings that overlap the requested window for one car.
     *
     * Overlap rule (SRS §38):
     *   existing.pickup_at < requested.return_at
     *   AND existing.return_at > requested.pickup_at
     *
     * Pending bookings never block.
     *
     * @return list<array<string,mixed>>
     */
    public function overlappingBlockingBookings(
        int $carId,
        string $pickupAt,
        string $returnAt,
        ?int $exceptBookingId = null
    ): array {
        $sql = 'SELECT `id`, `reference`, `status`, `pickup_at`, `return_at`
                FROM `bookings`
                WHERE `car_id` = ?
                  AND `status` IN (' . $this->placeholders(self::BLOCKING_STATUSES) . ')
                  AND `pickup_at` < ?
                  AND `return_at` > ?';

        $bindings = [$carId, ...self::BLOCKING_STATUSES, $returnAt, $pickupAt];

        if ($exceptBookingId !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $exceptBookingId;
        }

        return $this->db->select($sql, $bindings);
    }

    /** Same query under a row lock, used inside the confirmation transaction. */
    public function overlappingBlockingBookingsForUpdate(
        int $carId,
        string $pickupAt,
        string $returnAt,
        ?int $exceptBookingId = null
    ): array {
        $sql = 'SELECT `id`, `reference`, `status`, `pickup_at`, `return_at`
                FROM `bookings`
                WHERE `car_id` = ?
                  AND `status` IN (' . $this->placeholders(self::BLOCKING_STATUSES) . ')
                  AND `pickup_at` < ?
                  AND `return_at` > ?';

        $bindings = [$carId, ...self::BLOCKING_STATUSES, $returnAt, $pickupAt];

        if ($exceptBookingId !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $exceptBookingId;
        }

        return $this->db->select($sql . ' FOR UPDATE', $bindings);
    }

    // -----------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('bookings', $data + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Tenant-scoped update.
     *
     * @param array<string,mixed> $data
     */
    public function updateInOrganization(int $id, int $organizationId, array $data): int
    {
        $data['updated_at'] = $this->now();

        return $this->db->update('bookings', $data, [
            'id'              => $id,
            'organization_id' => $organizationId,
        ]);
    }

    /** Customer-scoped update (used only for cancellation). */
    public function updateForUser(int $id, int $userId, array $data): int
    {
        $data['updated_at'] = $this->now();

        return $this->db->update('bookings', $data, [
            'id'      => $id,
            'user_id' => $userId,
        ]);
    }

    /**
     * Allocates the next reference for the given year.
     *
     * LAST_INSERT_ID(expr) makes the read-modify-write atomic within the
     * connection, so concurrent bookings cannot receive the same number.
     */
    public function nextReference(?int $year = null): string
    {
        $year ??= (int) DateTimeHelper::now()->setTimezone(DateTimeHelper::business())->format('Y');

        $this->db->statement(
            'INSERT INTO `booking_reference_counters` (`year`, `next_value`)
             VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE `next_value` = LAST_INSERT_ID(`next_value` + 1)',
            [$year]
        );

        $sequence = (int) $this->db->pdo()->lastInsertId();

        return sprintf('BK-%d-%06d', $year, $sequence);
    }

    // -----------------------------------------------------------------
    // Customer listings
    // -----------------------------------------------------------------

    /**
     * @param 'upcoming'|'active'|'completed'|'cancelled'|'all' $group
     * @return list<array<string,mixed>>
     */
    public function listForUser(int $userId, string $group, Pagination $pagination): array
    {
        [$where, $bindings] = $this->userGroupFilter($userId, $group);

        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ' ' . self::DETAIL_JOINS . '
             WHERE ' . $where . '
             ORDER BY b.`pickup_at` DESC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    public function countForUser(int $userId, string $group): int
    {
        [$where, $bindings] = $this->userGroupFilter($userId, $group);

        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `bookings` b WHERE ' . $where,
            $bindings
        );
    }

    /** @return array{0:string,1:list<mixed>} */
    private function userGroupFilter(int $userId, string $group): array
    {
        $where = 'b.`user_id` = ?';
        $bindings = [$userId];

        $where .= match ($group) {
            'upcoming'  => " AND b.`status` IN ('pending','confirmed','ready_for_pickup')",
            'active'    => " AND b.`status` = 'active'",
            'completed' => " AND b.`status` = 'completed'",
            'cancelled' => " AND b.`status` IN ('cancelled','rejected','no_show')",
            default     => '',
        };

        return [$where, $bindings];
    }

    /** @return array<string,int> Counts per customer booking tab. */
    public function groupCountsForUser(int $userId): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COUNT(*) AS `all`,
                SUM(`status` IN ('pending','confirmed','ready_for_pickup')) AS `upcoming`,
                SUM(`status` = 'active') AS `active`,
                SUM(`status` = 'completed') AS `completed`,
                SUM(`status` IN ('cancelled','rejected','no_show')) AS `cancelled`
             FROM `bookings` WHERE `user_id` = ?",
            [$userId]
        );

        return [
            'all'       => (int) ($row['all'] ?? 0),
            'upcoming'  => (int) ($row['upcoming'] ?? 0),
            'active'    => (int) ($row['active'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function recentForUser(int $userId, int $limit = 5): array
    {
        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ' ' . self::DETAIL_JOINS . '
             WHERE b.`user_id` = ?
             ORDER BY b.`created_at` DESC
             LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    /** The customer's next upcoming booking, for their dashboard. */
    public function nextUpcomingForUser(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::DETAIL_SELECT . ' ' . self::DETAIL_JOINS . "
             WHERE b.`user_id` = ?
               AND b.`status` IN ('pending','confirmed','ready_for_pickup')
               AND b.`return_at` >= ?
             ORDER BY b.`pickup_at` ASC
             LIMIT 1",
            [$userId, $this->now()]
        );
    }

    public function activeForUser(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::DETAIL_SELECT . ' ' . self::DETAIL_JOINS . "
             WHERE b.`user_id` = ? AND b.`status` = 'active'
             ORDER BY b.`pickup_at` ASC
             LIMIT 1",
            [$userId]
        );
    }

    // -----------------------------------------------------------------
    // Management listings
    // -----------------------------------------------------------------

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listForOrganization(int $organizationId, array $filters, Pagination $pagination): array
    {
        [$where, $bindings] = $this->organizationFilter($organizationId, $filters);

        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ',
                    u.`name` AS `customer_name`, u.`email` AS `customer_email`,
                    p.`phone` AS `customer_phone`
             ' . self::DETAIL_JOINS . '
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             LEFT JOIN `user_profiles` p ON p.`user_id` = b.`user_id`
             WHERE ' . $where . '
             ORDER BY b.`created_at` DESC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    /** @param array<string,mixed> $filters */
    public function countForOrganization(int $organizationId, array $filters): int
    {
        [$where, $bindings] = $this->organizationFilter($organizationId, $filters);

        return (int) $this->db->scalar(
            'SELECT COUNT(*)
             FROM `bookings` b
             INNER JOIN `cars` c ON c.`id` = b.`car_id`
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             LEFT JOIN `user_profiles` p ON p.`user_id` = b.`user_id`
             WHERE ' . $where,
            $bindings
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function organizationFilter(int $organizationId, array $filters): array
    {
        $where = 'b.`organization_id` = ?';
        $bindings = [$organizationId];

        if (!empty($filters['status'])) {
            $where .= ' AND b.`status` = ?';
            $bindings[] = $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $where .= ' AND b.`payment_status` = ?';
            $bindings[] = $filters['payment_status'];
        }

        if (!empty($filters['car_id'])) {
            $where .= ' AND b.`car_id` = ?';
            $bindings[] = (int) $filters['car_id'];
        }

        if (!empty($filters['user_id'])) {
            $where .= ' AND b.`user_id` = ?';
            $bindings[] = (int) $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (b.`reference` LIKE ? OR u.`name` LIKE ? OR u.`email` LIKE ?
                            OR c.`brand` LIKE ? OR c.`model` LIKE ? OR c.`plate_number` LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($bindings, $term, $term, $term, $term, $term, $term);
        }

        if (!empty($filters['from'])) {
            $where .= ' AND b.`pickup_at` >= ?';
            $bindings[] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where .= ' AND b.`pickup_at` <= ?';
            $bindings[] = $filters['to'];
        }

        if (!empty($filters['overdue'])) {
            $where .= " AND b.`status` = 'active' AND b.`return_at` < ?";
            $bindings[] = $this->now();
        }

        return [$where, $bindings];
    }

    /** @return array<string,int> */
    public function statusCounts(int $organizationId): array
    {
        $rows = $this->db->select(
            'SELECT `status`, COUNT(*) AS `total` FROM `bookings` WHERE `organization_id` = ? GROUP BY `status`',
            [$organizationId]
        );

        $counts = array_fill_keys(
            ['pending', 'confirmed', 'rejected', 'ready_for_pickup', 'active', 'completed', 'cancelled', 'no_show'],
            0
        );

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    // -----------------------------------------------------------------
    // Dashboard and scheduler queries
    // -----------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function pickupsBetween(int $organizationId, string $from, string $to): array
    {
        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ', u.`name` AS `customer_name`
             ' . self::DETAIL_JOINS . '
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             WHERE b.`organization_id` = ?
               AND b.`pickup_at` >= ? AND b.`pickup_at` < ?
               AND b.`status` IN (\'confirmed\',\'ready_for_pickup\')
             ORDER BY b.`pickup_at` ASC',
            [$organizationId, $from, $to]
        );
    }

    /** @return list<array<string,mixed>> */
    public function returnsBetween(int $organizationId, string $from, string $to): array
    {
        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ', u.`name` AS `customer_name`
             ' . self::DETAIL_JOINS . '
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             WHERE b.`organization_id` = ?
               AND b.`return_at` >= ? AND b.`return_at` < ?
               AND b.`status` = \'active\'
             ORDER BY b.`return_at` ASC',
            [$organizationId, $from, $to]
        );
    }

    /** @return list<array<string,mixed>> */
    public function recentForOrganization(int $organizationId, int $limit = 6): array
    {
        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ', u.`name` AS `customer_name`
             ' . self::DETAIL_JOINS . '
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             WHERE b.`organization_id` = ?
             ORDER BY b.`created_at` DESC
             LIMIT ' . max(1, $limit),
            [$organizationId]
        );
    }

    public function countOverdue(int $organizationId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `bookings`
             WHERE `organization_id` = ? AND `status` = 'active' AND `return_at` < ?",
            [$organizationId, $this->now()]
        );
    }

    /**
     * Bookings due for a pickup reminder, across all organizations.
     *
     * @return list<array<string,mixed>>
     */
    public function dueForPickupReminder(string $from, string $to): array
    {
        return $this->db->select(
            'SELECT b.`id`, b.`reference`, b.`user_id`, b.`organization_id`, b.`pickup_at`, b.`return_at`,
                    c.`brand`, c.`model`, o.`name` AS `organization_name`
             FROM `bookings` b
             INNER JOIN `cars` c ON c.`id` = b.`car_id`
             INNER JOIN `organizations` o ON o.`id` = b.`organization_id`
             WHERE b.`status` IN (\'confirmed\',\'ready_for_pickup\')
               AND b.`pickup_at` >= ? AND b.`pickup_at` < ?
             ORDER BY b.`pickup_at` ASC',
            [$from, $to]
        );
    }

    /** @return list<array<string,mixed>> */
    public function dueForReturnReminder(string $from, string $to): array
    {
        return $this->db->select(
            'SELECT b.`id`, b.`reference`, b.`user_id`, b.`organization_id`, b.`pickup_at`, b.`return_at`,
                    c.`brand`, c.`model`, o.`name` AS `organization_name`
             FROM `bookings` b
             INNER JOIN `cars` c ON c.`id` = b.`car_id`
             INNER JOIN `organizations` o ON o.`id` = b.`organization_id`
             WHERE b.`status` = \'active\'
               AND b.`return_at` >= ? AND b.`return_at` < ?
             ORDER BY b.`return_at` ASC',
            [$from, $to]
        );
    }

    /**
     * Active bookings whose return time has passed. Overdue is derived, never
     * stored as a status.
     *
     * @return list<array<string,mixed>>
     */
    public function overdueBookings(?string $now = null): array
    {
        return $this->db->select(
            'SELECT b.`id`, b.`reference`, b.`user_id`, b.`organization_id`, b.`pickup_at`, b.`return_at`,
                    c.`brand`, c.`model`, o.`name` AS `organization_name`
             FROM `bookings` b
             INNER JOIN `cars` c ON c.`id` = b.`car_id`
             INNER JOIN `organizations` o ON o.`id` = b.`organization_id`
             WHERE b.`status` = \'active\' AND b.`return_at` < ?
             ORDER BY b.`return_at` ASC',
            [$now ?? $this->now()]
        );
    }

    /** @return list<array<string,mixed>> */
    public function overdueForOrganization(int $organizationId, int $limit = 20): array
    {
        return $this->db->select(
            'SELECT ' . self::DETAIL_SELECT . ', u.`name` AS `customer_name`
             ' . self::DETAIL_JOINS . '
             INNER JOIN `users` u ON u.`id` = b.`user_id`
             WHERE b.`organization_id` = ? AND b.`status` = \'active\' AND b.`return_at` < ?
             ORDER BY b.`return_at` ASC
             LIMIT ' . max(1, $limit),
            [$organizationId, $this->now()]
        );
    }
}
