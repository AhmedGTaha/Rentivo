<?php

declare(strict_types=1);

namespace Rentivo\Database;

use Throwable;

/**
 * Minimal ordered migration runner.
 *
 * Each migration is a PHP file in database/migrations returning a callable
 * that receives the Connection. Applied migrations are recorded in the
 * `migrations` table so re-running the command is always safe.
 */
final class Migrator
{
    public function __construct(
        private Connection $connection,
        private string $migrationsPath
    ) {
    }

    public function ensureHistoryTable(): void
    {
        $this->connection->statement(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT UNSIGNED NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migrations_migration_unique` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> Migration names in execution order. */
    public function available(): array
    {
        $files = glob(rtrim($this->migrationsPath, '/\\') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        return array_map(
            static fn (string $file): string => basename($file, '.php'),
            $files
        );
    }

    /** @return list<string> */
    public function applied(): array
    {
        $this->ensureHistoryTable();

        $rows = $this->connection->select('SELECT `migration` FROM `migrations` ORDER BY `id`');

        return array_map(static fn (array $row): string => (string) $row['migration'], $rows);
    }

    /** @return list<string> */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->applied()));
    }

    /**
     * Runs all pending migrations.
     *
     * @param callable(string,string):void|null $output Receives (level, message).
     * @return list<string> Names of migrations that ran.
     */
    public function run(?callable $output = null): array
    {
        $output ??= static function (): void {
        };

        $this->ensureHistoryTable();

        $pending = $this->pending();

        if ($pending === []) {
            $output('info', 'Nothing to migrate. Database is up to date.');

            return [];
        }

        $batch = (int) $this->connection->scalar('SELECT COALESCE(MAX(`batch`), 0) FROM `migrations`') + 1;
        $ran = [];

        foreach ($pending as $name) {
            $path = rtrim($this->migrationsPath, '/\\') . '/' . $name . '.php';

            $migration = require $path;

            if (!is_callable($migration)) {
                throw new \RuntimeException("Migration {$name} must return a callable.");
            }

            $output('run', 'Migrating: ' . $name);

            try {
                // DDL is not transactional in MySQL, so each migration is
                // recorded only after it fully succeeds.
                $migration($this->connection);
            } catch (Throwable $e) {
                $output('error', 'Failed: ' . $name . ' — ' . $e->getMessage());

                throw $e;
            }

            $this->connection->insert('migrations', [
                'migration'  => $name,
                'batch'      => $batch,
                'applied_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $output('done', 'Migrated:  ' . $name);
            $ran[] = $name;
        }

        return $ran;
    }

    /** Drops every table in the schema. Used by the test bootstrap only. */
    public function dropAllTables(): void
    {
        $pdo = $this->connection->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $tables = $this->connection->select(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?',
            [$this->connection->databaseName()]
        );

        foreach ($tables as $row) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
