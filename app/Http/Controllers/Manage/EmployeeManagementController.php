<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\InvitationRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Security\RateLimiter;
use Rentivo\Services\EmployeeException;
use Rentivo\Services\EmployeeService;
use Rentivo\Services\MailService;
use Rentivo\Support\Config;
use Rentivo\Support\Flash;

/**
 * Employee and permission administration.
 *
 * Admin-only throughout: employees can never reach these routes, and
 * authorizeAdmin() is asserted on every single action rather than relying on
 * the sidebar hiding the link.
 */
final class EmployeeManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private EmployeeService $employees,
        private OrganizationUserRepository $members,
        private InvitationRepository $invitations,
        private MailService $mail,
        private RateLimiter $rateLimiter
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/employees */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        return $this->renderManage($context, 'manage/employees/index', [
            'title'             => 'Employees',
            'manageSection'     => 'employees',
            'members'           => $this->members->listForOrganization($context->organizationId()),
            'pendingInvitations' => $this->invitations->listPending($context->organizationId()),
            'permissionGroups'  => Permissions::groups(),
        ]);
    }

    /** GET /manage/{org}/employees/invite */
    public function inviteForm(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        return $this->renderManage($context, 'manage/employees/invite', [
            'title'            => 'Invite an employee',
            'manageSection'    => 'employees',
            'permissionGroups' => Permissions::groups(),
            'mailConfigured'   => $this->mail->isConfigured(),
        ]);
    }

    /** POST /manage/{org}/employees/invite */
    public function invite(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        $this->rateLimiter->enforce(
            'invite_create:' . $context->organizationId(),
            30,
            3600,
            'Too many invitations sent recently. Please try again later.'
        );

        $validator = $this->validate($request, [
            'email' => 'required|email|max:191',
        ], [
            'email' => 'Google email address',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'employees/invite'),
                $validator->errors(),
                $request->body()
            );
        }

        try {
            $result = $this->employees->invite(
                $context,
                (string) $validator->value('email'),
                $request->inputArray('permissions')
            );

            if ($result['emailed']) {
                Flash::success('Invitation sent to ' . $validator->value('email') . '.');
            } else {
                Flash::warning('Invitation created, but no email was sent because SMTP is not configured.');

                // In local development the admin needs a usable link.
                if (Config::isLocal()) {
                    Flash::info('Invitation link: ' . $result['url']);
                }
            }
        } catch (EmployeeException $e) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'employees/invite'),
                ['email' => $e->getMessage()],
                $request->body()
            );
        }

        return $this->redirect($this->manageUrl($context, 'employees'));
    }

    /** POST /manage/{org}/employees/invitations/{id}/revoke */
    public function revokeInvitation(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        try {
            $this->employees->revokeInvitation($context, $request->routeInt('id'));

            Flash::success('Invitation revoked.');
        } catch (EmployeeException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'employees'));
    }

    /** GET /manage/{org}/employees/{id}/permissions */
    public function permissionsForm(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        $memberId = $request->routeInt('id');
        $member = $this->members->findInOrganization($memberId, $context->organizationId());

        if ($member === null) {
            throw HttpException::notFound('Employee not found in this organization.');
        }

        return $this->renderManage($context, 'manage/employees/permissions', [
            'title'             => 'Permissions — ' . $member['name'],
            'manageSection'     => 'employees',
            'member'            => $member,
            'assigned'          => $this->members->permissionKeysFor($memberId),
            'permissionGroups'  => Permissions::groups(),
        ]);
    }

    /** POST /manage/{org}/employees/{id}/permissions */
    public function updatePermissions(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        $memberId = $request->routeInt('id');

        try {
            $this->employees->updatePermissions($context, $memberId, $request->inputArray('permissions'));

            Flash::success('Permissions updated.');
        } catch (EmployeeException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'employees'));
    }

    /** POST /manage/{org}/employees/{id}/remove */
    public function remove(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAdmin();

        try {
            $this->employees->removeMember($context, $request->routeInt('id'));

            Flash::success('Employee removed. Their activity history has been kept.');
        } catch (EmployeeException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'employees'));
    }
}
