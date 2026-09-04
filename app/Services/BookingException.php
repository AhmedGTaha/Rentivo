<?php

declare(strict_types=1);

namespace Rentivo\Services;

use RuntimeException;

/**
 * A business-rule failure in the booking domain (invalid transition, date
 * conflict, missing prerequisite). Messages are user-safe.
 */
final class BookingException extends RuntimeException
{
    /** @param list<array<string,mixed>> $conflicts */
    public function __construct(
        string $message,
        private array $conflicts = []
    ) {
        parent::__construct($message);
    }

    /** @return list<array<string,mixed>> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    /** @param list<array<string,mixed>> $conflicts */
    public static function conflict(string $message, array $conflicts = []): self
    {
        return new self($message, $conflicts);
    }
}
