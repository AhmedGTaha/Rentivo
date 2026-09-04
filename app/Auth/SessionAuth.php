<?php

declare(strict_types=1);

namespace Rentivo\Auth;

use Rentivo\Http\HttpException;
use Rentivo\Http\Response;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\Csrf;
use Rentivo\Support\Session;

/**
 * The single source of truth for "who is making this request".
 *
 * There are no passwords in Rentivo; a session is only ever established by a
 * successful Google OAuth callback.
 */
final class SessionAuth
{
    private const USER_KEY = '_auth_user_id';
    private const INTENDED_KEY = '_intended_url';
    private const INTENDED_CONTEXT = '_intended_booking';

    /** @var array<string,mixed>|null */
    private ?array $cachedUser = null;

    private bool $resolved = false;

    public function __construct(private UserRepository $users)
    {
    }

    public function login(int $userId): void
    {
        // Session fixation defence: a brand new session id after every login.
        Session::regenerate();
        Session::put(self::USER_KEY, $userId);
        Csrf::rotate();

        $this->cachedUser = null;
        $this->resolved = false;
    }

    public function logout(): void
    {
        Session::destroy();
        $this->cachedUser = null;
        $this->resolved = true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->cachedUser;
        }

        $this->resolved = true;
        $userId = Session::get(self::USER_KEY);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return $this->cachedUser = null;
        }

        $user = $this->users->find((int) $userId);

        if ($user === null) {
            // The account disappeared underneath the session.
            Session::forget(self::USER_KEY);

            return $this->cachedUser = null;
        }

        return $this->cachedUser = $user;
    }

    /**
     * @return array<string,mixed>
     *
     * @throws HttpException when unauthenticated.
     */
    public function requireUser(): array
    {
        $user = $this->user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }

    public function requireId(): int
    {
        return (int) $this->requireUser()['id'];
    }

    /** Forgets any cached identity; used after profile updates. */
    public function refresh(): void
    {
        $this->cachedUser = null;
        $this->resolved = false;
    }

    /**
     * Stores where the visitor should land after authenticating.
     *
     * The value is sanitised to an in-app path so it can never become an
     * open redirect.
     */
    public function setIntendedUrl(string $url): void
    {
        Session::put(self::INTENDED_KEY, Response::safeLocation($url));
    }

    public function pullIntendedUrl(string $fallback = '/account'): string
    {
        $intended = Session::pull(self::INTENDED_KEY);

        if (!is_string($intended) || $intended === '') {
            return Response::safeLocation($fallback);
        }

        return Response::safeLocation($intended);
    }

    /**
     * Preserves an in-progress booking selection across the Google round trip.
     *
     * @param array<string,mixed> $context
     */
    public function setIntendedBooking(array $context): void
    {
        Session::put(self::INTENDED_CONTEXT, $context);
    }

    /** @return array<string,mixed>|null */
    public function pullIntendedBooking(): ?array
    {
        $context = Session::pull(self::INTENDED_CONTEXT);

        return is_array($context) ? $context : null;
    }

    /** @return array<string,mixed>|null */
    public function peekIntendedBooking(): ?array
    {
        $context = Session::get(self::INTENDED_CONTEXT);

        return is_array($context) ? $context : null;
    }
}
