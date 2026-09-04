<?php

declare(strict_types=1);

namespace Rentivo\Security;

use Rentivo\Database\Connection;
use Rentivo\Http\HttpException;
use Throwable;

/**
 * Database-backed fixed-window rate limiter for sensitive routes.
 *
 * Deliberately simple: one row per (key, window). Keys are hashed so raw
 * identifiers such as email addresses are never stored in the limiter table.
 */
final class RateLimiter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Records a hit and reports whether the caller is still within the limit.
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $hash = hash('sha256', $key);
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + $decaySeconds);

        try {
            $this->connection->statement(
                'INSERT INTO `rate_limits` (`limit_key`, `attempts`, `expires_at`, `created_at`)
                 VALUES (:key, 1, :expires, :now)
                 ON DUPLICATE KEY UPDATE
                    `attempts`   = IF(`expires_at` <= :now2, 1, `attempts` + 1),
                    `expires_at` = IF(`expires_at` <= :now3, :expires2, `expires_at`)',
                [
                    'key'      => $hash,
                    'expires'  => $expires,
                    'now'      => $now,
                    'now2'     => $now,
                    'now3'     => $now,
                    'expires2' => $expires,
                ]
            );

            $attempts = (int) $this->connection->scalar(
                'SELECT `attempts` FROM `rate_limits` WHERE `limit_key` = ?',
                [$hash]
            );
        } catch (Throwable) {
            // A limiter outage must never take down a legitimate request.
            return true;
        }

        return $attempts <= $maxAttempts;
    }

    /**
     * Convenience wrapper that throws 429 when the limit is exceeded.
     *
     * @throws HttpException
     */
    public function enforce(string $key, int $maxAttempts, int $decaySeconds, string $message = ''): void
    {
        if (!$this->attempt($key, $maxAttempts, $decaySeconds)) {
            throw HttpException::tooManyRequests($message);
        }
    }

    public function clear(string $key): void
    {
        try {
            $this->connection->delete('rate_limits', ['limit_key' => hash('sha256', $key)]);
        } catch (Throwable) {
            // Ignored: clearing is best-effort.
        }
    }

    /** Removes expired windows; called by the scheduler. */
    public function prune(): int
    {
        try {
            return $this->connection->affectingStatement(
                'DELETE FROM `rate_limits` WHERE `expires_at` <= ?',
                [gmdate('Y-m-d H:i:s')]
            );
        } catch (Throwable) {
            return 0;
        }
    }
}
