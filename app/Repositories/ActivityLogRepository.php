<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\Pagination;

/**
 * Append-only audit trail.
 *
 * There is deliberately no update or delete method: audit entries are
 * immutable through the application.
 */
final class ActivityLogRepository extends Repository
{
    /**
     * @param array<string,mixed>|null $metadata
     */
    public function record(
        ?int $organizationId,
        ?int $actorUserId,
        string $actionKey,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?string $ipAddress = null
    ): int {
        return $this->db->insert('activity_logs', [
            'organization_id' => $organizationId,
            'actor_user_id'   => $actorUserId,
            'action_key'      => $actionKey,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'metadata_json'   => $metadata === null
                ? null
                : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip_address'      => $ipAddress,
            'created_at'      => $this->now(),
        ]);
    }

    /**
     * @param array{search?:string,action?:string,actor_user_id?:int} $filters
     * @return list<array<string,mixed>>
     */
    public function listForOrganization(int $organizationId, array $filters, Pagination $pagination): array
    {
        [$where, $bindings] = $this->filter($organizationId, $filters);

        return $this->db->select(
            'SELECT al.*, u.`name` AS `actor_name`, u.`email` AS `actor_email`,
                    u.`google_avatar_url` AS `actor_avatar`
             FROM `activity_logs` al
             LEFT JOIN `users` u ON u.`id` = al.`actor_user_id`
             WHERE ' . $where . '
             ORDER BY al.`created_at` DESC, al.`id` DESC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    /** @param array<string,mixed> $filters */
    public function countForOrganization(int $organizationId, array $filters): int
    {
        [$where, $bindings] = $this->filter($organizationId, $filters);

        return (int) $this->db->scalar(
            'SELECT COUNT(*)
             FROM `activity_logs` al
             LEFT JOIN `users` u ON u.`id` = al.`actor_user_id`
             WHERE ' . $where,
            $bindings
        );
    }

    /** @return array{0:string,1:list<mixed>} */
    private function filter(int $organizationId, array $filters): array
    {
        $where = 'al.`organization_id` = ?';
        $bindings = [$organizationId];

        if (!empty($filters['action'])) {
            $where .= ' AND al.`action_key` = ?';
            $bindings[] = $filters['action'];
        }

        if (!empty($filters['actor_user_id'])) {
            $where .= ' AND al.`actor_user_id` = ?';
            $bindings[] = (int) $filters['actor_user_id'];
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (al.`action_key` LIKE ? OR u.`name` LIKE ? OR al.`entity_type` LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($bindings, $term, $term, $term);
        }

        return [$where, $bindings];
    }

    /** @return list<array<string,mixed>> */
    public function recentForOrganization(int $organizationId, int $limit = 8): array
    {
        return $this->db->select(
            'SELECT al.*, u.`name` AS `actor_name`, u.`google_avatar_url` AS `actor_avatar`
             FROM `activity_logs` al
             LEFT JOIN `users` u ON u.`id` = al.`actor_user_id`
             WHERE al.`organization_id` = ?
             ORDER BY al.`created_at` DESC, al.`id` DESC
             LIMIT ' . max(1, $limit),
            [$organizationId]
        );
    }

    /** @return list<string> Distinct action keys, for the activity filter. */
    public function distinctActions(int $organizationId): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT `action_key` FROM `activity_logs` WHERE `organization_id` = ? ORDER BY `action_key`',
            [$organizationId]
        );

        return array_map(static fn (array $row): string => (string) $row['action_key'], $rows);
    }

    /** @return list<array<string,mixed>> Timeline for one entity. */
    public function forEntity(int $organizationId, string $entityType, int $entityId): array
    {
        return $this->db->select(
            'SELECT al.*, u.`name` AS `actor_name`
             FROM `activity_logs` al
             LEFT JOIN `users` u ON u.`id` = al.`actor_user_id`
             WHERE al.`organization_id` = ? AND al.`entity_type` = ? AND al.`entity_id` = ?
             ORDER BY al.`created_at` ASC, al.`id` ASC',
            [$organizationId, $entityType, $entityId]
        );
    }
}
