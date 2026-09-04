<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Organization-owned car categories. Categories are always database managed —
 * nothing in the application hard-codes a category list.
 */
final class CategoryRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function findInOrganization(int $id, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `car_categories` WHERE `id` = ? AND `organization_id` = ?',
            [$id, $organizationId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForOrganization(int $organizationId): array
    {
        return $this->db->select(
            'SELECT cc.*,
                    (SELECT COUNT(*) FROM `cars` c
                     WHERE c.`category_id` = cc.`id` AND c.`archived_at` IS NULL) AS `car_count`
             FROM `car_categories` cc
             WHERE cc.`organization_id` = ?
             ORDER BY cc.`name` ASC',
            [$organizationId]
        );
    }

    public function belongsToOrganization(int $id, int $organizationId): bool
    {
        return $this->findInOrganization($id, $organizationId) !== null;
    }

    public function nameExists(int $organizationId, string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `car_categories` WHERE `organization_id` = ? AND `name` = ?';
        $bindings = [$organizationId, $name];

        if ($exceptId !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $bindings) > 0;
    }

    public function slugExists(int $organizationId, string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `car_categories` WHERE `organization_id` = ? AND `slug` = ?';
        $bindings = [$organizationId, $slug];

        if ($exceptId !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $bindings) > 0;
    }

    public function create(int $organizationId, string $name, string $slug): int
    {
        $now = $this->now();

        return $this->db->insert('car_categories', [
            'organization_id' => $organizationId,
            'name'            => $name,
            'slug'            => $slug,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    public function update(int $id, int $organizationId, string $name, string $slug): int
    {
        return $this->db->update('car_categories', [
            'name'       => $name,
            'slug'       => $slug,
            'updated_at' => $this->now(),
        ], [
            'id'              => $id,
            'organization_id' => $organizationId,
        ]);
    }

    /** Categories are only removable while nothing references them. */
    public function delete(int $id, int $organizationId): int
    {
        $inUse = (int) $this->db->scalar('SELECT COUNT(*) FROM `cars` WHERE `category_id` = ?', [$id]);

        if ($inUse > 0) {
            return 0;
        }

        return $this->db->affectingStatement(
            'DELETE FROM `car_categories` WHERE `id` = ? AND `organization_id` = ?',
            [$id, $organizationId]
        );
    }

    /**
     * Category names in use across the public marketplace, for filters.
     *
     * @return list<string>
     */
    public function distinctPublicNames(): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT cc.`name`
             FROM `car_categories` cc
             INNER JOIN `cars` c ON c.`category_id` = cc.`id`
             INNER JOIN `organizations` o ON o.`id` = cc.`organization_id`
             WHERE o.`is_active` = 1
               AND c.`archived_at` IS NULL
               AND c.`status` NOT IN (\'inactive\')
             ORDER BY cc.`name` ASC'
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
