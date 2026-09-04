<?php

declare(strict_types=1);

namespace Rentivo\Support;

/**
 * Session bootstrap and typed accessors.
 *
 * Cookies are HttpOnly and SameSite=Lax always, and Secure whenever the
 * request is served over HTTPS or the app runs in production.
 */
final class Session
{
    public static function start(bool $secure = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // In CLI (tests, scheduler) there is no session to start.
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('rentivo_session');
        session_start();
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Reads and removes a value in one step. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: 'rentivo_session', '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
