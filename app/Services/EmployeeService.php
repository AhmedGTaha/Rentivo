<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Database\Connection;
use Rentivo\Repositories\InvitationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Repositories\PermissionRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Support\Config;
use Rentivo\Support\DateTimeHelper;

/**
 * Employee invitations, membership and permission assignment.
 *
 * Every method here is admin-only. Employees can never reach this service:
 * OrganizationContext::authorizeAdmin() gates each entry point.
 */
final class EmployeeService
{
    public function __construct(
        private Connection $db,
        private OrganizationUserRepository $members,
        private InvitationRepository $invitations,
        private PermissionRepository $permissions,
        private UserRepository $users,
        private AuditService $audit,
        private NotificationService $notifications,
        private MailService $mail
    ) {
    }

    // -----------------------------------------------------------------
    // Invitations
    // -----------------------------------------------------------------

    /**
     * Creates an invitation and returns the raw token.
     *
     * The raw token is returned exactly once, to be embedded in the emailed
     * URL (and shown to the admin in local development when SMTP is absent).
     * Only its hash is stored.
     *
     * @param list<string> $permissionKeys
     *
     * @return array{invitation_id:int,token:string,url:string,emailed:bool}
     *
     * @throws EmployeeException
     */
    public function invite(OrganizationContext $context, string $email, array $permissionKeys): array
    {
        $context->authorizeAdmin();

        $email = strtolower(trim($email));

        // An existing member does not need an invitation.
        $existingUser = $this->users->findByEmail($email);

        if ($existingUser !== null && $this->members->exists($context->organizationId(), (int) $existingUser['id'])) {
            throw new EmployeeException('That person is already a member of this organization.');
        }

        if ($this->invitations->findPendingForEmail($context->organizationId(), $email) !== null) {
            throw new EmployeeException('An invitation for that email is already pending.');
        }

        // Unknown keys are discarded so a tampered form cannot grant anything
        // outside the catalogue.
        $permissionKeys = Permissions::filterValid($permissionKeys);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $expiresAt = DateTimeHelper::now()
            ->modify('+' . (int) Config::get('booking.invitation_ttl_days', 7) . ' days')
            ->format(DateTimeHelper::DB_FORMAT);

        $invitationId = $this->db->transaction(function () use (
            $context,
            $email,
            $tokenHash,
            $expiresAt,
            $permissionKeys
        ): int {
            $invitationId = $this->invitations->create(
                $context->organizationId(),
                $email,
                $tokenHash,
                $context->userId(),
                $expiresAt
            );

            $this->invitations->setPermissions(
                $invitationId,
                $this->permissions->idsForKeys($permissionKeys)
            );

            return $invitationId;
        });

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::EMPLOYEE_INVITED,
            'invitation',
            $invitationId,
            ['email' => $email, 'permissions' => $permissionKeys]
        );

        $url = rtrim((string) Config::get('url', ''), '/') . '/invitations/' . $token;

        $emailed = $this->mail->send(
            $email,
            $email,
            'You have been invited to join ' . $context->name() . ' on Rentivo',
            $this->mail->layout(
                'Join ' . $context->name(),
                '<p>' . $this->mail->text($context->name())
                    . ' has invited you to help manage their fleet on Rentivo.</p>'
                . '<p>Sign in with the Google account for <strong>'
                    . $this->mail->text($email) . '</strong> to accept. '
                    . 'This invitation expires in 7 days.</p>',
                ['label' => 'Accept invitation', 'url' => $url]
            )
        );

