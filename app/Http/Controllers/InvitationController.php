<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Security\RateLimiter;
use Rentivo\Services\EmployeeException;
use Rentivo\Services\EmployeeService;
use Rentivo\Support\Flash;

/**
 * Employee invitation acceptance.
 *
 * The page is viewable without signing in so an invitee can see what they are
 * being invited to, but acceptance requires a Google session whose verified
 * email exactly matches the invited address.
 */
final class InvitationController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private EmployeeService $employees,
        private RateLimiter $rateLimiter
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /invitations/{token} */
    public function show(Request $request): Response
    {
        $token = (string) $request->route('token');

        $invitation = $this->employees->findInvitationByToken($token);
        $state = $this->employees->invitationState($invitation);

        $user = $this->auth->user();

        $emailMatches = $invitation !== null
            && $user !== null
            && strtolower((string) $user['email']) === strtolower((string) $invitation['email']);

        return $this->render('auth/invitation', [
            'title'        => 'Organization invitation',
            'invitation'   => $invitation,
            'state'        => $state,
            'token'        => $token,
            'emailMatches' => $emailMatches,
            'permissions'  => $invitation === null
                ? []
                : $this->employees->invitations()->permissionKeys((int) $invitation['id']),
            'permissionLabels' => Permissions::labels(),
            // The invitation card centres itself like the sign-in page.
            'fullWidth'    => true,
        ]);
    }

    /** POST /invitations/{token}/accept */
    public function accept(Request $request): Response
    {
        $token = (string) $request->route('token');

        // Guests are sent to Google and returned to this same invitation page.
        if ($this->auth->guest()) {
            $this->auth->setIntendedUrl('/invitations/' . rawurlencode($token));

            Flash::info('Sign in with the invited Google account to accept.');

            return $this->redirect('/login');
        }

        $user = $this->requireUser();

        $this->rateLimiter->enforce(
            'invitation_accept:' . $request->ip(),
            20,
            600,
            'Too many invitation attempts. Please wait and try again.'
        );

        try {
            $invitation = $this->employees->acceptInvitation($token, $user);

            Flash::success('You have joined ' . $invitation['organization_name'] . '.');

            return $this->redirect('/manage/' . $invitation['organization_slug']);
        } catch (EmployeeException $e) {
            Flash::error($e->getMessage());

            return $this->redirect('/invitations/' . rawurlencode($token));
        }
    }
}
