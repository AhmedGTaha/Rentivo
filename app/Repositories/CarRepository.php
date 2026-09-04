<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\DateTimeHelper;
use Rentivo\Support\Pagination;

/**
 * Fleet reads and writes.
 *
 * Public reads and management reads are deliberately separate methods:
 * management methods always take an organization id, and public methods
 * always apply the publishable predicate (not archived, not inactive, agency
 * active).
 */
final class CarRepository extends Repository
{
    /** Booking statuses that reserve a car for a period of time. */
    public const BLOCKING_STATUSES = ['confirmed', 'ready_for_pickup', 'active'];

    /** Car statuses that can never be rented, regardless of dates. */
    public const UNRENTABLE_STATUSES = ['maintenance', 'inactive'];

    private const PUBLIC_PREDICATE = "c.`archived_at` IS NULL
        AND c.`status` NOT IN ('inactive')
        AND o.`is_active` = 1";

    // -----------------------------------------------------------------
    // Management (tenant-scoped)
    // -----------------------------------------------------------------

    /**
     * Tenant-scoped car lookup.
     *
     * This is the only way management code loads a car: the organization
     * predicate is part of the query, never checked afterwards.
     *
     * @return array<string,mixed>|null
     */
    public function findInOrganization(int $id, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT c.*, cc.`name` AS `category_name`, l.`name` AS `location_name`
             FROM `cars` c
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             LEFT JOIN `locations` l ON l.`id` = c.`location_id`
             WHERE c.`id` = ? AND c.`organization_id` = ?',
            [$id, $organizationId]
        );
    }

    /** Locks a car row for the duration of the current transaction. */
    public function lockForUpdate(int $id, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `cars` WHERE `id` = ? AND `organization_id` = ? FOR UPDATE',
            [$id, $organizationId]
        );
    }

    /**
     * Management fleet listing with search, status and category filters.
     *
     * @param array{search?:string,status?:string,category_id?:int,include_archived?:bool} $filters
     * @return list<array<string,mixed>>
     */
    public function listForOrganization(int $organizationId, array $filters, Pagination $pagination): array
    {
        [$where, $bindings] = $this->managementFilter($organizationId, $filters);

        return $this->db->select(
            'SELECT c.*, cc.`name` AS `category_name`, l.`name` AS `location_name`,
                    (SELECT ci.`file_path` FROM `car_images` ci
                     WHERE ci.`car_id` = c.`id`
                     ORDER BY ci.`is_primary` DESC, ci.`display_order` ASC, ci.`id` ASC
                     LIMIT 1) AS `primary_image`
             FROM `cars` c
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             LEFT JOIN `locations` l ON l.`id` = c.`location_id`
             WHERE ' . $where . '
             ORDER BY c.`created_at` DESC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    /** @param array<string,mixed> $filters */
    public function countForOrganization(int $organizationId, array $filters): int
    {
        [$where, $bindings] = $this->managementFilter($organizationId, $filters);

        return (int) $this->db->scalar('SELECT COUNT(*) FROM `cars` c WHERE ' . $where, $bindings);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function managementFilter(int $organizationId, array $filters): array
    {
        $where = 'c.`organization_id` = ?';
        $bindings = [$organizationId];

        if (empty($filters['include_archived'])) {
            $where .= ' AND c.`archived_at` IS NULL';
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (c.`brand` LIKE ? OR c.`model` LIKE ? OR c.`plate_number` LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($bindings, $term, $term, $term);
        }

        if (!empty($filters['status'])) {
            $where .= ' AND c.`status` = ?';
            $bindings[] = $filters['status'];
        }

        if (!empty($filters['category_id'])) {
            $where .= ' AND c.`category_id` = ?';
            $bindings[] = (int) $filters['category_id'];
        }

        return [$where, $bindings];
    }

    /** Cars selectable in management dropdowns (e.g. booking filters). */
    public function simpleListForOrganization(int $organizationId): array
    {
        return $this->db->select(
            'SELECT `id`, `brand`, `model`, `year`, `plate_number`
             FROM `cars`
             WHERE `organization_id` = ?
             ORDER BY `brand`, `model`',
            [$organizationId]
        );
    }

    public function slugExists(int $organizationId, string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `cars` WHERE `organization_id` = ? AND `slug` = ?';
        $bindings = [$organizationId, $slug];

        if ($exceptId !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $bindings) > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(int $organizationId, array $data): int
    {
        $now = $this->now();

        return $this->db->insert('cars', $data + [
            'organization_id' => $organizationId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, int $organizationId, array $data): int
    {
        $data['updated_at'] = $this->now();

        return $this->db->update('cars', $data, [
            'id'              => $id,
            'organization_id' => $organizationId,
        ]);
    }

    public function setStatus(int $id, int $organizationId, string $status): int
    {
        return $this->update($id, $organizationId, ['status' => $status]);
    }

    public function archive(int $id, int $organizationId): int
    {
        return $this->update($id, $organizationId, [
            'archived_at' => $this->now(),
            'status'      => 'inactive',
        ]);
    }

    public function restore(int $id, int $organizationId): int
    {
        return $this->update($id, $organizationId, [
            'archived_at' => null,
            'status'      => 'available',
        ]);
    }

    /** @return array<string,int> status => count */
    public function statusCounts(int $organizationId): array
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

    // -----------------------------------------------------------------
    // Public marketplace
    // -----------------------------------------------------------------

    /**
     * Public car lookup by global slug.
     *
     * @return array<string,mixed>|null
     */
    public function findPublicBySlug(string $slug, ?int $organizationId = null): ?array
    {
        $sql = 'SELECT c.*,
                       o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
                       o.`logo_path` AS `organization_logo_path`, o.`primary_color` AS `organization_color`,
                       o.`rental_terms` AS `organization_rental_terms`,
                       o.`contact_email` AS `organization_contact_email`, o.`phone` AS `organization_phone`,
                       cc.`name` AS `category_name`,
                       l.`name` AS `location_name`, l.`address` AS `location_address`,
                       l.`opening_hours` AS `location_opening_hours`
                FROM `cars` c
                INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
                LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
                LEFT JOIN `locations` l ON l.`id` = c.`location_id`
                WHERE c.`slug` = ? AND ' . self::PUBLIC_PREDICATE;

        $bindings = [$slug];

        if ($organizationId !== null) {
            $sql .= ' AND c.`organization_id` = ?';
            $bindings[] = $organizationId;
        }

        $sql .= ' ORDER BY c.`id` ASC LIMIT 1';

        return $this->db->selectOne($sql, $bindings);
    }

    /**
     * The public browse query.
     *
     * @return list<array<string,mixed>>
     */
    public function searchPublic(CarFilters $filters, Pagination $pagination): array
    {
        [$where, $bindings] = $this->publicFilter($filters);

        $order = match ($filters->sort) {
            CarFilters::SORT_PRICE_ASC  => 'c.`daily_rate_fils` ASC, c.`id` DESC',
            CarFilters::SORT_PRICE_DESC => 'c.`daily_rate_fils` DESC, c.`id` DESC',
            CarFilters::SORT_POPULAR    => '`booking_count` DESC, c.`created_at` DESC',
            default                     => 'c.`created_at` DESC, c.`id` DESC',
        };

        return $this->db->select(
            'SELECT c.*,
                    o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
                    o.`logo_path` AS `organization_logo_path`, o.`primary_color` AS `organization_color`,
                    cc.`name` AS `category_name`,
                    l.`name` AS `location_name`,
                    (SELECT ci.`file_path` FROM `car_images` ci
                     WHERE ci.`car_id` = c.`id`
                     ORDER BY ci.`is_primary` DESC, ci.`display_order` ASC, ci.`id` ASC
                     LIMIT 1) AS `primary_image`,
                    (SELECT COUNT(*) FROM `bookings` b WHERE b.`car_id` = c.`id`) AS `booking_count`
             FROM `cars` c
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             LEFT JOIN `locations` l ON l.`id` = c.`location_id`
             WHERE ' . $where . '
             ORDER BY ' . $order . '
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    public function countPublic(CarFilters $filters): int
    {
        [$where, $bindings] = $this->publicFilter($filters);

        return (int) $this->db->scalar(
            'SELECT COUNT(*)
             FROM `cars` c
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             LEFT JOIN `locations` l ON l.`id` = c.`location_id`
             WHERE ' . $where,
            $bindings
        );
    }

    /**
     * Builds the shared WHERE clause for public browsing, including the
     * date-based availability exclusion.
     *
     * @return array{0:string,1:list<mixed>}
     */
    private function publicFilter(CarFilters $filters): array
    {
        $where = self::PUBLIC_PREDICATE;
        $bindings = [];

        if ($filters->organizationId !== null) {
            $where .= ' AND c.`organization_id` = ?';
            $bindings[] = $filters->organizationId;
        }

        if ($filters->search !== '') {
            $where .= ' AND (c.`brand` LIKE ? OR c.`model` LIKE ? OR CONCAT(c.`brand`, \' \', c.`model`) LIKE ? OR o.`name` LIKE ?)';
            $term = '%' . $filters->search . '%';
            array_push($bindings, $term, $term, $term, $term);
        }

        if ($filters->agency !== '') {
            $where .= ' AND o.`slug` = ?';
            $bindings[] = $filters->agency;
        }

        if ($filters->category !== '') {
            $where .= ' AND cc.`name` = ?';
            $bindings[] = $filters->category;
        }

        if ($filters->brand !== '') {
            $where .= ' AND c.`brand` = ?';
            $bindings[] = $filters->brand;
        }

        if ($filters->model !== '') {
            $where .= ' AND c.`model` LIKE ?';
            $bindings[] = '%' . $filters->model . '%';
        }

        if ($filters->year !== null) {
            $where .= ' AND c.`year` = ?';
            $bindings[] = $filters->year;
        }

        if ($filters->minPriceFils !== null) {
            $where .= ' AND c.`daily_rate_fils` >= ?';
            $bindings[] = $filters->minPriceFils;
        }

        if ($filters->maxPriceFils !== null) {
            $where .= ' AND c.`daily_rate_fils` <= ?';
            $bindings[] = $filters->maxPriceFils;
        }

        if ($filters->transmission !== '') {
            $where .= ' AND c.`transmission` = ?';
            $bindings[] = $filters->transmission;
        }

        if ($filters->fuelType !== '') {
            $where .= ' AND c.`fuel_type` = ?';
            $bindings[] = $filters->fuelType;
        }

        if ($filters->minSeats !== null) {
            $where .= ' AND c.`seats` >= ?';
            $bindings[] = $filters->minSeats;
        }

        if ($filters->location !== '') {
            $where .= ' AND l.`name` = ?';
            $bindings[] = $filters->location;
        }

        if ($filters->hasDateWindow()) {
            // Operationally unavailable cars can never be booked...
            $where .= ' AND c.`status` NOT IN (' . $this->placeholders(self::UNRENTABLE_STATUSES) . ')';
            array_push($bindings, ...self::UNRENTABLE_STATUSES);

            // ...and a car is excluded only if a *blocking* booking actually
            // overlaps the requested window. A car currently rented is still
            // offered for a later period once its rental ends.
            $where .= ' AND NOT EXISTS (
                SELECT 1 FROM `bookings` b
                WHERE b.`car_id` = c.`id`
                  AND b.`status` IN (' . $this->placeholders(self::BLOCKING_STATUSES) . ')
                  AND b.`pickup_at` < ?
                  AND b.`return_at` > ?
            )';
            array_push($bindings, ...self::BLOCKING_STATUSES);
            $bindings[] = DateTimeHelper::toDb($filters->returnAt);
            $bindings[] = DateTimeHelper::toDb($filters->pickupAt);
        }

        return [$where, $bindings];
    }

    /**
     * Cars for the homepage "featured fleet" strip.
     *
     * @return list<array<string,mixed>>
     */
    public function featuredPublic(int $limit = 6): array
    {
        return $this->db->select(
            'SELECT c.*,
                    o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
                    cc.`name` AS `category_name`,
                    (SELECT ci.`file_path` FROM `car_images` ci
                     WHERE ci.`car_id` = c.`id`
                     ORDER BY ci.`is_primary` DESC, ci.`display_order` ASC, ci.`id` ASC
                     LIMIT 1) AS `primary_image`
             FROM `cars` c
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             WHERE ' . self::PUBLIC_PREDICATE . '
               AND c.`status` = \'available\'
             ORDER BY c.`created_at` DESC
             LIMIT ' . max(1, $limit)
        );
    }

    /** @return list<string> Brands present in the public marketplace. */
    public function distinctPublicBrands(): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT c.`brand`
             FROM `cars` c
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             WHERE ' . self::PUBLIC_PREDICATE . '
             ORDER BY c.`brand` ASC'
        );

        return array_map(static fn (array $row): string => (string) $row['brand'], $rows);
    }

    /**
     * Similar cars shown on a detail page (same category or same agency).
     *
     * @return list<array<string,mixed>>
     */
    public function relatedPublic(int $carId, int $organizationId, ?int $categoryId, int $limit = 3): array
    {
        return $this->db->select(
            'SELECT c.*,
                    o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
                    cc.`name` AS `category_name`,
                    (SELECT ci.`file_path` FROM `car_images` ci
                     WHERE ci.`car_id` = c.`id`
                     ORDER BY ci.`is_primary` DESC, ci.`display_order` ASC, ci.`id` ASC
                     LIMIT 1) AS `primary_image`
             FROM `cars` c
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             WHERE ' . self::PUBLIC_PREDICATE . '
               AND c.`id` <> ?
               AND (c.`organization_id` = ? OR c.`category_id` <=> ?)
             ORDER BY (c.`organization_id` = ?) DESC, c.`created_at` DESC
             LIMIT ' . max(1, $limit),
            [$carId, $organizationId, $categoryId, $organizationId]
        );
    }

    public function countPublicForOrganization(int $organizationId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*)
             FROM `cars` c
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             WHERE ' . self::PUBLIC_PREDICATE . ' AND c.`organization_id` = ?',
            [$organizationId]
        );
    }
}
