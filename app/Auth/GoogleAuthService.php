<?php

declare(strict_types=1);

namespace Rentivo\Auth;

use Google\Client as GoogleClient;
use Rentivo\Repositories\UserRepository;
use Rentivo\Support\Config;
use Rentivo\Support\Logger;
use Rentivo\Support\Session;
use Throwable;

/**
 * Google OAuth 2.0, the only authentication method in Rentivo.
 *
 * The flow is:
 *   1. authorizationUrl() stores a random state in the session and redirects
 *   2. Google returns to the callback with code + state
 *   3. handleCallback() verifies the state, exchanges the code server-side,
 *      and validates the returned ID token
 *   4. the verified identity is upserted into `users`
 *
 * Access and refresh tokens are deliberately not persisted: V1 makes no
 * further Google API calls on the user's behalf.
 */
final class GoogleAuthService
{
    private const STATE_KEY = '_google_oauth_state';

    public function __construct(private UserRepository $users)
    {
    }

    /** True when GOOGLE_CLIENT_ID/SECRET are present in the environment. */
    public function isConfigured(): bool
    {
        return (string) Config::get('google.client_id', '') !== ''
            && (string) Config::get('google.client_secret', '') !== '';
    }

    public function redirectUri(): string
    {
        return (string) Config::get('google.redirect_uri', '');
    }

    public function client(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) Config::get('google.client_id'));
        $client->setClientSecret((string) Config::get('google.client_secret'));
        $client->setRedirectUri($this->redirectUri());
        $client->setScopes(['openid', 'email', 'profile']);
        $client->setAccessType('online');
        $client->setPrompt('select_account');

        return $client;
    }

    /**
     * Builds the Google consent URL and remembers the CSRF state value.
     */
    public function authorizationUrl(): string
    {
        $state = bin2hex(random_bytes(24));
        Session::put(self::STATE_KEY, $state);

        $client = $this->client();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Exchanges the callback code for a verified identity.
     *
     * @return array{google_id:string,email:string,name:string,avatar:?string,email_verified:bool}
     *
     * @throws GoogleAuthException
     */
    public function handleCallback(?string $code, ?string $state, ?string $error = null): array
    {
        if ($error !== null && $error !== '') {
            throw new GoogleAuthException('Google sign-in was cancelled.');
        }

        $expected = Session::pull(self::STATE_KEY);

        // The state is single-use and must match exactly.
        if (!is_string($expected) || $expected === '' || !is_string($state) || !hash_equals($expected, $state)) {
            throw new GoogleAuthException('The sign-in request could not be verified. Please try again.');
        }

        if ($code === null || $code === '') {
            throw new GoogleAuthException('Google did not return an authorization code.');
        }

        try {
            $client = $this->client();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new GoogleAuthException('Google rejected the sign-in request.');
            }

            $idToken = $token['id_token'] ?? null;

            if (!is_string($idToken) || $idToken === '') {
                throw new GoogleAuthException('Google did not return an identity token.');
            }

            // Server-side signature/audience verification.
            $payload = $client->verifyIdToken($idToken);

            if (!is_array($payload)) {
                throw new GoogleAuthException('The Google identity token could not be verified.');
            }
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::exception($e, ['context' => 'google_oauth_callback']);

            throw new GoogleAuthException('Google sign-in failed. Please try again.');
        }

        return $this->identityFromPayload($payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{google_id:string,email:string,name:string,avatar:?string,email_verified:bool}
     *
     * @throws GoogleAuthException
     */
    public function identityFromPayload(array $payload): array
    {
        $subject = (string) ($payload['sub'] ?? '');
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $verified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($subject === '' || $email === '') {
            throw new GoogleAuthException('Google did not return a usable account identity.');
        }

        if (!$verified) {
            throw new GoogleAuthException('Your Google email address must be verified before signing in.');
        }

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $name = strstr($email, '@', true) ?: $email;
        }

        $avatar = $payload['picture'] ?? null;

        return [
            'google_id'      => $subject,
            'email'          => $email,
            'name'           => mb_substr($name, 0, 191),
            'avatar'         => is_string($avatar) && $avatar !== '' ? mb_substr($avatar, 0, 512) : null,
            'email_verified' => true,
        ];
    }

    /**
     * Creates or refreshes the local user for a verified Google identity.
     *
     * Matching is by Google subject id first, then by email so an existing
     * account is never duplicated.
     *
     * @param array{google_id:string,email:string,name:string,avatar:?string,email_verified:bool} $identity
     *
     * @return array{user:array<string,mixed>,created:bool}
     */
    public function upsertUser(array $identity): array
    {
        $existing = $this->users->findByGoogleId($identity['google_id'])
            ?? $this->users->findByEmail($identity['email']);

        if ($existing !== null) {
            $this->users->updateFromGoogle((int) $existing['id'], $identity);

            return [
                'user'    => $this->users->find((int) $existing['id']) ?? $existing,
                'created' => false,
            ];
        }

        $userId = $this->users->createFromGoogle($identity);

        return [
            'user'    => $this->users->find($userId) ?? [],
            'created' => true,
        ];
    }

    /** Guidance shown in local development when credentials are absent. */
    public function configurationHint(): string
    {
        return 'Google sign-in is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and '
            . 'GOOGLE_REDIRECT_URI in your .env file, and register '
            . $this->redirectUri() . ' as an authorized redirect URI in the Google Cloud console.';
    }
}
