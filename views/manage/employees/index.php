<?php
/**
 * Employee list and pending invitations. Admin-only.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array  $members, $pendingInvitations, $permissionGroups
 * @var string $orgSlug
 */

use Rentivo\Security\OrganizationContext;

$members = $members ?? [];
$pendingInvitations = $pendingInvitations ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Team</p>
        <h1 class="page-header__title">Employees</h1>
        <p class="page-header__description">
            Administrators have full authority. Employees only get the permissions
            you grant them, and every one is enforced server-side.
        </p>
    </div>
    <div class="page-header__actions">
        <?= component('primitives/button', [
            'label' => 'Invite an employee',
            'href'  => $base . '/employees/invite',
            'icon'  => 'plus',
        ]) ?>
    </div>
</div>

<?php if ($pendingInvitations !== []): ?>
    <section style="margin-bottom: var(--space-7);">
        <h2 class="eyebrow" style="margin-bottom: var(--space-4);">Pending invitations</h2>

        <div class="stack">
            <?php foreach ($pendingInvitations as $invitation): ?>
                <article class="employee-card">
                    <div class="employee-card__identity">
                        <span class="avatar">
                            <?= component('primitives/icon', ['name' => 'mail', 'size' => 18]) ?>
                        </span>
                        <div style="min-width: 0;">
                            <p class="employee-card__name"><?= e($invitation['email']) ?></p>
                            <p class="employee-card__email">
                                Invited by <?= e($invitation['invited_by_name'] ?? 'an admin') ?>
                                · expires <?= e(date_display((string) $invitation['expires_at'])) ?>
                            </p>
                        </div>
                    </div>

                    <div class="employee-card__actions">
                        <?= component('primitives/badge', ['label' => 'Pending', 'tone' => 'warning']) ?>
                        <form method="post"
                              action="<?= e($base) ?>/employees/invitations/<?= (int) $invitation['id'] ?>/revoke"
                              data-confirm="The invitation link will stop working immediately."
                              data-confirm-title="Revoke this invitation?"
                              data-confirm-label="Revoke"
                              data-confirm-destructive="1">
                            <?= csrf_field() ?>
                            <?= component('primitives/button', [
                                'label'   => 'Revoke',
                                'variant' => 'danger',
                                'size'    => 'sm',
                            ]) ?>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section>
    <h2 class="eyebrow" style="margin-bottom: var(--space-4);">Team members</h2>

    <div class="stack">
        <?php foreach ($members as $member): ?>
            <?php $isAdmin = (string) $member['role'] === OrganizationContext::ROLE_ADMIN; ?>
            <article class="employee-card">
                <div class="employee-card__identity">
                    <?= component('primitives/avatar', [
                        'imageUrl' => $member['google_avatar_url'] ?? null,
                        'name'     => (string) $member['name'],
                    ]) ?>
                    <div style="min-width: 0;">
                        <p class="employee-card__name"><?= e($member['name']) ?></p>
                        <p class="employee-card__email"><?= e($member['email']) ?></p>
                    </div>
                </div>

                <div class="employee-card__actions">
                    <?= component('primitives/badge', [
                        'label' => $isAdmin ? 'Administrator' : 'Employee',
                        'tone'  => $isAdmin ? 'ink' : 'neutral',
                    ]) ?>

                    <?php if (!$isAdmin): ?>
                        <?= component('primitives/badge', [
                            'label' => (int) ($member['permission_count'] ?? 0) . ' permissions',
                            'tone'  => 'outline',
                        ]) ?>
                        <?= component('primitives/button', [
                            'label'   => 'Permissions',
                            'href'    => $base . '/employees/' . (int) $member['id'] . '/permissions',
                            'variant' => 'secondary',
                            'size'    => 'sm',
                        ]) ?>
                    <?php else: ?>
                        <span class="text-xs text-muted">Full organization authority</span>
                    <?php endif; ?>

                    <?php if ((int) $member['user_id'] !== $context->userId()): ?>
                        <form method="post"
                              action="<?= e($base) ?>/employees/<?= (int) $member['id'] ?>/remove"
                              data-confirm="<?= e($member['name']) ?> will lose access immediately. Their activity history is kept."
                              data-confirm-title="Remove this member?"
                              data-confirm-label="Remove"
                              data-confirm-destructive="1">
                            <?= csrf_field() ?>
                            <?= component('primitives/button', [
                                'label'   => 'Remove',
                                'variant' => 'danger',
                                'size'    => 'sm',
                            ]) ?>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
