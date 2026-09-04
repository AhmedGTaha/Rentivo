<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\Pagination;

/**
 * In-app notifications.
 *
 * dedupe_key is unique, which is what makes the hourly scheduler idempotent:
 * inserting the same reminder twice simply affects zero rows.
 */
final class NotificationRepository extends Repository
{
    /**
     * @param array<string,mixed> $data
     * @return int Row id, or 0 when a duplicate dedupe_key suppressed it.
     */
    public function create(array $data): int
    {
        $data['created_at'] ??= $this->now();

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $this->db->statement(
            'INSERT IGNORE INTO `notifications` (`' . implode('`, `', $columns) . '`)
             VALUES (' . implode(', ', $placeholders) . ')',
            $data
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId, Pagination $pagination, bool $unreadOnly = false): array
    {
        $sql = 'SELECT n.*, o.`name` AS `organization_name`, o.`slug` AS `organization_slug`
                FROM `notifications` n
                LEFT JOIN `organizations` o ON o.`id` = n.`organization_id`
                WHERE n.`user_id` = ?';

        if ($unreadOnly) {
            $sql .= ' AND n.`read_at` IS NULL';
        }

        $sql .= ' ORDER BY n.`created_at` DESC
                  LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset();

        return $this->db->select($sql, [$userId]);
    }

    public function countForUser(int $userId, bool $unreadOnly = false): int
    {
        $sql = 'SELECT COUNT(*) FROM `notifications` WHERE `user_id` = ?';

        if ($unreadOnly) {
            $sql .= ' AND `read_at` IS NULL';
        }

        return (int) $this->db->scalar($sql, [$userId]);
    }

    public function unreadCount(int $userId): int
    {
        return $this->countForUser($userId, true);
    }

    /** @return list<array<string,mixed>> */
    public function recentForUser(int $userId, int $limit = 5): array
    {
        return $this->db->select(
            'SELECT * FROM `notifications` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    /** Owner-scoped: a user can only ever mark their own notification read. */
    public function markRead(int $id, int $userId): int
    {
        return $this->db->affectingStatement(
            'UPDATE `notifications` SET `read_at` = ? WHERE `id` = ? AND `user_id` = ? AND `read_at` IS NULL',
            [$this->now(), $id, $userId]
        );
    }

    public function markAllRead(int $userId): int
    {
        return $this->db->affectingStatement(
            'UPDATE `notifications` SET `read_at` = ? WHERE `user_id` = ? AND `read_at` IS NULL',
            [$this->now(), $userId]
        );
    }

    public function dedupeKeyExists(string $dedupeKey): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `notifications` WHERE `dedupe_key` = ?',
            [$dedupeKey]
        ) > 0;
    }
}
