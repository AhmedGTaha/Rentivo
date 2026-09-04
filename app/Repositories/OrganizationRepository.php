<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\Pagination;

/**
 * Organizations (rental agencies).
 */
final class OrganizationRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM `organizations` WHERE `id` = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM `organizations` WHERE `slug` = ?', [$slug]);
    }

    /** Public storefront lookup: inactive agencies are not addressable. */
    public function findActiveBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `organizations` WHERE `slug` = ? AND `is_active` = 1',
            [$slug]
        );
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `organizations` WHERE `slug` = ?';
        $bindings = [$slug];

        if ($exceptId !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $bindings) > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('organizations', $data + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $data['updated_at'] = $this->now();

        $this->db->update('organizations', $data, ['id' => $id]);
    }

    /**
     * Active agencies with their public fleet count, for /agencies.
     *
     * @return list<array<string,mixed>>
     */
    public function listActive(Pagination $pagination, ?string $search = null): array
    {
        [$where, $bindings] = $this->activeFilter($search);

        $sql = 'SELECT o.*,
                       (SELECT COUNT(*) FROM `cars` c
                        WHERE c.`organization_id` = o.`id`
                          AND c.`archived_at` IS NULL
                          AND c.`status` NOT IN (\'inactive\')) AS `fleet_count`
                FROM `organizations` o
                WHERE ' . $where . '
                ORDER BY o.`name` ASC
                LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset();

        return $this->db->select($sql, $bindings);
    }

    public function countActive(?string $search = null): int
    {
        [$where, $bindings] = $this->activeFilter($search);

        return (int) $this->db->scalar('SELECT COUNT(*) FROM `organizations` o WHERE ' . $where, $bindings);
    }

    /**
     * Agencies to feature on the homepage, ordered by published fleet size.
     *
     * @return list<array<string,mixed>>
     */
    public function featured(int $limit = 4): array
    {
        return $this->db->select(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM `cars` c
                     WHERE c.`organization_id` = o.`id`
                       AND c.`archived_at` IS NULL
                       AND c.`status` NOT IN (\'inactive\')) AS `fleet_count`
             FROM `organizations` o
             WHERE o.`is_active` = 1
             ORDER BY `fleet_count` DESC, o.`created_at` DESC
             LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Agencies with at least one publishable car, for the browse filter.
     *
     * @return list<array<string,mixed>>
     */
    public function selectableForFilters(): array
    {
        return $this->db->select(
            'SELECT DISTINCT o.`id`, o.`name`, o.`slug`
             FROM `organizations` o
             INNER JOIN `cars` c ON c.`organization_id` = o.`id`
             WHERE o.`is_active` = 1
               AND c.`archived_at` IS NULL
               AND c.`status` NOT IN (\'inactive\')
             ORDER BY o.`name` ASC'
        );
    }

    /** @return array{0:string,1:list<mixed>} */
    private function activeFilter(?string $search): array
    {
        $where = 'o.`is_active` = 1';
        $bindings = [];

        if ($search !== null && $search !== '') {
            $where .= ' AND (o.`name` LIKE ? OR o.`description` LIKE ? OR o.`address` LIKE ?)';
            $term = '%' . $search . '%';
            $bindings = [$term, $term, $term];
        }

        return [$where, $bindings];
    }
}
