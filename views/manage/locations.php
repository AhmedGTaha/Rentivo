<?php
/**
 * Location management.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array      $locations
 * @var array|null $editing
 * @var array      $errors, $old
 * @var string     $orgSlug
 */

use Rentivo\Security\Permissions;

$locations = $locations ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$old = $old ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$canManage = $context->can(Permissions::LOCATIONS_MANAGE);

$value = static function (string $key, $fallback = '') use ($old, $editing) {
    return $old[$key] ?? ($editing[$key] ?? $fallback);
};
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Operations</p>
        <h1 class="page-header__title">Locations</h1>
        <p class="page-header__description">
            Where customers collect and return your vehicles.
        </p>
    </div>
</div>

<div class="grid grid-2" style="align-items: start;">
    <div class="stack">
        <?php if ($locations === []): ?>
            <?= component('feedback/empty-state', [
                'title'       => 'No locations yet',
                'description' => 'Add at least one location so customers know where to collect a car.',
                'icon'        => 'map-pin',
            ]) ?>
        <?php else: ?>
            <?php foreach ($locations as $location): ?>
                <article class="card card--padded">
                    <div class="row row-between row-wrap" style="align-items: flex-start;">
                        <div class="row" style="gap: var(--space-4); min-width: 0;">
                            <span class="location-card__icon">
                                <?= component('primitives/icon', ['name' => 'map-pin', 'size' => 18]) ?>
                            </span>
                            <div style="min-width: 0;">
                                <p class="text-strong"><?= e($location['name']) ?></p>
                                <p class="text-xs text-muted"><?= e($location['address']) ?></p>
                                <?php if (($location['opening_hours'] ?? null) !== null): ?>
                                    <p class="text-xs text-muted"><?= e($location['opening_hours']) ?></p>
                                <?php endif; ?>
                                <?php if (($location['phone'] ?? null) !== null): ?>
                                    <p class="text-xs text-muted"><?= e($location['phone']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row" style="gap: var(--space-2);">
                            <?= component('primitives/badge', [
                                'label' => (int) $location['is_active'] === 1 ? 'Active' : 'Inactive',
                                'tone'  => (int) $location['is_active'] === 1 ? 'success' : 'neutral',
                                'small' => true,
                            ]) ?>
                            <?= component('primitives/badge', [
                                'label' => (int) ($location['car_count'] ?? 0) . ' cars',
                                'tone'  => 'outline',
                                'small' => true,
                            ]) ?>
                            <?php if ($canManage): ?>
                                <a class="btn btn--secondary btn--sm"
                                   href="<?= e($base) ?>/locations?edit=<?= (int) $location['id'] ?>">Edit</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <form class="card card--padded" method="post"
              action="<?= e($base) ?>/locations<?= $editing !== null ? '/' . (int) $editing['id'] : '' ?>">
            <?= csrf_field() ?>

            <h2 class="card__title" style="margin-bottom: var(--space-5);">
                <?= $editing !== null ? 'Edit location' : 'Add a location' ?>
            </h2>

            <div class="stack">
                <?= component('forms/field', [
                    'name' => 'name', 'label' => 'Location name', 'required' => true,
                    'value' => $value('name'), 'errors' => $errors,
                    'attributes' => ['placeholder' => 'Airport counter, City branch…'],
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'address', 'label' => 'Address', 'required' => true,
                    'value' => $value('address'), 'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'phone', 'label' => 'Phone', 'type' => 'tel',
                    'value' => $value('phone'), 'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'opening_hours', 'label' => 'Opening hours',
                    'value' => $value('opening_hours'), 'errors' => $errors,
                    'attributes' => ['placeholder' => 'Sun–Thu 08:00–20:00'],
                ]) ?>

                <div class="filter-panel__row">
                    <?= component('forms/field', [
                        'name' => 'latitude', 'label' => 'Latitude',
                        'value' => $value('latitude'), 'errors' => $errors,
                        'attributes' => ['inputmode' => 'decimal', 'placeholder' => '26.2285'],
                    ]) ?>
                    <?= component('forms/field', [
                        'name' => 'longitude', 'label' => 'Longitude',
                        'value' => $value('longitude'), 'errors' => $errors,
                        'attributes' => ['inputmode' => 'decimal', 'placeholder' => '50.5860'],
                    ]) ?>
                </div>

                <?= component('forms/toggle', [
                    'name'    => 'is_active',
                    'label'   => 'Active and bookable',
                    'checked' => $editing === null ? true : (int) ($editing['is_active'] ?? 1) === 1,
                ]) ?>
            </div>

            <div class="form-actions">
                <?= component('primitives/button', [
                    'label' => $editing !== null ? 'Save location' : 'Add location',
                ]) ?>
                <?php if ($editing !== null): ?>
                    <?= component('primitives/button', [
                        'label'   => 'Cancel',
                        'href'    => $base . '/locations',
                        'variant' => 'ghost',
                    ]) ?>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>
