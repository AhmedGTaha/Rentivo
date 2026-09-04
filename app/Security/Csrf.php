<?php

declare(strict_types=1);

namespace Rentivo\Security;

use Rentivo\Support\Session;

/**
 * Per-session CSRF token.
 *
 * Every state-changing request is validated by the kernel before it reaches a
 * controller, so individual controllers never repeat this check.
 */
final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function isValid(?string $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($token, $candidate);
    }

    /** Rotated after login so a pre-auth token cannot be reused. */
    public static function rotate(): string
    {
        Session::forget(self::SESSION_KEY);

        return self::token();
    }

    /** Ready-to-render hidden input. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
