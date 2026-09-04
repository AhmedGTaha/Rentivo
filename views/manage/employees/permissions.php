<?php
/**
 * Employee permission editor. Admin-only.
 *
 * Employees can never reach this page: EmployeeManagementController asserts
 * authorizeAdmin() on both the GET and the POST.
 *
 * @var array  $member, $assigned, $permissionGroups
 * @var string $orgSlug
 */

$member = $member ?? [];
$assigned = $assigned ?? [];
$permissionGroups = $permissionGroups ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Employees', 'href' => $base . '/employees'],
    ['label' => (string) ($member['name'] ?? '')],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <div class="row" style="gap: var(--space-4);">
            <?= component('primitives/avatar', [
                'imageUrl' => $member['google_avatar_url'] ?? null,
                'name'     => (string) ($member['name'] ?? ''),
                'size'     => 'lg',
            ]) ?>
            <div style="min-width: 0;">
                <p class="eyebrow">Permissions</p>
                <h1 class="page-header__title"><?= e($member['name'] ?? '') ?></h1>
                <p class="page-header__description" style="overflow-wrap: anywhere;">
                    <?= e($member['email'] ?? '') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<form method="post" action="<?= e($base) ?>/employees/<?= (int) ($member['id'] ?? 0) ?>/permissions"
      data-guard-submit>
    <?= csrf_field() ?>

    <?= component('feedback/alert', [
        'type'    => 'info',
        'message' => 'Unchecking a permission removes that ability immediately, '
            . 'including for any page they already have open.',
    ]) ?>

    <div style="margin-top: var(--space-6);">
        <?= component('organizations/permission-matrix', [
            'permissionGroups' => $permissionGroups,
            'assigned'         => $assigned,
        ]) ?>
    </div>

    <div class="form-actions">
        <?= component('primitives/button', ['label' => 'Save permissions']) ?>
        <?= component('primitives/button', [
            'label'   => 'Cancel',
            'href'    => $base . '/employees',
            'variant' => 'ghost',
        ]) ?>
    </div>
</form>
