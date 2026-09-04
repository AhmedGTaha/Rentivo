<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Organizations (rental agencies) and their membership rows.
 *
 * Membership is the single fact that grants any management access; the role
 * column distinguishes implicit admin authority from explicit employee
 * permissions.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `organizations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `logo_path` VARCHAR(255) NULL DEFAULT NULL,
            `description` TEXT NULL DEFAULT NULL,
            `contact_email` VARCHAR(191) NOT NULL,
            `phone` VARCHAR(32) NOT NULL,
            `address` VARCHAR(255) NULL DEFAULT NULL,
            `primary_color` CHAR(7) NULL DEFAULT NULL,
            `rental_terms` TEXT NULL DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `organizations_slug_unique` (`slug`),
            KEY `organizations_is_active_index` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `organization_users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `role` ENUM('admin','employee') NOT NULL DEFAULT 'employee',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `organization_users_org_user_unique` (`organization_id`, `user_id`),
            KEY `organization_users_user_id_index` (`user_id`),
            KEY `organization_users_organization_id_index` (`organization_id`),
            CONSTRAINT `organization_users_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `organization_users_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
