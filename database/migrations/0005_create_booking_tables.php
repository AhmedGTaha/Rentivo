<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Bookings, the organization-customer relationship, and the counter table
 * that makes booking reference generation concurrency-safe.
 *
 * Every monetary column is an unsigned integer number of fils. No float or
 * decimal money exists anywhere in the schema.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `organization_customers` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `internal_notes` TEXT NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `organization_customers_org_user_unique` (`organization_id`, `user_id`),
            KEY `organization_customers_user_id_index` (`user_id`),
            CONSTRAINT `organization_customers_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `organization_customers_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // One row per calendar year; incremented atomically to allocate the next
    // sequence number for a BK-YYYY-NNNNNN reference.
    $db->statement(
        "CREATE TABLE `booking_reference_counters` (
            `year` SMALLINT UNSIGNED NOT NULL,
            `next_value` INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `bookings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `car_id` BIGINT UNSIGNED NOT NULL,
            `pickup_location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `return_location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `pickup_at` DATETIME NOT NULL,
            `return_at` DATETIME NOT NULL,
            `rental_days` SMALLINT UNSIGNED NOT NULL,
            `daily_rate_snapshot_fils` INT UNSIGNED NOT NULL,
            `subtotal_fils` INT UNSIGNED NOT NULL,
            `additional_charges_fils` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_fils` INT UNSIGNED NOT NULL,
            `status` ENUM('pending','confirmed','rejected','ready_for_pickup','active','completed','cancelled','no_show')
                NOT NULL DEFAULT 'pending',
            `payment_status` ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
            `payment_method` VARCHAR(32) NOT NULL DEFAULT 'pay_at_pickup',
            `customer_notes` TEXT NULL DEFAULT NULL,
            `admin_notes` TEXT NULL DEFAULT NULL,
            `rejection_reason` TEXT NULL DEFAULT NULL,
            `cancellation_reason` TEXT NULL DEFAULT NULL,
            `cancelled_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `cancelled_at` DATETIME NULL DEFAULT NULL,
            `confirmed_at` DATETIME NULL DEFAULT NULL,
            `completed_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `bookings_reference_unique` (`reference`),
            KEY `bookings_organization_id_index` (`organization_id`),
            KEY `bookings_user_id_index` (`user_id`),
            KEY `bookings_car_id_index` (`car_id`),
            KEY `bookings_status_index` (`status`),
            KEY `bookings_pickup_at_index` (`pickup_at`),
            KEY `bookings_return_at_index` (`return_at`),
            KEY `bookings_organization_status_index` (`organization_id`, `status`),
            /* Availability lookups: for a car, find blocking bookings that
               overlap a requested window. */
            KEY `bookings_availability_index` (`car_id`, `status`, `pickup_at`, `return_at`),
            CONSTRAINT `bookings_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `bookings_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `bookings_car_id_foreign`
                FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `bookings_pickup_location_id_foreign`
                FOREIGN KEY (`pickup_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
            CONSTRAINT `bookings_return_location_id_foreign`
                FOREIGN KEY (`return_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
            CONSTRAINT `bookings_cancelled_by_user_id_foreign`
                FOREIGN KEY (`cancelled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
