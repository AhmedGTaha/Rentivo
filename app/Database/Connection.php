<?php

declare(strict_types=1);

namespace Rentivo\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Single PDO connection wrapper.
 *
 * Every query in the application goes through this class, which guarantees
 * exception error mode, real prepared statements, and utf8mb4.
 */
final class Connection
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $config
     */
    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $charset = $this->config['charset'] ?? 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $charset
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            // Deliberately does not leak credentials into the message.
            throw new RuntimeException(
                'Unable to connect to the database "' . $this->config['database'] . '".',
                (int) $e->getCode(),
                $e
            );
        }

        $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        $this->pdo->exec("SET time_zone = '+00:00'");

        return $this->pdo;
    }

    /** Allows tests to inject an already-open connection. */
    public function setPdo(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    public function databaseName(): string
    {
        return $this->config['database'];
    }

    /** @param array<string|int,mixed> $bindings */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : $key;

            $statement->bindValue($parameter, $value, match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            });
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->statement($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return list<array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->statement($sql, $bindings)->fetchAll();
    }

    /** @param array<string|int,mixed> $bindings */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->statement($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $bindings */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    /**
     * Inserts a row from an associative array and returns the new id.
     *
     * Column names come only from application code, never from request input.
     *
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns)),
            implode(', ', $placeholders)
        );

        $this->statement($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Updates rows matched by $where.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            return 0;
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = '`' . $column . '` = :set_' . $column;
            $bindings['set_' . $column] = $value;
        }

        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = '`' . $column . '` = :where_' . $column;
            $bindings['where_' . $column] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            implode(' AND ', $conditions)
        );

        return $this->affectingStatement($sql, $bindings);
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            return 0;
        }

        $conditions = [];
        $bindings = [];

        foreach ($where as $column => $value) {
            $conditions[] = '`' . $column . '` = :' . $column;
            $bindings[$column] = $value;
        }

        return $this->affectingStatement(
            sprintf('DELETE FROM `%s` WHERE %s', $table, implode(' AND ', $conditions)),
            $bindings
        );
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->transactionDepth + 1));
        }

        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT trans' . ($this->transactionDepth + 1));
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . ($this->transactionDepth + 1));
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /**
     * Runs $callback inside a transaction, rolling back on any throwable.
     *
     * @template T
     * @param callable(self):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        $result = $this->scalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$this->config['database'], $table]
        );

        return (int) $result > 0;
    }
}
