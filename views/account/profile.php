<?php
/**
 * Customer profile.
 *
 * The email address is read-only: it comes from the verified Google identity
 * and is the account's key.
 *
 * @var array       $profile
 * @var array|null  $currentUser
 * @var array       $errors
 * @var array       $old
 * @var string|null $redirect  Where to return after saving (e.g. back to checkout)
 */

$profile = $profile ?? [];
$currentUser = $currentUser ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$redirect = $redirect ?? null;

$value = static function (string $key, $fallback = '') use ($old, $profile) {
    return $old[$key] ?? ($profile[$key] ?? $fallback);
};

$avatarUrl = ($profile['profile_image_path'] ?? null) !== null
    ? '/uploads/' . ltrim((string) $profile['profile_image_path'], '/')
    : ($currentUser['google_avatar_url'] ?? null);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Account</p>
        <h1 class="page-header__title">Profile</h1>
        <p class="page-header__description">
            Rental agencies see your name, phone number and documents when you book
            with them.
        </p>
    </div>
</div>

<?php if ($redirect !== null): ?>
    <div style="margin-bottom: var(--space-6);">
        <?= component('feedback/alert', [
            'type'    => 'info',
            'message' => 'Add your phone number to continue with your booking.',
        ]) ?>
    </div>
<?php endif; ?>

<form class="card card--padded" method="post" action="/account/profile"
      enctype="multipart/form-data" data-guard-submit>
    <?= csrf_field() ?>
    <?php if ($redirect !== null): ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
    <?php endif; ?>

    <div class="profile-avatar">
        <?= component('primitives/avatar', [
            'imageUrl' => $avatarUrl,
            'name'     => (string) ($currentUser['name'] ?? ''),
            'size'     => 'xl',
        ]) ?>
        <div class="grow">
            <p class="text-strong">Profile picture</p>
            <p class="profile-avatar__hint">
                Upload your own image, or leave this empty to keep using your Google
                picture. JPEG, PNG or WebP.
            </p>
            <div style="margin-top: var(--space-3); max-width: 320px;">
                <input class="input" type="file" name="profile_image"
                       accept="image/jpeg,image/png,image/webp"
                       aria-label="Upload a profile picture">
            </div>
            <?php if (isset($errors['profile_image'])): ?>
                <p class="field__error" style="margin-top: var(--space-2);">
                    <?= component('primitives/icon', ['name' => 'alert', 'size' => 14]) ?>
                    <span><?= e($errors['profile_image']) ?></span>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-grid form-grid--2">
        <?= component('forms/field', [
            'name'     => 'name',
            'label'    => 'Full name',
            'value'    => $old['name'] ?? ($currentUser['name'] ?? ''),
            'required' => true,
            'errors'   => $errors,
            'attributes' => ['autocomplete' => 'name'],
        ]) ?>

        <?= component('forms/field', [
            'name'       => 'email_display',
            'label'      => 'Email address',
            'type'       => 'email',
            'value'      => (string) ($currentUser['email'] ?? ''),
            'hint'       => 'Provided by Google and cannot be changed here.',
            'attributes' => ['readonly' => true, 'disabled' => true],
        ]) ?>

        <?= component('forms/field', [
            'name'   => 'phone',
            'label'  => 'Phone number',
            'type'   => 'tel',
            'value'  => $value('phone'),
            'hint'   => 'Required before you can submit a booking.',
            'errors' => $errors,
            'attributes' => ['autocomplete' => 'tel', 'placeholder' => '+973 3xxx xxxx'],
        ]) ?>

        <?= component('forms/field', [
            'name'   => 'date_of_birth',
            'label'  => 'Date of birth',
            'type'   => 'date',
            'value'  => $value('date_of_birth'),
            'errors' => $errors,
        ]) ?>

        <?= component('forms/field', [
            'name'   => 'nationality',
            'label'  => 'Nationality',
            'value'  => $value('nationality'),
            'errors' => $errors,
        ]) ?>

        <?= component('forms/field', [
            'name'   => 'address',
            'label'  => 'Address',
            'value'  => $value('address'),
            'errors' => $errors,
            'attributes' => ['autocomplete' => 'street-address'],
        ]) ?>
    </div>

    <div class="form-actions">
        <?= component('primitives/button', ['label' => 'Save profile']) ?>
        <?= component('primitives/button', [
            'label'   => 'Cancel',
            'href'    => '/account',
            'variant' => 'ghost',
        ]) ?>
    </div>
</form>
