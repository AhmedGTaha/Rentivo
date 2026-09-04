<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

/**
 * Employee invitations.
 *
 * Only the SHA-256 hash of an invitation token is ever persisted, so a
 * database read cannot reconstruct a usable invitation URL.
 */
final class InvitationRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function findByTokenHash(string $tokenHash): ?array
    {
        return $this->db->selectOne(
            'SELECT i.*, o.`name` AS `organization_name`, o.`slug` AS `organization_slug`,
                    o.`logo_path` AS `organization_logo_path`
             FROM `employee_invitations` i
             INNER JOIN `organizations` o ON o.`id` = i.`organization_id`
             WHERE i.`token_hash` = ?',
            [$tokenHash]
        );
    }

    /** Tenant-scoped lookup for management screens. */
    public function findInOrganization(int $invitationId, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `employee_invitations` WHERE `id` = ? AND `organization_id` = ?',
            [$invitationId, $organizationId]
        );
    }

    /** An outstanding (not accepted, not revoked, not expired) invitation. */
    public function findPendingForEmail(int $organizationId, string $email): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `employee_invitations`
             WHERE `organization_id` = ?
               AND `email` = ?
               AND `accepted_at` IS NULL
               AND `revoked_at` IS NULL
               AND `expires_at` > ?
             ORDER BY `id` DESC
             LIMIT 1',
            [$organizationId, strtolower($email), $this->now()]
        );
    }

    public function create(
        int $organizationId,
        string $email,
        string $tokenHash,
        int $invitedByUserId,
        string $expiresAt
    ): int {
        $now = $this->now();

        return $this->db->insert('employee_invitations', [
            'organization_id'    => $organizationId,
            'email'              => strtolower($email),
            'token_hash'         => $tokenHash,
            'invited_by_user_id' => $invitedByUserId,
            'expires_at'         => $expiresAt,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }

    /** @param list<int> $permissionIds */
    public function setPermissions(int $invitationId, array $permissionIds): void
    {
        $this->db->delete('employee_invitation_permissions', ['invitation_id' => $invitationId]);

        $now = $this->now();

        foreach (array_unique($permissionIds) as $permissionId) {
            $this->db->insert('employee_invitation_permissions', [
                'invitation_id' => $invitationId,
                'permission_id' => $permissionId,
                'created_at'    => $now,
            ]);
        }
    }

    /** @return list<int> */
    public function permissionIds(int $invitationId): array
    {
        $rows = $this->db->select(
            'SELECT `permission_id` FROM `employee_invitation_permissions` WHERE `invitation_id` = ?',
            [$invitationId]
        );

        return array_map(static fn (array $row): int => (int) $row['permission_id'], $rows);
    }

    /** @return list<string> */
    public function permissionKeys(int $invitationId): array
    {
        $rows = $this->db->select(
            'SELECT p.`key`
             FROM `employee_invitation_permissions` ip
             INNER JOIN `permissions` p ON p.`id` = ip.`permission_id`
             WHERE ip.`invitation_id` = ?
             ORDER BY p.`key`',
            [$invitationId]
        );

        return array_map(static fn (array $row): string => (string) $row['key'], $rows);
    }

    /**
     * Marks an invitation accepted.
     *
     * The `accepted_at IS NULL` predicate makes this a single-use operation:
     * a replayed request updates zero rows and the caller aborts.
     */
    public function markAccepted(int $invitationId, int $userId): int
    {
        $now = $this->now();

        return $this->db->affectingStatement(
            'UPDATE `employee_invitations`
             SET `accepted_at` = ?, `accepted_by_user_id` = ?, `updated_at` = ?
             WHERE `id` = ? AND `accepted_at` IS NULL AND `revoked_at` IS NULL',
            [$now, $userId, $now, $invitationId]
        );
    }

    public function revoke(int $invitationId, int $organizationId): int
    {
        $now = $this->now();

        return $this->db->affectingStatement(
            'UPDATE `employee_invitations`
             SET `revoked_at` = ?, `updated_at` = ?
             WHERE `id` = ? AND `organization_id` = ? AND `accepted_at` IS NULL AND `revoked_at` IS NULL',
            [$now, $now, $invitationId, $organizationId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listPending(int $organizationId): array
    {
        return $this->db->select(
            'SELECT i.*, u.`name` AS `invited_by_name`
             FROM `employee_invitations` i
             LEFT JOIN `users` u ON u.`id` = i.`invited_by_user_id`
             WHERE i.`organization_id` = ?
               AND i.`accepted_at` IS NULL
               AND i.`revoked_at` IS NULL
             ORDER BY i.`created_at` DESC',
            [$organizationId]
        );
    }
}
