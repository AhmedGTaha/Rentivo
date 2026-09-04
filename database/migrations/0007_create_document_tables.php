<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Customer identity documents and the per-organization review of them.
 *
 * Verification is scoped to (organization_id, document_id): one agency
 * verifying a licence never marks it verified for another agency.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `user_documents` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `document_type` ENUM('driving_license','national_id') NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(191) NULL DEFAULT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATE NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_documents_user_type_index` (`user_id`, `document_type`),
            CONSTRAINT `user_documents_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `document_reviews` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `document_id` BIGINT UNSIGNED NOT NULL,
            `status` ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
            `reviewed_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `reviewed_at` DATETIME NULL DEFAULT NULL,
            `rejection_reason` TEXT NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `document_reviews_organization_document_unique` (`organization_id`, `document_id`),
            KEY `document_reviews_document_id_index` (`document_id`),
            KEY `document_reviews_organization_status_index` (`organization_id`, `status`),
            CONSTRAINT `document_reviews_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `document_reviews_document_id_foreign`
                FOREIGN KEY (`document_id`) REFERENCES `user_documents` (`id`) ON DELETE CASCADE,
            CONSTRAINT `document_reviews_reviewed_by_user_id_foreign`
                FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
