<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Database\Connection;
use Rentivo\Support\DateTimeHelper;

/**
 * Base for all repositories.
 *
 * Repositories own every SQL statement in the application. They never render
 * HTML, never read the session, and never make authorization decisions —
 * instead they always accept the tenant scope as an explicit argument.
 */
abstract class Repository
{
    public function __construct(protected Connection $db)
    {
    }

    public function connection(): Connection
    {
        return $this->db;
    }

    protected function now(): string
    {
        return DateTimeHelper::nowDb();
    }

    /**
     * Builds "IN (?, ?, ?)" placeholders for a list of values.
     *
     * @param list<mixed> $values
     */
    protected function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }
}
