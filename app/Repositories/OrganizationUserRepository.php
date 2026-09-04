<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Security\OrganizationContext;

/**
 * Organization membership and the explicit permissions attached to employees.
 *
 * Every management authorization decision starts from findMembership(): if
 * there is no row, the user has no relationship with that organization at all.
 */
final class OrganizationUserRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function findMembership(int $organizationId, int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `organization_users` WHERE `organization_id` = ? AND `user_id` = ?',
            [$organizationId, $userId]
        );
    }

    /**
     * Tenant-scoped member lookup by id.
     *
     * The organization_id predicate is what stops an admin of agency A from
     * addressing a membership row belonging to agency B.
     *
     * @return array<string,mixed>|null
     */
    public function findInOrganization(int $memberId, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT ou.*, u.`name`, u.`email`, u.`google_avatar_url`
             FROM `organization_users` ou
             INNER JOIN `users` u ON u.`id` = ou.`user_id`
             WHERE ou.`id` = ? AND ou.`organization_id` = ?',
            [$memberId, $organizationId]
        );
    }

    public function exists(int $organizationId, int $userId): bool
    {
        return $this->findMembership($organizationId, $userId) !== null;
    }

    public function create(int $organizationId, int $userId, string $role): int
    {
        return $this->db->insert('organization_users', [
            'organization_id' => $organizationId,
            'user_id'         => $userId,
            'role'            => $role,
            'created_at'      => $this->now(),
        ]);
    }

    public function remove(int $memberId, int $organizationId): int
    {
        return $this->db->affectingStatement(
            'DELETE FROM `organization_users` WHERE `id` = ? AND `organization_id` = ?',
            [$memberId, $organizationId]
        );
    }

    /**
     * Members of one organization with their permission counts.
     *
     * @return list<array<string,mixed>>
     */
    public function listForOrganization(int $organizationId): array
    {
        return $this->db->select(
            'SELECT ou.*, u.`name`, u.`email`, u.`google_avatar_url`, u.`last_login_at`,
                    (SELECT COUNT(*) FROM `employee_permissions` ep
                     WHERE ep.`organization_user_id` = ou.`id`) AS `permission_count`
             FROM `organization_users` ou
             INNER JOIN `users` u ON u.`id` = ou.`user_id`
             WHERE ou.`organization_id` = ?
             ORDER BY FIELD(ou.`role`, \'admin\', \'employee\'), u.`name` ASC',
            [$organizationId]
        );
    }

    public function countAdmins(int $organizationId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `organization_users` WHERE `organization_id` = ? AND `role` = ?',
            [$organizationId, OrganizationContext::ROLE_ADMIN]
        );
    }

    /**
     * Organizations the user belongs to, for the account organization switcher.
     *
     * @return list<array<string,mixed>>
     */
    public function organizationsForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT o.`id`, o.`name`, o.`slug`, o.`logo_path`, o.`primary_color`, o.`is_active`,
                    ou.`role`, ou.`id` AS `membership_id`
             FROM `organization_users` ou
             INNER JOIN `organizations` o ON o.`id` = ou.`organization_id`
             WHERE ou.`user_id` = ?
             ORDER BY o.`name` ASC',
            [$userId]
        );
    }

    /**
     * Staff user ids that should be notified about an organization event.
     *
     * Admins always qualify; employees only when they hold the permission.
     *
     * @return list<int>
     */
    public function staffUserIdsWithPermission(int $organizationId, string $permissionKey): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT ou.`user_id`
             FROM `organization_users` ou
             LEFT JOIN `employee_permissions` ep ON ep.`organization_user_id` = ou.`id`
             LEFT JOIN `permissions` p ON p.`id` = ep.`permission_id`
             WHERE ou.`organization_id` = ?
               AND (ou.`role` = \'admin\' OR p.`key` = ?)',
            [$organizationId, $permissionKey]
        );

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    // -----------------------------------------------------------------
    // Employee permissions
    // -----------------------------------------------------------------

    /** @return list<string> */
    public function permissionKeysFor(int $organizationUserId): array
    {
        $rows = $this->db->select(
            'SELECT p.`key`
             FROM `employee_permissions` ep
             INNER JOIN `permissions` p ON p.`id` = ep.`permission_id`
             WHERE ep.`organization_user_id` = ?
             ORDER BY p.`key`',
            [$organizationUserId]
        );

        return array_map(static fn (array $row): string => (string) $row['key'], $rows);
    }

    /**
     * Replaces the full permission set for one employee.
     *
     * Called inside a transaction by EmployeeService so a partial permission
     * set can never be observed.
     *
     * @param list<int> $permissionIds
     */
    public function syncPermissions(int $organizationUserId, array $permissionIds): void
    {
        $this->db->delete('employee_permissions', ['organization_user_id' => $organizationUserId]);

        $now = $this->now();

        foreach (array_unique($permissionIds) as $permissionId) {
            $this->db->insert('employee_permissions', [
                'organization_user_id' => $organizationUserId,
                'permission_id'        => $permissionId,
                'created_at'           => $now,
            ]);
        }
    }
}
