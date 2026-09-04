<?php
/**
 * Organization settings. Admin-only.
 *
 * @var array  $organization
 * @var array  $errors, $old
 * @var string $orgSlug
 */

$organization = $organization ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$value = static function (string $key, $fallback = '') use ($old, $organization) {
    return $old[$key] ?? ($organization[$key] ?? $fallback);
};

$logo = $organization['logo_path'] ?? null;
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Organization</p>
        <h1 class="page-header__title">Settings</h1>
        <p class="page-header__description">
            Your public storefront address is
            <a href="/agency/<?= e(rawurlencode($orgSlug)) ?>">/agency/<?= e($orgSlug) ?></a>
            and does not change when you rename the organization.
        </p>
    </div>
</div>

<form class="card card--padded" method="post" action="<?= e($base) ?>/settings"
      enctype="multipart/form-data" data-guard-submit>
    <?= csrf_field() ?>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Public details</legend>

        <div class="form-grid form-grid--2">
            <?= component('forms/field', [
                'name' => 'name', 'label' => 'Organization name', 'required' => true,
                'value' => $value('name'), 'errors' => $errors,
            ]) ?>
            <?= component('forms/field', [
                'name' => 'contact_email', 'label' => 'Contact email', 'type' => 'email',
                'required' => true, 'value' => $value('contact_email'), 'errors' => $errors,
            ]) ?>
            <?= component('forms/field', [
                'name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true,
                'value' => $value('phone'), 'errors' => $errors,
            ]) ?>
            <?= component('forms/field', [
                'name' => 'address', 'label' => 'Address',
                'value' => $value('address'), 'errors' => $errors,
            ]) ?>
            <div class="form-grid__full">
                <?= component('forms/field', [
                    'name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                    'value' => $value('description'), 'errors' => $errors,
                ]) ?>
            </div>
        </div>
    </fieldset>

    <div class="divider"></div>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Branding</legend>

        <div class="profile-avatar" style="border: none; padding-bottom: 0; margin-bottom: var(--space-5);">
            <span class="agency-header__logo">
                <?php if ($logo !== null && $logo !== ''): ?>
                    <img src="<?= e('/uploads/' . ltrim((string) $logo, '/')) ?>" alt="">
                <?php else: ?>
                    <?= e(\Rentivo\Support\Str::initials((string) ($organization['name'] ?? ''))) ?>
                <?php endif; ?>
            </span>
            <div class="grow">
                <p class="text-strong">Organization logo</p>
                <p class="profile-avatar__hint">
                    Shown on your storefront, car cards and the management console.
                    JPEG, PNG or WebP.
                </p>
                <div style="margin-top: var(--space-3); max-width: 320px;">
                    <input class="input" type="file" name="logo"
                           accept="image/jpeg,image/png,image/webp"
                           aria-label="Upload an organization logo">
                </div>
            </div>
        </div>

        <div class="form-grid form-grid--2">
            <?= component('forms/field', [
                'name' => 'primary_color', 'label' => 'Brand accent colour', 'type' => 'color',
                'value' => $value('primary_color', '#111111'), 'errors' => $errors,
                'hint' => 'Applied as a small accent only; it never re-themes the platform.',
            ]) ?>
        </div>
    </fieldset>

    <div class="divider"></div>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Rental terms</legend>

        <?= component('forms/field', [
            'name' => 'rental_terms', 'label' => 'Rental terms', 'type' => 'textarea',
            'value' => $value('rental_terms'), 'errors' => $errors,
            'hint' => 'Shown on your storefront, every car page and every booking.',
            'attributes' => ['rows' => 8],
        ]) ?>
    </fieldset>

    <div class="divider"></div>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Visibility</legend>

        <?= component('forms/toggle', [
            'name'    => 'is_active',
            'label'   => 'Listed on the public marketplace',
            'checked' => (int) ($organization['is_active'] ?? 1) === 1,
        ]) ?>

        <p class="field__hint" style="margin-top: var(--space-3);">
            Turning this off hides your storefront and every car from the public
            marketplace. Existing bookings are unaffected and your team keeps access.
        </p>
    </fieldset>

    <div class="form-actions">
        <?= component('primitives/button', ['label' => 'Save settings']) ?>
        <?= component('primitives/button', [
            'label'   => 'View storefront',
            'href'    => '/agency/' . rawurlencode($orgSlug),
            'variant' => 'ghost',
            'icon'    => 'external',
        ]) ?>
    </div>
</form>
