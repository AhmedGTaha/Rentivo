<?php

declare(strict_types=1);

/**
 * Rentivo seeder.
 *
 *   php scripts/seed.php          Seed the permission catalogue (required)
 *   php scripts/seed.php --demo   Also create demo agencies, cars and bookings
 *
 * The permission seed is idempotent and safe to run on every deploy. Demo
 * content is never created unless --demo is passed explicitly, so production
 * installs stay clean.
 */

use Rentivo\Bootstrap;
use Rentivo\Database\Connection;
use Rentivo\Repositories\PermissionRepository;
use Rentivo\Security\Permissions;
use Rentivo\Support\Config;

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line.');
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$app = Bootstrap::boot($basePath);

$options = array_slice($argv, 1);
$withDemo = in_array('--demo', $options, true);

$write = static function (string $message, string $color = "\033[36m"): void {
    echo $color . $message . "\033[0m" . PHP_EOL;
};

try {
    /** @var Connection $db */
    $db = $app->get(Connection::class);

    if (!$db->tableExists('permissions')) {
        $write('The database has not been migrated yet. Run: php scripts/migrate.php', "\033[31m");
        exit(1);
    }

    // -----------------------------------------------------------------
    // Permissions (always seeded)
    // -----------------------------------------------------------------
    /** @var PermissionRepository $permissions */
    $permissions = $app->get(PermissionRepository::class);

    $count = 0;

    foreach (Permissions::groups() as $groupKey => $group) {
        foreach ($group['permissions'] as $key => $label) {
            $permissions->upsert($key, $groupKey, $label);
            $count++;
        }
    }

    $write("Seeded {$count} permissions.", "\033[32m");

    // -----------------------------------------------------------------
    // Optional development demo content
    // -----------------------------------------------------------------
    if (!$withDemo) {
        $write('Run with --demo to also create development demo content.');
        exit(0);
    }

    if (Config::isProduction()) {
        $write('Refusing to seed demo content in a production environment.', "\033[31m");
        exit(1);
    }

    $seeder = require $basePath . '/database/seeds/demo.php';
    $seeder($app, $write);

    exit(0);
} catch (Throwable $e) {
    $write('Seeding failed: ' . $e->getMessage(), "\033[31m");
    exit(1);
}