        return [
            'invitation_id' => $invitationId,
            'token'         => $token,
            'url'           => $url,
            'emailed'       => $emailed,
        ];
    }

    public function revokeInvitation(OrganizationContext $context, int $invitationId): void
    {
        $context->authorizeAdmin();

        $invitation = $this->invitations->findInOrganization($invitationId, $context->organizationId());

        if ($invitation === null) {
            throw new EmployeeException('Invitation not found.');
        }

        if ($this->invitations->revoke($invitationId, $context->organizationId()) === 0) {
            throw new EmployeeException('That invitation can no longer be revoked.');
        }

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::EMPLOYEE_INVITE_REVOKED,
            'invitation',
            $invitationId,
            ['email' => $invitation['email']]
        );
    }

    /**
     * Resolves an invitation token for display, without accepting it.
     *
     * @return array<string,mixed>|null
     */
    public function findInvitationByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return $this->invitations->findByTokenHash(hash('sha256', $token));
    }

    /**
     * Classifies an invitation's usability.
     *
     * @param array<string,mixed>|null $invitation
     */
    public function invitationState(?array $invitation): string
    {
        if ($invitation === null) {
            return 'invalid';
        }

        if ($invitation['accepted_at'] !== null) {
            return 'accepted';
        }

        if ($invitation['revoked_at'] !== null) {
            return 'revoked';
        }

        if ((string) $invitation['expires_at'] <= DateTimeHelper::nowDb()) {
            return 'expired';
        }

        return 'valid';
    }

    /**
     * Accepts an invitation on behalf of an authenticated user.
     *
     * Four separate guards must all pass:
     *   - the token resolves to a usable invitation
     *   - the user's Google email is verified
     *   - that email exactly matches the invited address
     *   - the invitation has not already been consumed (enforced by the
     *     conditional UPDATE, which makes replay a no-op)
     *
     * @param array<string,mixed> $user
     *
     * @throws EmployeeException
     */
    public function acceptInvitation(string $token, array $user): array
    {
        $invitation = $this->findInvitationByToken($token);
        $state = $this->invitationState($invitation);

        if ($invitation === null || $state !== 'valid') {
            throw new EmployeeException(match ($state) {
                'accepted' => 'This invitation has already been used.',
                'revoked'  => 'This invitation has been revoked.',
                'expired'  => 'This invitation has expired. Ask the organization for a new one.',
                default    => 'This invitation link is not valid.',
            });
        }

        if (($user['email_verified_at'] ?? null) === null) {
            throw new EmployeeException('Your Google email address must be verified to accept an invitation.');
        }

        if (strtolower((string) $user['email']) !== strtolower((string) $invitation['email'])) {
            throw new EmployeeException(sprintf(
                'This invitation was issued to %s. Sign in with that Google account to accept it.',
                (string) $invitation['email']
            ));
        }

        $organizationId = (int) $invitation['organization_id'];
        $userId = (int) $user['id'];

        if ($this->members->exists($organizationId, $userId)) {
            // Consume the invitation so it cannot linger, but do not duplicate
            // the membership.
            $this->invitations->markAccepted((int) $invitation['id'], $userId);

            throw new EmployeeException('You are already a member of this organization.');
        }

        $this->db->transaction(function () use ($invitation, $organizationId, $userId): void {
            // The conditional UPDATE is the replay guard: a second attempt
            // updates zero rows and aborts before any membership is created.
            if ($this->invitations->markAccepted((int) $invitation['id'], $userId) === 0) {
                throw new EmployeeException('This invitation has already been used.');
            }

            $membershipId = $this->members->create(
                $organizationId,
                $userId,
                OrganizationContext::ROLE_EMPLOYEE
            );

            $this->members->syncPermissions(
                $membershipId,
                $this->invitations->permissionIds((int) $invitation['id'])
            );
        });

        $this->audit->record(
            $organizationId,
            $userId,
            AuditService::EMPLOYEE_JOINED,
            'organization_user',
            $userId,
            ['email' => $invitation['email']]
        );

        $this->notifications->employeeInvitationAccepted(
            $userId,
            $organizationId,
            (string) $invitation['organization_name'],
            (string) $invitation['organization_slug']
        );

        return $invitation;
    }

    // -----------------------------------------------------------------
    // Membership and permissions
    // -----------------------------------------------------------------

    /**
     * Replaces an employee's permission set.
     *
     * @param list<string> $permissionKeys
     *
     * @throws EmployeeException
     */
    public function updatePermissions(OrganizationContext $context, int $memberId, array $permissionKeys): void
    {
        $context->authorizeAdmin();

        $member = $this->members->findInOrganization($memberId, $context->organizationId());

        if ($member === null) {
            throw new EmployeeException('Employee not found.');
        }

        if ((string) $member['role'] === OrganizationContext::ROLE_ADMIN) {
            throw new EmployeeException('Admins already hold full organization authority.');
        }

        $permissionKeys = Permissions::filterValid($permissionKeys);
        $before = $this->members->permissionKeysFor($memberId);

        $this->db->transaction(function () use ($memberId, $permissionKeys): void {
            $this->members->syncPermissions($memberId, $this->permissions->idsForKeys($permissionKeys));
        });

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::EMPLOYEE_PERMISSIONS_CHANGED,
            'organization_user',
            $memberId,
            [
                'email'   => $member['email'],
                'before'  => $before,
                'after'   => $permissionKeys,
            ]
        );
    }

    /** @throws EmployeeException */
    public function removeMember(OrganizationContext $context, int $memberId): void
    {
        $context->authorizeAdmin();

        $member = $this->members->findInOrganization($memberId, $context->organizationId());

        if ($member === null) {
            throw new EmployeeException('Employee not found.');
        }

        if ((int) $member['user_id'] === $context->userId()) {
            throw new EmployeeException('You cannot remove yourself from the organization.');
        }

        if ((string) $member['role'] === OrganizationContext::ROLE_ADMIN
            && $this->members->countAdmins($context->organizationId()) <= 1
        ) {
            throw new EmployeeException('An organization must always keep at least one admin.');
        }

        $this->members->remove($memberId, $context->organizationId());

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            AuditService::EMPLOYEE_REMOVED,
            'organization_user',
            $memberId,
            ['email' => $member['email'], 'role' => $member['role']]
        );
    }

    public function members(): OrganizationUserRepository
    {
        return $this->members;
    }

    public function invitations(): InvitationRepository
    {
        return $this->invitations;
    }
}
