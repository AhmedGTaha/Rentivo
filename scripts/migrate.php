<?php

declare(strict_types=1);

/**
 * Rentivo migration runner.
 *
 *   php scripts/migrate.php            Run all pending migrations
 *   php scripts/migrate.php --status   Show applied/pending migrations
 *   php scripts/migrate.php --fresh    Drop every table then migrate (local only)
 */

use Rentivo\Bootstrap;
use Rentivo\Database\Connection;
use Rentivo\Database\Migrator;
use Rentivo\Support\Config;

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line.');
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$app = Bootstrap::boot($basePath);

$options = array_slice($argv, 1);
$isStatus = in_array('--status', $options, true);
$isFresh = in_array('--fresh', $options, true);

$colors = [
    'run'   => "\033[33m",
    'done'  => "\033[32m",
    'info'  => "\033[36m",
    'error' => "\033[31m",
    'reset' => "\033[0m",
];

$write = static function (string $level, string $message) use ($colors): void {
    $color = $colors[$level] ?? '';
    echo $color . $message . $colors['reset'] . PHP_EOL;
};

try {
    /** @var Connection $connection */
    $connection = $app->get(Connection::class);

    // Create the schema if it does not exist yet so a fresh clone only needs
    // MySQL credentials, not a manually created database.
    ensureDatabaseExists($write);

    /** @var Migrator $migrator */
    $migrator = $app->get(Migrator::class);

    if ($isStatus) {
        $applied = $migrator->applied();

        foreach ($migrator->available() as $migration) {
            $isApplied = in_array($migration, $applied, true);
            $write($isApplied ? 'done' : 'run', ($isApplied ? '[applied] ' : '[pending] ') . $migration);
        }

        exit(0);
    }

    if ($isFresh) {
        if (Config::isProduction()) {
            $write('error', 'Refusing to run --fresh in a production environment.');
            exit(1);
        }

        $write('info', 'Dropping all tables...');
        $migrator->dropAllTables();
    }

    $ran = $migrator->run($write);

    if ($ran !== []) {
        $write('done', count($ran) . ' migration(s) applied.');
    }

    exit(0);
} catch (Throwable $e) {
    $write('error', 'Migration failed: ' . $e->getMessage());
    exit(1);
}

/**
 * Creates the configured database if the server is reachable but the schema
 * is missing.
 */
function ensureDatabaseExists(callable $write): void
{
    /** @var array{host:string,port:int,database:string,username:string,password:string} $config */
    $config = Config::get('database');

    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $exists = $pdo->query(
        'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ' . $pdo->quote($config['database'])
    );

    if ($exists !== false && (int) $exists->fetchColumn() === 0) {
        // Identifier cannot be parameterised; it comes from configuration and
        // is restricted to a safe character set before use.
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $config['database']) ?? '';

        if ($name === '') {
            throw new RuntimeException('Configured database name is invalid.');
        }

        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $write('info', 'Created database "' . $name . '".');
    }
}
