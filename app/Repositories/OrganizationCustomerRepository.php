<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\Pagination;

/**
 * The per-organization view of a customer, created on their first booking
 * with that agency. Internal notes stored here are never exposed to the
 * customer.
 */
final class OrganizationCustomerRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function find(int $organizationId, int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `organization_customers` WHERE `organization_id` = ? AND `user_id` = ?',
            [$organizationId, $userId]
        );
    }

    /** Tenant-scoped lookup by the relationship's own id. */
    public function findInOrganization(int $id, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT oc.*, u.`name`, u.`email`, u.`google_avatar_url`, p.`phone`, p.`nationality`,
                    p.`date_of_birth`, p.`address`
             FROM `organization_customers` oc
             INNER JOIN `users` u ON u.`id` = oc.`user_id`
             LEFT JOIN `user_profiles` p ON p.`user_id` = oc.`user_id`
             WHERE oc.`id` = ? AND oc.`organization_id` = ?',
            [$id, $organizationId]
        );
    }

    /** True when the user has ever transacted with this organization. */
    public function relationshipExists(int $organizationId, int $userId): bool
    {
        return $this->find($organizationId, $userId) !== null;
    }

    /** Creates the relationship if it does not already exist. */
    public function ensure(int $organizationId, int $userId): int
    {
        $existing = $this->find($organizationId, $userId);

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $now = $this->now();

        $this->db->statement(
            'INSERT INTO `organization_customers` (`organization_id`, `user_id`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `updated_at` = VALUES(`updated_at`)',
            [$organizationId, $userId, $now, $now]
        );

        $row = $this->find($organizationId, $userId);

        return $row === null ? 0 : (int) $row['id'];
    }

    public function updateNotes(int $id, int $organizationId, ?string $notes): int
    {
        return $this->db->update('organization_customers', [
            'internal_notes' => $notes,
            'updated_at'     => $this->now(),
        ], [
            'id'              => $id,
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Customer list with per-organization booking counts.
     *
     * @return list<array<string,mixed>>
     */
    public function listForOrganization(int $organizationId, ?string $search, Pagination $pagination): array
    {
        [$where, $bindings] = $this->filter($organizationId, $search);

        return $this->db->select(
            'SELECT oc.*, u.`name`, u.`email`, u.`google_avatar_url`, p.`phone`,
                    (SELECT COUNT(*) FROM `bookings` b
                     WHERE b.`organization_id` = oc.`organization_id` AND b.`user_id` = oc.`user_id`) AS `total_bookings`,
                    (SELECT COUNT(*) FROM `bookings` b
                     WHERE b.`organization_id` = oc.`organization_id` AND b.`user_id` = oc.`user_id`
                       AND b.`status` IN (\'confirmed\',\'ready_for_pickup\',\'active\')) AS `active_bookings`
             FROM `organization_customers` oc
             INNER JOIN `users` u ON u.`id` = oc.`user_id`
             LEFT JOIN `user_profiles` p ON p.`user_id` = oc.`user_id`
             WHERE ' . $where . '
             ORDER BY u.`name` ASC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    public function countForOrganization(int $organizationId, ?string $search): int
    {
        [$where, $bindings] = $this->filter($organizationId, $search);

        return (int) $this->db->scalar(
            'SELECT COUNT(*)
             FROM `organization_customers` oc
             INNER JOIN `users` u ON u.`id` = oc.`user_id`
             LEFT JOIN `user_profiles` p ON p.`user_id` = oc.`user_id`
             WHERE ' . $where,
            $bindings
        );
    }

    /** @return array{0:string,1:list<mixed>} */
    private function filter(int $organizationId, ?string $search): array
    {
        $where = 'oc.`organization_id` = ?';
        $bindings = [$organizationId];

        if ($search !== null && $search !== '') {
            $where .= ' AND (u.`name` LIKE ? OR u.`email` LIKE ? OR p.`phone` LIKE ?)';
            $term = '%' . $search . '%';
            array_push($bindings, $term, $term, $term);
        }

        return [$where, $bindings];
    }

    /** @return array{total:int,active:int,completed:int,cancelled:int} */
    public function bookingSummary(int $organizationId, int $userId): array
    {
        $row = $this->db->selectOne(
            'SELECT
                COUNT(*) AS `total`,
                SUM(`status` IN (\'confirmed\',\'ready_for_pickup\',\'active\')) AS `active`,
                SUM(`status` = \'completed\') AS `completed`,
                SUM(`status` IN (\'cancelled\',\'rejected\',\'no_show\')) AS `cancelled`
             FROM `bookings`
             WHERE `organization_id` = ? AND `user_id` = ?',
            [$organizationId, $userId]
        );

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'active'    => (int) ($row['active'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }
}
