<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Platform identity. There is no password column anywhere in Rentivo: the
 * only credential is the Google subject id.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `google_id` VARCHAR(64) NOT NULL,
            `email` VARCHAR(191) NOT NULL,
            `name` VARCHAR(191) NOT NULL,
            `google_avatar_url` VARCHAR(512) NULL DEFAULT NULL,
            `email_verified_at` DATETIME NULL DEFAULT NULL,
            `last_login_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_google_id_unique` (`google_id`),
            UNIQUE KEY `users_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `user_profiles` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `phone` VARCHAR(32) NULL DEFAULT NULL,
            `date_of_birth` DATE NULL DEFAULT NULL,
            `nationality` VARCHAR(96) NULL DEFAULT NULL,
            `address` VARCHAR(255) NULL DEFAULT NULL,
            `profile_image_path` VARCHAR(255) NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_profiles_user_id_unique` (`user_id`),
            CONSTRAINT `user_profiles_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
