<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Builds the test schema from the real migrations, so the tests always run
 * against the same structure a fresh installation would produce. If MySQL is
 * unreachable the unit suite still runs; only the integration tests skip.
 */

use Rentivo\Bootstrap;
use Rentivo\Database\Connection;
use Rentivo\Database\Migrator;
use Rentivo\Repositories\PermissionRepository;
use Rentivo\Security\Permissions;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

// phpunit.xml sets these; assigning them here too keeps direct invocation working.
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'testing';
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'rentivo_test';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'true';

$app = Bootstrap::boot($basePath);

try {
    /** @var Connection $connection */
    $connection = $app->get(Connection::class);

    // Create the test schema if the server is up but the database is absent.
    $config = \Rentivo\Support\Config::get('database');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $config['database']) ?? '';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    /** @var Migrator $migrator */
    $migrator = $app->get(Migrator::class);

    // A clean schema every run: no leftover state can make a test pass.
    $migrator->dropAllTables();
    $migrator->run();

    /** @var PermissionRepository $permissions */
    $permissions = $app->get(PermissionRepository::class);

    foreach (Permissions::groups() as $groupKey => $group) {
        foreach ($group['permissions'] as $key => $label) {
            $permissions->upsert($key, $groupKey, $label);
        }
    }

    define('RENTIVO_TEST_DATABASE_READY', true);
} catch (Throwable $e) {
    // Integration tests call requiresDatabase() and skip themselves.
    define('RENTIVO_TEST_DATABASE_READY', false);
    define('RENTIVO_TEST_DATABASE_ERROR', $e->getMessage());
}
