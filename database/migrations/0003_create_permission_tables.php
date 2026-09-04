<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Employee permission catalogue, assignments, and invitations.
 *
 * Invitations store only a SHA-256 hash of the token; the raw token exists
 * exactly once, inside the invitation URL that is emailed to the invitee.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `permissions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `key` VARCHAR(64) NOT NULL,
            `group_key` VARCHAR(32) NOT NULL,
            `label` VARCHAR(120) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `permissions_key_unique` (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `employee_permissions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_user_id` BIGINT UNSIGNED NOT NULL,
            `permission_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `employee_permissions_member_permission_unique` (`organization_user_id`, `permission_id`),
            KEY `employee_permissions_permission_id_index` (`permission_id`),
            CONSTRAINT `employee_permissions_organization_user_id_foreign`
                FOREIGN KEY (`organization_user_id`) REFERENCES `organization_users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `employee_permissions_permission_id_foreign`
                FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `employee_invitations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `email` VARCHAR(191) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `invited_by_user_id` BIGINT UNSIGNED NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `accepted_at` DATETIME NULL DEFAULT NULL,
            `accepted_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `revoked_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `employee_invitations_token_hash_unique` (`token_hash`),
            KEY `employee_invitations_organization_email_index` (`organization_id`, `email`),
            KEY `employee_invitations_expires_at_index` (`expires_at`),
            CONSTRAINT `employee_invitations_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `employee_invitations_invited_by_user_id_foreign`
                FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `employee_invitations_accepted_by_user_id_foreign`
                FOREIGN KEY (`accepted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `employee_invitation_permissions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invitation_id` BIGINT UNSIGNED NOT NULL,
            `permission_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `invitation_permissions_invitation_permission_unique` (`invitation_id`, `permission_id`),
            KEY `invitation_permissions_permission_id_index` (`permission_id`),
            CONSTRAINT `invitation_permissions_invitation_id_foreign`
                FOREIGN KEY (`invitation_id`) REFERENCES `employee_invitations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `invitation_permissions_permission_id_foreign`
                FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
