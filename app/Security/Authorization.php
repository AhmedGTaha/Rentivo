<?php

declare(strict_types=1);

namespace Rentivo\Security;

use Rentivo\Auth\SessionAuth;
use Rentivo\Http\HttpException;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Repositories\OrganizationUserRepository;

/**
 * Central entry point for organization authorization.
 *
 * Nothing in the management area is reachable without going through
 * organizationContext(), which enforces, in order:
 *
 *   1. an authenticated user
 *   2. an existing organization for the slug
 *   3. an actual membership row linking that user to that organization
 *   4. (at the call site) the required permission
 */
final class Authorization
{
    /** @var array<string, OrganizationContext> */
    private array $contextCache = [];

    public function __construct(
        private SessionAuth $auth,
        private OrganizationRepository $organizations,
        private OrganizationUserRepository $members
    ) {
    }

    /**
     * Resolves the organization context for a management request.
     *
     * A non-member receives 404 rather than 403 so the existence of another
     * agency's organization is not confirmed to a probing user.
     *
     * @throws HttpException
     */
    public function organizationContext(string $slug): OrganizationContext
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        // The cache is keyed by user as well as slug: a context resolved for
        // one identity must never be handed to another.
        $cacheKey = $user['id'] . '|' . $slug;

        if (isset($this->contextCache[$cacheKey])) {
            return $this->contextCache[$cacheKey];
        }

        $organization = $this->organizations->findBySlug($slug);

        if ($organization === null) {
            throw HttpException::notFound();
        }

        $membership = $this->members->findMembership((int) $organization['id'], (int) $user['id']);

        if ($membership === null) {
            throw HttpException::notFound();
        }

        if ((int) $organization['is_active'] !== 1 && $membership['role'] !== OrganizationContext::ROLE_ADMIN) {
            throw HttpException::forbidden('This organization is currently inactive.');
        }

        $permissions = $membership['role'] === OrganizationContext::ROLE_ADMIN
            ? Permissions::all()
            : $this->members->permissionKeysFor((int) $membership['id']);

        return $this->contextCache[$cacheKey] = new OrganizationContext(
            $organization,
            (int) $user['id'],
            (int) $membership['id'],
            (string) $membership['role'],
            $permissions
        );
    }

    /**
     * Non-throwing variant used by navigation to decide what to display.
     */
    public function tryOrganizationContext(string $slug): ?OrganizationContext
    {
        try {
            return $this->organizationContext($slug);
        } catch (HttpException) {
            return null;
        }
    }

    /**
     * Organizations the current user belongs to, for the account switcher.
     *
     * @return list<array<string,mixed>>
     */
    public function memberships(): array
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return [];
        }

        return $this->members->organizationsForUser($userId);
    }

    /** @throws HttpException when unauthenticated. */
    public function requireUser(): array
    {
        return $this->auth->requireUser();
    }

    /**
     * Guards ownership of a customer-owned record.
     *
     * @throws HttpException
     */
    public function requireOwnership(int $ownerUserId): void
    {
        if ($this->auth->id() !== $ownerUserId) {
            throw HttpException::forbidden();
        }
    }
}
