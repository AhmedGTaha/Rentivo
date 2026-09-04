<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\GoogleAuthException;
use Rentivo\Auth\GoogleAuthService;
use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\UserRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\RateLimiter;
use Rentivo\Support\Config;
use Rentivo\Support\Flash;

/**
 * Google-only authentication.
 *
 * There is no password anywhere in this controller: no registration form, no
 * reset flow, no credential check. The same Google round trip both creates an
 * account and signs an existing user in.
 */
final class AuthController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private GoogleAuthService $google,
        private UserRepository $users,
        private RateLimiter $rateLimiter
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /login */
    public function login(Request $request): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/account');
        }

        // Remember where the visitor was heading, so they land back there.
        $intended = $request->queryParam('redirect');

        if (is_string($intended) && $intended !== '') {
            $this->auth->setIntendedUrl($intended);
        }

        return $this->render('auth/login', [
            'title'             => 'Sign in',
            'googleConfigured'  => $this->google->isConfigured(),
            'configurationHint' => $this->google->configurationHint(),
            'intendedBooking'   => $this->auth->peekIntendedBooking(),
        ]);
    }

    /** GET /auth/google */
    public function redirectToGoogle(Request $request): Response
    {
        if (!$this->google->isConfigured()) {
            Flash::error('Google sign-in is not configured on this environment.');

            return $this->redirect('/login');
        }

        $this->rateLimiter->enforce(
            'google_login:' . $request->ip(),
            20,
            300,
            'Too many sign-in attempts. Please wait a few minutes and try again.'
        );

        return Response::redirect($this->google->authorizationUrl())
            ->withHeader('Cache-Control', 'no-store');
    }

    /** GET /auth/google/callback */
    public function handleGoogleCallback(Request $request): Response
    {
        if (!$this->google->isConfigured()) {
            Flash::error('Google sign-in is not configured on this environment.');

            return $this->redirect('/login');
        }

        try {
            $identity = $this->google->handleCallback(
                $this->stringParam($request, 'code'),
                $this->stringParam($request, 'state'),
                $this->stringParam($request, 'error')
            );
        } catch (GoogleAuthException $e) {
            Flash::error($e->getMessage());

            return $this->redirect('/login');
        }

        $result = $this->google->upsertUser($identity);
        $user = $result['user'];

        if ($user === []) {
            Flash::error('Your account could not be created. Please try again.');

            return $this->redirect('/login');
        }

        // Regenerates the session id and rotates the CSRF token.
        $this->auth->login((int) $user['id']);
        $this->users->profileOrCreate((int) $user['id']);

        Flash::success($result['created']
            ? 'Welcome to ' . Config::get('name', 'Rentivo') . ', ' . $user['name'] . '.'
            : 'Welcome back, ' . $user['name'] . '.');

        // Returns the visitor exactly where they were, including a booking
        // checkout they had already started.
        return $this->redirect($this->auth->pullIntendedUrl('/account'));
    }

    /** POST /logout */
    public function logout(Request $request): Response
    {
        $this->auth->logout();

        Flash::success('You have been signed out.');

        return $this->redirect('/');
    }

    private function stringParam(Request $request, string $key): ?string
    {
        $value = $request->queryParam($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
