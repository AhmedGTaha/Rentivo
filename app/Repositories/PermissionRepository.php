<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * The permission catalogue. Rows are created by the seeder from
 * Rentivo\Security\Permissions, which is the authoritative key list.
 */
final class PermissionRepository extends Repository
{
    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select('SELECT * FROM `permissions` ORDER BY `id`');
    }

    /** @return array<string,int> key => id */
    public function idsByKey(): array
    {
        $map = [];

        foreach ($this->all() as $row) {
            $map[(string) $row['key']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * Resolves permission keys to ids, silently dropping unknown keys so a
     * tampered form can never grant something outside the catalogue.
     *
     * @param list<string> $keys
     * @return list<int>
     */
    public function idsForKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT `id` FROM `permissions` WHERE `key` IN (' . $this->placeholders($keys) . ')',
            array_values($keys)
        );

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $key): ?array
    {
        return $this->db->selectOne('SELECT * FROM `permissions` WHERE `key` = ?', [$key]);
    }

    public function upsert(string $key, string $groupKey, string $label): void
    {
        $this->db->statement(
            'INSERT INTO `permissions` (`key`, `group_key`, `label`, `created_at`)
             VALUES (:key, :group_key, :label, :created_at)
             ON DUPLICATE KEY UPDATE `group_key` = :group_key2, `label` = :label2',
            [
                'key'        => $key,
                'group_key'  => $groupKey,
                'label'      => $label,
                'created_at' => $this->now(),
                'group_key2' => $groupKey,
                'label2'     => $label,
            ]
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM `permissions`');
    }
}
