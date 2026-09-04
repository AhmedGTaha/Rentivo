<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Organization-owned fleet: locations, categories, cars and marketing images.
 *
 * Every one of these tables carries organization_id (directly, or through its
 * parent car) so repositories can always scope by tenant.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `locations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `address` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(32) NULL DEFAULT NULL,
            `opening_hours` VARCHAR(255) NULL DEFAULT NULL,
            `latitude` DECIMAL(10,7) NULL DEFAULT NULL,
            `longitude` DECIMAL(10,7) NULL DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `locations_organization_id_index` (`organization_id`),
            KEY `locations_organization_active_index` (`organization_id`, `is_active`),
            CONSTRAINT `locations_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `car_categories` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `slug` VARCHAR(140) NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `car_categories_organization_name_unique` (`organization_id`, `name`),
            UNIQUE KEY `car_categories_organization_slug_unique` (`organization_id`, `slug`),
            CONSTRAINT `car_categories_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `cars` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `category_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `brand` VARCHAR(80) NOT NULL,
            `model` VARCHAR(80) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `year` SMALLINT UNSIGNED NOT NULL,
            `plate_number` VARCHAR(32) NULL DEFAULT NULL,
            `vin` VARCHAR(32) NULL DEFAULT NULL,
            `transmission` ENUM('automatic','manual') NOT NULL DEFAULT 'automatic',
            `fuel_type` ENUM('petrol','diesel','hybrid','electric') NOT NULL DEFAULT 'petrol',
            `seats` TINYINT UNSIGNED NOT NULL DEFAULT 5,
            `doors` TINYINT UNSIGNED NOT NULL DEFAULT 4,
            `mileage` INT UNSIGNED NULL DEFAULT NULL,
            `color` VARCHAR(40) NULL DEFAULT NULL,
            `daily_rate_fils` INT UNSIGNED NOT NULL,
            `description` TEXT NULL DEFAULT NULL,
            `status` ENUM('available','reserved','rented','maintenance','inactive') NOT NULL DEFAULT 'available',
            `archived_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `cars_organization_slug_unique` (`organization_id`, `slug`),
            KEY `cars_organization_id_index` (`organization_id`),
            KEY `cars_status_index` (`status`),
            KEY `cars_brand_index` (`brand`),
            KEY `cars_category_id_index` (`category_id`),
            KEY `cars_location_id_index` (`location_id`),
            KEY `cars_daily_rate_index` (`daily_rate_fils`),
            KEY `cars_organization_status_index` (`organization_id`, `status`, `archived_at`),
            CONSTRAINT `cars_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `cars_category_id_foreign`
                FOREIGN KEY (`category_id`) REFERENCES `car_categories` (`id`) ON DELETE SET NULL,
            CONSTRAINT `cars_location_id_foreign`
                FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `car_images` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `car_id` BIGINT UNSIGNED NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `car_images_car_order_index` (`car_id`, `display_order`),
            KEY `car_images_car_primary_index` (`car_id`, `is_primary`),
            CONSTRAINT `car_images_car_id_foreign`
                FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `favorites` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `car_id` BIGINT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `favorites_user_car_unique` (`user_id`, `car_id`),
            KEY `favorites_car_id_index` (`car_id`),
            CONSTRAINT `favorites_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `favorites_car_id_foreign`
                FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
