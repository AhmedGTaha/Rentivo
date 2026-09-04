<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Organization pickup/return locations.
 *
 * Every read is scoped by organization_id; there is deliberately no
 * find-by-id-alone method on this repository.
 */
final class LocationRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function findInOrganization(int $id, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `locations` WHERE `id` = ? AND `organization_id` = ?',
            [$id, $organizationId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForOrganization(int $organizationId, bool $activeOnly = false): array
    {
        $sql = 'SELECT l.*,
                       (SELECT COUNT(*) FROM `cars` c
                        WHERE c.`location_id` = l.`id` AND c.`archived_at` IS NULL) AS `car_count`
                FROM `locations` l
                WHERE l.`organization_id` = ?';

        if ($activeOnly) {
            $sql .= ' AND l.`is_active` = 1';
        }

        $sql .= ' ORDER BY l.`name` ASC';

        return $this->db->select($sql, [$organizationId]);
    }

    /** Confirms a location id supplied by a form belongs to the organization. */
    public function belongsToOrganization(int $id, int $organizationId): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `locations` WHERE `id` = ? AND `organization_id` = ? AND `is_active` = 1',
            [$id, $organizationId]
        ) > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(int $organizationId, array $data): int
    {
        $now = $this->now();

        return $this->db->insert('locations', $data + [
            'organization_id' => $organizationId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, int $organizationId, array $data): int
    {
        $data['updated_at'] = $this->now();

        return $this->db->update('locations', $data, [
            'id'              => $id,
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Locations offered publicly on a storefront.
     *
     * @return list<array<string,mixed>>
     */
    public function publicForOrganization(int $organizationId): array
    {
        return $this->db->select(
            'SELECT `id`, `name`, `address`, `phone`, `opening_hours`, `latitude`, `longitude`
             FROM `locations`
             WHERE `organization_id` = ? AND `is_active` = 1
             ORDER BY `name` ASC',
            [$organizationId]
        );
    }

    /**
     * Distinct location names across the marketplace, for the browse filter.
     *
     * @return list<string>
     */
    public function distinctPublicNames(): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT l.`name`
             FROM `locations` l
             INNER JOIN `cars` c ON c.`location_id` = l.`id`
             INNER JOIN `organizations` o ON o.`id` = l.`organization_id`
             WHERE l.`is_active` = 1
               AND o.`is_active` = 1
               AND c.`archived_at` IS NULL
               AND c.`status` NOT IN (\'inactive\')
             ORDER BY l.`name` ASC'
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
