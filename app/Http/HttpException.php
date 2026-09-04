<?php

declare(strict_types=1);

namespace Rentivo\Http;

use RuntimeException;
use Throwable;

/**
 * Exception carrying an HTTP status. Thrown anywhere in the stack (typically
 * by authorization checks) and converted to a rendered error page by the
 * kernel.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private int $status,
        string $message = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message);
    }

    public static function methodNotAllowed(string $message = ''): self
    {
        return new self(405, $message);
    }

    public static function tooManyRequests(string $message = ''): self
    {
        return new self(429, $message);
    }

    public static function unprocessable(string $message = ''): self
    {
        return new self(422, $message);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be understood.',
            401 => 'You need to sign in to continue.',
            403 => 'You do not have permission to do that.',
            404 => 'We could not find the page you were looking for.',
            405 => 'That action is not allowed on this address.',
            409 => 'That action conflicts with the current state.',
            422 => 'The information provided could not be processed.',
            429 => 'Too many attempts. Please slow down and try again shortly.',
            default => 'Something went wrong on our side.',
        };
    }
}
