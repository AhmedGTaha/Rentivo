<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Cross-cutting platform tables: in-app notifications, the immutable audit
 * trail, and the rate limiter's window store.
 *
 * notifications.dedupe_key is what makes the hourly scheduler idempotent: a
 * reminder can only ever be inserted once per (booking, reminder kind).
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `organization_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `type` VARCHAR(48) NOT NULL,
            `title` VARCHAR(191) NOT NULL,
            `message` TEXT NOT NULL,
            `related_entity_type` VARCHAR(48) NULL DEFAULT NULL,
            `related_entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `action_url` VARCHAR(255) NULL DEFAULT NULL,
            `dedupe_key` VARCHAR(191) NULL DEFAULT NULL,
            `read_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `notifications_dedupe_key_unique` (`dedupe_key`),
            KEY `notifications_user_id_index` (`user_id`),
            KEY `notifications_read_at_index` (`read_at`),
            KEY `notifications_user_created_index` (`user_id`, `created_at`),
            CONSTRAINT `notifications_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `notifications_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `activity_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `actor_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `action_key` VARCHAR(64) NOT NULL,
            `entity_type` VARCHAR(48) NULL DEFAULT NULL,
            `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `metadata_json` JSON NULL DEFAULT NULL,
            `ip_address` VARCHAR(45) NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `activity_logs_organization_id_index` (`organization_id`),
            KEY `activity_logs_created_at_index` (`created_at`),
            KEY `activity_logs_organization_created_index` (`organization_id`, `created_at`),
            KEY `activity_logs_entity_index` (`entity_type`, `entity_id`),
            CONSTRAINT `activity_logs_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `activity_logs_actor_user_id_foreign`
                FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `rate_limits` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `limit_key` CHAR(64) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `rate_limits_limit_key_unique` (`limit_key`),
            KEY `rate_limits_expires_at_index` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
