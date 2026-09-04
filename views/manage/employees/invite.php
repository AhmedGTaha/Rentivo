<?php
/**
 * Employee invitation form. Admin-only.
 *
 * @var array  $permissionGroups
 * @var bool   $mailConfigured
 * @var array  $errors, $old
 * @var string $orgSlug
 */

$permissionGroups = $permissionGroups ?? [];
$mailConfigured = $mailConfigured ?? false;
$errors = $errors ?? [];
$old = $old ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$selected = $old['permissions'] ?? [];
$selected = is_array($selected) ? array_map('strval', $selected) : [];
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Employees', 'href' => $base . '/employees'],
    ['label' => 'Invite'],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Team</p>
        <h1 class="page-header__title">Invite an employee</h1>
        <p class="page-header__description">
            The invitation is tied to one Google address. Only that verified Google
            account can accept it, and the link expires after seven days.
        </p>
    </div>
</div>

<?php if (!$mailConfigured): ?>
    <div style="margin-bottom: var(--space-6);">
        <?= component('feedback/alert', [
            'type'    => 'info',
            'title'   => 'SMTP is not configured',
            'message' => 'The invitation will still be created. In local development the '
                . 'link is shown to you afterwards so you can share it manually.',
        ]) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($base) ?>/employees/invite" data-guard-submit>
    <?= csrf_field() ?>

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <div style="max-width: 480px;">
            <?= component('forms/field', [
                'name'     => 'email',
                'label'    => 'Google email address',
                'type'     => 'email',
                'value'    => $old['email'] ?? '',
                'required' => true,
                'errors'   => $errors,
                'hint'     => 'Must be the exact address of their Google account.',
                'attributes' => ['placeholder' => 'colleague@example.com', 'autocomplete' => 'off'],
            ]) ?>
        </div>
    </div>

    <div class="page-header" style="margin-bottom: var(--space-5);">
        <div class="page-header__heading">
            <h2 class="section-header__title" style="font-size: var(--font-size-lg);">Permissions</h2>
            <p class="page-header__description">
                Grant only what this person needs. Every permission is checked on the
                server for every request, not just used to hide buttons.
            </p>
        </div>
    </div>

    <?= component('organizations/permission-matrix', [
        'permissionGroups' => $permissionGroups,
        'assigned'         => $selected,
    ]) ?>

    <div class="form-actions">
        <?= component('primitives/button', ['label' => 'Send invitation', 'size' => 'lg']) ?>
        <?= component('primitives/button', [
            'label'   => 'Cancel',
            'href'    => $base . '/employees',
            'variant' => 'ghost',
        ]) ?>
    </div>
</form>
