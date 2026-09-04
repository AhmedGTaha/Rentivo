<?php
/**
 * Sign-in page.
 *
 * Google is the only authentication method: there is no password field, no
 * registration form and no reset flow anywhere in Rentivo.
 *
 * @var bool        $googleConfigured
 * @var string      $configurationHint
 * @var array|null  $intendedBooking
 */

$googleConfigured = $googleConfigured ?? false;
$configurationHint = $configurationHint ?? '';
$intendedBooking = $intendedBooking ?? null;
$isLocal = $isLocal ?? false;
?>

<div class="auth-layout">
    <div class="auth-card">
        <p class="eyebrow">Rentivo</p>
        <h1 class="auth-card__title">Sign in to continue</h1>
        <p class="auth-card__lead">
            Rentivo uses your Google account. The same button creates your account
            the first time and signs you in every time after.
        </p>

        <?php if ($intendedBooking !== null): ?>
            <div class="auth-card__context">
                <?= component('primitives/icon', ['name' => 'car', 'size' => 18]) ?>
                <span class="text-sm">
                    Your car selection is saved. You will return to checkout
                    straight after signing in.
                </span>
            </div>
        <?php endif; ?>

        <div class="auth-card__action">
            <?php if ($googleConfigured): ?>
                <a class="btn btn--google btn--lg btn--block" href="/auth/google">
                    <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18">
                        <path fill="#FFC107" d="M21.8 10H12v4h5.6A5.9 5.9 0 0 1 6.1 12 5.9 5.9 0 0 1 12 6.1c1.5 0 2.9.6 3.9 1.5l2.8-2.8A9.9 9.9 0 1 0 12 21.9c5 0 9.5-3.6 9.5-9.9 0-.7-.1-1.3-.2-2z"/>
                        <path fill="#FF3D00" d="m3.2 7.3 3.3 2.4A5.9 5.9 0 0 1 12 6.1c1.5 0 2.9.6 3.9 1.5l2.8-2.8A9.9 9.9 0 0 0 3.2 7.3z"/>
                        <path fill="#4CAF50" d="M12 21.9c2.6 0 5-.9 6.8-2.6l-3.1-2.6a5.9 5.9 0 0 1-9.1-3l-3.3 2.5A9.9 9.9 0 0 0 12 21.9z"/>
                        <path fill="#1976D2" d="M21.8 10H12v4h5.6a6 6 0 0 1-2 2.7l3.1 2.6c-.2.2 3.4-2.4 3.4-7.3 0-.7-.1-1.3-.3-2z"/>
                    </svg>
                    Continue with Google
                </a>
            <?php else: ?>
                <?= component('feedback/alert', [
                    'type'    => 'warning',
                    'title'   => 'Google sign-in is not configured',
                    'message' => $isLocal
                        ? $configurationHint
                        : 'Sign-in is temporarily unavailable. Please try again later.',
                ]) ?>
            <?php endif; ?>
        </div>

        <p class="auth-card__legal">
            We never receive or store a password. Rentivo reads only your name,
            email address and profile picture from Google.
        </p>

        <div class="divider divider--labelled" style="margin-top: var(--space-7);">or</div>

        <?= component('primitives/button', [
            'label'   => 'Keep browsing without an account',
            'href'    => '/cars',
            'variant' => 'ghost',
            'block'   => true,
        ]) ?>
    </div>
</div>
