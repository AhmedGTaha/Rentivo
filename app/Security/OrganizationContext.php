<?php

declare(strict_types=1);

namespace Rentivo\Security;

use Rentivo\Http\HttpException;

/**
 * The resolved answer to "which organization is this request acting on, and
 * what may the current user do inside it?".
 *
 * Controllers receive this object from Authorization and use its
 * organizationId() for every subsequent query, which is what makes tenant
 * isolation structural rather than incidental.
 */
final class OrganizationContext
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EMPLOYEE = 'employee';

    /**
     * @param array<string,mixed> $organization
     * @param list<string>        $permissions
     */
    public function __construct(
        private array $organization,
        private int $userId,
        private int $organizationUserId,
        private string $role,
        private array $permissions
    ) {
    }

    public function organizationId(): int
    {
        return (int) $this->organization['id'];
    }

    public function slug(): string
    {
        return (string) $this->organization['slug'];
    }

    public function name(): string
    {
        return (string) $this->organization['name'];
    }

    /** @return array<string,mixed> */
    public function organization(): array
    {
        return $this->organization;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function organizationUserId(): int
    {
        return $this->organizationUserId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->isAdmin() ? Permissions::all() : $this->permissions;
    }

    /** Admin authority is implicit and covers every permission key. */
    public function can(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    /** @param list<string> $permissions */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @throws HttpException 403 when the permission is missing. */
    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            throw HttpException::forbidden('You do not have permission to perform this action.');
        }
    }

    /**
     * @param list<string> $permissions
     * @throws HttpException
     */
    public function authorizeAny(array $permissions): void
    {
        if (!$this->canAny($permissions)) {
            throw HttpException::forbidden('You do not have permission to perform this action.');
        }
    }

    /** Admin-only actions such as employee and settings management. */
    public function authorizeAdmin(): void
    {
        if (!$this->isAdmin()) {
            throw HttpException::forbidden('Only organization admins can perform this action.');
        }
    }

    public function accentColor(): string
    {
        $color = (string) ($this->organization['primary_color'] ?? '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#111111';
    }
}
