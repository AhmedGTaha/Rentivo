<?php

declare(strict_types=1);

use Rentivo\Database\Connection;

/**
 * Rental records created at pickup and completed at return, plus the private
 * inspection photographs captured during each phase.
 *
 * Inspection images live under storage/private and are never mixed with
 * public marketing photos.
 */
return static function (Connection $db): void {
    $db->statement(
        "CREATE TABLE `rentals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `booking_id` BIGINT UNSIGNED NOT NULL,
            `organization_id` BIGINT UNSIGNED NOT NULL,
            `car_id` BIGINT UNSIGNED NOT NULL,
            `customer_user_id` BIGINT UNSIGNED NOT NULL,
            `checkout_employee_user_id` BIGINT UNSIGNED NOT NULL,
            `return_employee_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `checkout_at` DATETIME NOT NULL,
            `expected_return_at` DATETIME NOT NULL,
            `actual_return_at` DATETIME NULL DEFAULT NULL,
            `checkout_mileage` INT UNSIGNED NOT NULL,
            `return_mileage` INT UNSIGNED NULL DEFAULT NULL,
            `checkout_fuel_percentage` TINYINT UNSIGNED NOT NULL,
            `return_fuel_percentage` TINYINT UNSIGNED NULL DEFAULT NULL,
            `checkout_condition` TEXT NULL DEFAULT NULL,
            `return_condition` TEXT NULL DEFAULT NULL,
            `damage_notes` TEXT NULL DEFAULT NULL,
            `additional_charges_fils` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `rentals_booking_id_unique` (`booking_id`),
            KEY `rentals_organization_id_index` (`organization_id`),
            KEY `rentals_car_id_index` (`car_id`),
            KEY `rentals_customer_user_id_index` (`customer_user_id`),
            KEY `rentals_expected_return_at_index` (`expected_return_at`),
            CONSTRAINT `rentals_booking_id_foreign`
                FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `rentals_organization_id_foreign`
                FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `rentals_car_id_foreign`
                FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `rentals_customer_user_id_foreign`
                FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `rentals_checkout_employee_user_id_foreign`
                FOREIGN KEY (`checkout_employee_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `rentals_return_employee_user_id_foreign`
                FOREIGN KEY (`return_employee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->statement(
        "CREATE TABLE `rental_inspection_images` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `rental_id` BIGINT UNSIGNED NOT NULL,
            `phase` ENUM('checkout','return') NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `rental_inspection_images_rental_phase_index` (`rental_id`, `phase`),
            CONSTRAINT `rental_inspection_images_rental_id_foreign`
                FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
