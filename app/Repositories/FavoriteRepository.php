<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\Pagination;

/**
 * Saved cars, unique per (user, car).
 */
final class FavoriteRepository extends Repository
{
    public function exists(int $userId, int $carId): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `favorites` WHERE `user_id` = ? AND `car_id` = ?',
            [$userId, $carId]
        ) > 0;
    }

    /** Idempotent: re-adding an existing favorite is a no-op. */
    public function add(int $userId, int $carId): void
    {
        $this->db->statement(
            'INSERT IGNORE INTO `favorites` (`user_id`, `car_id`, `created_at`) VALUES (?, ?, ?)',
            [$userId, $carId, $this->now()]
        );
    }

    public function remove(int $userId, int $carId): int
    {
        return $this->db->affectingStatement(
            'DELETE FROM `favorites` WHERE `user_id` = ? AND `car_id` = ?',
            [$userId, $carId]
        );
    }

    /** @return list<int> Car ids the user has saved, for badge state. */
    public function carIdsFor(int $userId): array
    {
        $rows = $this->db->select('SELECT `car_id` FROM `favorites` WHERE `user_id` = ?', [$userId]);

        return array_map(static fn (array $row): int => (int) $row['car_id'], $rows);
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId, Pagination $pagination): array
    {
        return $this->db->select(
            'SELECT c.*,
                    o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
                    cc.`name` AS `category_name`,
                    f.`created_at` AS `favorited_at`,
                    (SELECT ci.`file_path` FROM `car_images` ci
                     WHERE ci.`car_id` = c.`id`
                     ORDER BY ci.`is_primary` DESC, ci.`display_order` ASC, ci.`id` ASC
                     LIMIT 1) AS `primary_image`
             FROM `favorites` f
             INNER JOIN `cars` c ON c.`id` = f.`car_id`
             INNER JOIN `organizations` o ON o.`id` = c.`organization_id`
             LEFT JOIN `car_categories` cc ON cc.`id` = c.`category_id`
             WHERE f.`user_id` = ?
             ORDER BY f.`created_at` DESC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            [$userId]
        );
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM `favorites` WHERE `user_id` = ?', [$userId]);
    }
}
