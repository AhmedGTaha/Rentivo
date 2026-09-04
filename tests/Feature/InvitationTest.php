<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Repositories\InvitationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\EmployeeException;
use Rentivo\Services\EmployeeService;
use Rentivo\Support\DateTimeHelper;
use Rentivo\Tests\TestCase;

/**
 * SRS Critical Acceptance Test 5: invitation email matching.
 *
 * Only the exact invited, verified Google address may accept an invitation,
 * and an invitation may only ever be used once.
 */
final class InvitationTest extends TestCase
{
    private array $organization;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminId = $this->createUser('admin@example.test', 'Admin');
        $this->organization = $this->createOrganization('Invite Agency', $this->adminId);
    }

    private function employees(): EmployeeService
    {
        return $this->app->get(EmployeeService::class);
    }

    private function adminContext(): OrganizationContext
    {
        $this->actingAs($this->adminId);

        return $this->contextFor((string) $this->organization['slug']);
    }

    /**
     * @return array{token:string,invitation_id:int}
     */
    private function invite(string $email, array $permissions = [Permissions::CARS_VIEW]): array
    {
        $result = $this->employees()->invite($this->adminContext(), $email, $permissions);

        return ['token' => $result['token'], 'invitation_id' => $result['invitation_id']];
    }

    // -----------------------------------------------------------------
    // Token storage
    // -----------------------------------------------------------------

    /** Only the hash is persisted; the raw token exists solely in the URL. */
    public function testOnlyTheTokenHashIsStored(): void
    {
        $invite = $this->invite('employee@example.test');

        $stored = $this->db()->selectOne(
            'SELECT token_hash FROM employee_invitations WHERE id = ?',
            [$invite['invitation_id']]
        );

        self::assertNotSame($invite['token'], $stored['token_hash']);
        self::assertSame(hash('sha256', $invite['token']), $stored['token_hash']);

        // The raw token appears nowhere in the row.
        $row = $this->db()->selectOne(
            'SELECT * FROM employee_invitations WHERE id = ?',
            [$invite['invitation_id']]
        );

        foreach ($row as $value) {
            self::assertNotSame($invite['token'], (string) $value);
        }
    }

    public function testInvitationExpiresInSevenDays(): void
    {
        $invite = $this->invite('employee@example.test');

        $row = $this->db()->selectOne(
            'SELECT expires_at FROM employee_invitations WHERE id = ?',
            [$invite['invitation_id']]
        );

        $expires = DateTimeHelper::fromDb((string) $row['expires_at']);
        $expected = DateTimeHelper::now()->modify('+7 days');

        // Allow a minute of slack for execution time.
        self::assertLessThan(60, abs($expires->getTimestamp() - $expected->getTimestamp()));
    }

    public function testInvalidTokenResolvesToNothing(): void
    {
        $this->invite('employee@example.test');

        self::assertNull($this->employees()->findInvitationByToken('not-a-token'));
        self::assertNull($this->employees()->findInvitationByToken(str_repeat('a', 64)));
        self::assertNull($this->employees()->findInvitationByToken(''));
    }

    // -----------------------------------------------------------------
    // SRS Test 5: email matching
    // -----------------------------------------------------------------

    public function testInvitedAddressCanAccept(): void
    {
        $invite = $this->invite('employee@example.test', [
            Permissions::CARS_VIEW,
            Permissions::BOOKINGS_VIEW,
        ]);

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);

        $this->employees()->acceptInvitation($invite['token'], $user);

        /** @var OrganizationUserRepository $members */
        $members = $this->app->get(OrganizationUserRepository::class);

        $membership = $members->findMembership((int) $this->organization['id'], $employeeId);

        self::assertNotNull($membership);
        self::assertSame(OrganizationContext::ROLE_EMPLOYEE, $membership['role']);

        // The invitation's permission selection was copied across.
        self::assertSame(
            ['bookings.view', 'cars.view'],
            $members->permissionKeysFor((int) $membership['id'])
        );
    }

    /** A different Google account must not be able to use the token. */
    public function testDifferentGoogleAccountCannotAccept(): void
    {
        $invite = $this->invite('employee@example.test');

        $intruderId = $this->createUser('intruder@example.test', 'Intruder');
        $intruder = $this->app->get(UserRepository::class)->find($intruderId);

        try {
            $this->employees()->acceptInvitation($invite['token'], $intruder);
            self::fail('A different Google account accepted the invitation.');
        } catch (EmployeeException $e) {
            self::assertStringContainsString('employee@example.test', $e->getMessage());
        }

        /** @var OrganizationUserRepository $members */
        $members = $this->app->get(OrganizationUserRepository::class);

        self::assertNull($members->findMembership((int) $this->organization['id'], $intruderId));

        // The invitation is still unused and available to its real recipient.
        $invitation = $this->employees()->findInvitationByToken($invite['token']);
        self::assertNull($invitation['accepted_at']);
    }

    /** Case differences in the email must not defeat the match. */
    public function testEmailMatchingIsCaseInsensitiveButExact(): void
    {
        $invite = $this->invite('Employee@Example.test');

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);

        $this->employees()->acceptInvitation($invite['token'], $user);

        self::assertNotNull(
            $this->app->get(OrganizationUserRepository::class)
                ->findMembership((int) $this->organization['id'], $employeeId)
        );
    }

    /** An unverified Google email may not accept, even if it matches. */
    public function testUnverifiedEmailCannotAccept(): void
    {
        $invite = $this->invite('employee@example.test');

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $this->db()->update('users', ['email_verified_at' => null], ['id' => $employeeId]);

        $user = $this->app->get(UserRepository::class)->find($employeeId);

        $this->expectException(EmployeeException::class);

        $this->employees()->acceptInvitation($invite['token'], $user);
    }

    // -----------------------------------------------------------------
    // Replay and lifecycle
    // -----------------------------------------------------------------

    /** A token is single-use: the second attempt must fail. */
    public function testInvitationCannotBeReplayed(): void
    {
        $invite = $this->invite('employee@example.test');

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);

        $this->employees()->acceptInvitation($invite['token'], $user);

        try {
            $this->employees()->acceptInvitation($invite['token'], $user);
            self::fail('The invitation was accepted twice.');
        } catch (EmployeeException) {
            // Expected.
        }

        // Exactly one membership exists, not two.
        $count = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM organization_users WHERE organization_id = ? AND user_id = ?',
            [(int) $this->organization['id'], $employeeId]
        );

        self::assertSame(1, $count);
    }

    public function testExpiredInvitationCannotBeAccepted(): void
    {
        $invite = $this->invite('employee@example.test');

        $this->db()->update(
            'employee_invitations',
            ['expires_at' => DateTimeHelper::now()->modify('-1 hour')->format(DateTimeHelper::DB_FORMAT)],
            ['id' => $invite['invitation_id']]
        );

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);

        self::assertSame(
            'expired',
            $this->employees()->invitationState($this->employees()->findInvitationByToken($invite['token']))
        );

        $this->expectException(EmployeeException::class);
        $this->employees()->acceptInvitation($invite['token'], $user);
    }

    public function testRevokedInvitationCannotBeAccepted(): void
    {
        $invite = $this->invite('employee@example.test');

        $this->employees()->revokeInvitation($this->adminContext(), $invite['invitation_id']);

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);

        $this->expectException(EmployeeException::class);
        $this->employees()->acceptInvitation($invite['token'], $user);
    }

    public function testCannotInviteAnExistingMemberTwice(): void
    {
        $invite = $this->invite('employee@example.test');

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);
        $this->employees()->acceptInvitation($invite['token'], $user);

        $this->expectException(EmployeeException::class);

        $this->employees()->invite($this->adminContext(), 'employee@example.test', []);
    }

    public function testCannotIssueTwoPendingInvitationsForTheSameAddress(): void
    {
        $this->invite('employee@example.test');

        $this->expectException(EmployeeException::class);

        $this->employees()->invite($this->adminContext(), 'employee@example.test', []);
    }

    /** An invitation cannot grant a permission outside the catalogue. */
    public function testInvitationPermissionsAreFilteredToTheCatalogue(): void
    {
        $invite = $this->invite('employee@example.test', [
            Permissions::CARS_VIEW,
            'organization.delete',
            '*',
        ]);

        /** @var InvitationRepository $invitations */
        $invitations = $this->app->get(InvitationRepository::class);

        self::assertSame(['cars.view'], $invitations->permissionKeys($invite['invitation_id']));
    }

    /** Accepting an invitation must not disturb the customer account. */
    public function testAcceptingDoesNotAffectTheCustomerIdentity(): void
    {
        $invite = $this->invite('employee@example.test');

        $employeeId = $this->createUser('employee@example.test', 'Employee');
        $user = $this->app->get(UserRepository::class)->find($employeeId);

        $this->employees()->acceptInvitation($invite['token'], $user);

        $after = $this->app->get(UserRepository::class)->find($employeeId);

        self::assertSame($user['email'], $after['email']);
        self::assertSame($user['google_id'], $after['google_id']);
    }
}
