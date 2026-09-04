<?php
/**
 * Employee invitation acceptance.
 *
 * The page is readable without signing in so an invitee can see what they are
 * being offered, but acceptance requires a Google session whose verified email
 * exactly matches the invited address — enforced server-side regardless of
 * what this page shows.
 *
 * @var array|null $invitation
 * @var string     $state         valid|expired|accepted|revoked|invalid
 * @var string     $token
 * @var bool       $emailMatches
 * @var array      $permissions
 * @var array      $permissionLabels
 * @var array|null $currentUser
 */

$invitation = $invitation ?? null;
$state = $state ?? 'invalid';
$token = $token ?? '';
$emailMatches = $emailMatches ?? false;
$permissions = $permissions ?? [];
$permissionLabels = $permissionLabels ?? [];
$currentUser = $currentUser ?? null;
?>

<div class="auth-layout">
    <div class="auth-card" style="max-width: 520px;">
        <?php if ($state !== 'valid' || $invitation === null): ?>
            <div class="state__icon" style="margin: 0 auto var(--space-4);">
                <?= component('primitives/icon', ['name' => 'x-circle', 'size' => 24]) ?>
            </div>

            <h1 class="auth-card__title">
                <?= e(match ($state) {
                    'accepted' => 'Invitation already used',
                    'revoked'  => 'Invitation revoked',
                    'expired'  => 'Invitation expired',
                    default    => 'Invitation not valid',
                }) ?>
            </h1>

            <p class="auth-card__lead">
                <?= e(match ($state) {
                    'accepted' => 'This invitation has already been accepted. If that was you, sign in and open the organization from your account menu.',
                    'revoked'  => 'The organization withdrew this invitation. Ask them to send a new one.',
                    'expired'  => 'Invitations are valid for seven days. Ask the organization for a fresh link.',
                    default    => 'This link is not a valid invitation. Check that you copied the whole address.',
                }) ?>
            </p>

            <div class="auth-card__action">
                <?= component('primitives/button', [
                    'label' => 'Go to your account',
                    'href'  => '/account',
                    'block' => true,
                ]) ?>
            </div>
        <?php else: ?>
            <p class="eyebrow">Organization invitation</p>
            <h1 class="auth-card__title">Join <?= e($invitation['organization_name']) ?></h1>
            <p class="auth-card__lead">
                You have been invited to help manage
                <strong><?= e($invitation['organization_name']) ?></strong> on Rentivo.
            </p>

            <div class="auth-card__context" style="display: block;">
                <p class="text-xs text-muted" style="margin-bottom: var(--space-2);">
                    This invitation is for
                </p>
                <p class="text-strong" style="overflow-wrap: anywhere;">
                    <?= e($invitation['email']) ?>
                </p>
            </div>

            <?php if ($permissions !== []): ?>
                <div style="margin-top: var(--space-5); text-align: left;">
                    <p class="eyebrow" style="margin-bottom: var(--space-3);">You will be able to</p>
                    <div class="permission-summary">
                        <?php foreach ($permissions as $key): ?>
                            <?= component('primitives/badge', [
                                'label' => $permissionLabels[$key] ?? $key,
                                'tone'  => 'neutral',
                                'small' => true,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-xs text-muted" style="margin-top: var(--space-5);">
                    No specific permissions have been assigned yet. An administrator
                    can grant them after you join.
                </p>
            <?php endif; ?>

            <div class="auth-card__action">
                <?php if ($currentUser === null): ?>
                    <form method="post" action="/invitations/<?= e(rawurlencode($token)) ?>/accept">
                        <?= csrf_field() ?>
                        <?= component('primitives/button', [
                            'label' => 'Sign in with Google to accept',
                            'size'  => 'lg',
                            'block' => true,
                        ]) ?>
                    </form>
                <?php elseif (!$emailMatches): ?>
                    <?= component('feedback/alert', [
                        'type'    => 'warning',
                        'title'   => 'Wrong Google account',
                        'message' => 'You are signed in as ' . ($currentUser['email'] ?? '')
                            . '. Sign in with ' . $invitation['email'] . ' to accept this invitation.',
                    ]) ?>
                    <form method="post" action="/logout" style="margin-top: var(--space-4);">
                        <?= csrf_field() ?>
                        <?= component('primitives/button', [
                            'label'   => 'Sign out and switch account',
                            'variant' => 'secondary',
                            'block'   => true,
                        ]) ?>
                    </form>
                <?php else: ?>
                    <form method="post" action="/invitations/<?= e(rawurlencode($token)) ?>/accept"
                          data-guard-submit>
                        <?= csrf_field() ?>
                        <?= component('primitives/button', [
                            'label' => 'Accept invitation',
                            'size'  => 'lg',
                            'block' => true,
                        ]) ?>
                    </form>
                <?php endif; ?>
            </div>

            <p class="auth-card__legal">
                Joining an organization does not change your customer account. You can
                still book cars as usual.
            </p>
        <?php endif; ?>
    </div>
</div>
