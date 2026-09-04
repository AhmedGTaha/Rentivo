<?php

declare(strict_types=1);

/**
 * Central application configuration.
 *
 * Values are resolved from the environment through env(), never read from
 * $_ENV directly elsewhere in the application.
 */

use Rentivo\Support\Env;

return [
    'name'     => Env::get('APP_NAME', 'Rentivo'),
    'env'      => Env::get('APP_ENV', 'production'),
    'url'      => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'debug'    => Env::bool('APP_DEBUG', false),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Bahrain'),

    'database' => [
        'host'     => Env::get('DB_HOST', '127.0.0.1'),
        'port'     => (int) Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'rentivo'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
    ],

    'google' => [
        'client_id'     => Env::get('GOOGLE_CLIENT_ID', ''),
        'client_secret' => Env::get('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri'  => Env::get('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
    ],

    'mail' => [
        'host'       => Env::get('SMTP_HOST', ''),
        'port'       => (int) Env::get('SMTP_PORT', '587'),
        'username'   => Env::get('SMTP_USERNAME', ''),
        'password'   => Env::get('SMTP_PASSWORD', ''),
        'encryption' => Env::get('SMTP_ENCRYPTION', 'tls'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
        'from_name'    => Env::get('MAIL_FROM_NAME', 'Rentivo'),
    ],

    'uploads' => [
        // Maximum accepted source upload size for images, in bytes.
        'max_image_bytes'    => 8 * 1024 * 1024,
        'max_document_bytes' => 10 * 1024 * 1024,
        'max_car_images'     => 10,
        // Longest edge, in pixels, kept for processed marketing images.
        'image_max_edge'     => 1800,
        'webp_quality'       => 82,
    ],

    'pagination' => [
        'cars_per_page'     => 12,
        'default_per_page'  => 20,
    ],

    'booking' => [
        'invitation_ttl_days' => 7,
    ],
];
