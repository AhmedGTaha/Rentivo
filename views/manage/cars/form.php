<?php
/**
 * Car create/edit form, plus image management when editing.
 *
 * Read-only staff (cars.view without cars.edit) see the same page with the
 * controls disabled; the server rejects their POST regardless.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array|null $car
 * @var array $images, $categories, $locations, $transmissions, $fuelTypes, $statuses
 * @var array $errors, $old
 * @var int   $maxImages
 * @var array $upcomingBookings
 * @var string $orgSlug
 */

use Rentivo\Security\Permissions;
use Rentivo\Services\CarService;
use Rentivo\Support\Currency;

$car = $car ?? null;
$images = $images ?? [];
$categories = $categories ?? [];
$locations = $locations ?? [];
$transmissions = $transmissions ?? CarService::transmissions();
$fuelTypes = $fuelTypes ?? CarService::fuelTypes();
$statuses = $statuses ?? CarService::statuses();
$errors = $errors ?? [];
$old = $old ?? [];
$maxImages = (int) ($maxImages ?? 10);
$upcomingBookings = $upcomingBookings ?? [];
$orgSlug = $orgSlug ?? '';

$base = '/manage/' . rawurlencode($orgSlug);
$isEdit = $car !== null;
$carId = $isEdit ? (int) $car['id'] : 0;

$canEdit = $context->can($isEdit ? Permissions::CARS_EDIT : Permissions::CARS_CREATE);
$canManageImages = $context->can(Permissions::CARS_MANAGE_IMAGES);
$canArchive = $context->can(Permissions::CARS_ARCHIVE);

$value = static function (string $key, $fallback = '') use ($old, $car) {
    return $old[$key] ?? ($car[$key] ?? $fallback);
};

$categoryOptions = ['' => 'No category'];
foreach ($categories as $category) {
    $categoryOptions[(string) $category['id']] = (string) $category['name'];
}

$locationOptions = ['' => 'No location'];
foreach ($locations as $location) {
    $locationOptions[(string) $location['id']] = (string) $location['name'];
}

$statusOptions = [];
foreach ($statuses as $status) {
    $statusOptions[$status] = CarService::statusLabel($status);
}

$rateValue = $old['daily_rate'] ?? ($isEdit ? Currency::toInput((int) $car['daily_rate_fils']) : '');
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Fleet', 'href' => $base . '/cars'],
    ['label' => $isEdit ? trim($car['brand'] . ' ' . $car['model']) : 'Add a car'],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow"><?= $isEdit ? 'Edit vehicle' : 'New vehicle' ?></p>
        <h1 class="page-header__title">
            <?= $isEdit ? e(trim($car['brand'] . ' ' . $car['model'] . ' ' . $car['year'])) : 'Add a car' ?>
        </h1>
        <?php if ($isEdit): ?>
            <div class="row row-wrap" style="margin-top: var(--space-3);">
                <?= component('cars/car-status-badge', ['status' => (string) $car['status']]) ?>
                <?php if (($car['archived_at'] ?? null) !== null): ?>
                    <?= component('primitives/badge', ['label' => 'Archived', 'tone' => 'neutral']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isEdit): ?>
        <div class="page-header__actions">
            <?= component('primitives/button', [
                'label'   => 'View publicly',
                'href'    => '/cars/' . rawurlencode((string) $car['slug']),
                'variant' => 'secondary',
                'icon'    => 'external',
            ]) ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$canEdit): ?>
    <div style="margin-bottom: var(--space-6);">
        <?= component('feedback/alert', [
            'type'    => 'info',
            'message' => 'You have view-only access to the fleet. Ask an administrator for edit permission to make changes.',
        ]) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2" style="align-items: start;">
    <form class="card card--padded" method="post"
          action="<?= e($base) ?>/cars<?= $isEdit ? '/' . $carId : '' ?>" data-guard-submit>
        <?= csrf_field() ?>

        <fieldset class="fieldset" <?= $canEdit ? '' : 'disabled' ?>>
            <legend class="fieldset__legend">Identity</legend>

            <div class="form-grid form-grid--2">
                <?= component('forms/field', [
                    'name' => 'brand', 'label' => 'Brand', 'required' => true,
                    'value' => $value('brand'), 'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'model', 'label' => 'Model', 'required' => true,
                    'value' => $value('model'), 'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'year', 'label' => 'Year', 'type' => 'number', 'required' => true,
                    'value' => $value('year'), 'errors' => $errors,
                    'attributes' => ['min' => 1950, 'max' => (int) date('Y') + 2],
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'color', 'label' => 'Colour',
                    'value' => $value('color'), 'errors' => $errors,
                ]) ?>
            </div>
        </fieldset>

        <div class="divider"></div>

        <fieldset class="fieldset" <?= $canEdit ? '' : 'disabled' ?>>
            <legend class="fieldset__legend">Specification</legend>

            <div class="form-grid form-grid--2">
                <?= component('forms/field', [
                    'name' => 'transmission', 'label' => 'Transmission', 'type' => 'select',
                    'required' => true, 'value' => $value('transmission', 'automatic'),
                    'options' => $transmissions, 'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'fuel_type', 'label' => 'Fuel type', 'type' => 'select',
                    'required' => true, 'value' => $value('fuel_type', 'petrol'),
                    'options' => $fuelTypes, 'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'seats', 'label' => 'Seats', 'type' => 'number', 'required' => true,
                    'value' => $value('seats', 5), 'errors' => $errors,
                    'attributes' => ['min' => 1, 'max' => 20],
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'doors', 'label' => 'Doors', 'type' => 'number', 'required' => true,
                    'value' => $value('doors', 4), 'errors' => $errors,
                    'attributes' => ['min' => 1, 'max' => 8],
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'mileage', 'label' => 'Odometer (km)', 'type' => 'number',
                    'value' => $value('mileage'), 'errors' => $errors,
                    'attributes' => ['min' => 0],
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'daily_rate', 'label' => 'Daily rate', 'required' => true,
                    'value' => $rateValue, 'errors' => $errors, 'prefix' => 'BHD',
                    'hint' => 'Three decimal places, e.g. 25.500',
                    'attributes' => ['inputmode' => 'decimal', 'placeholder' => '25.000'],
                ]) ?>
            </div>
        </fieldset>

        <div class="divider"></div>

        <fieldset class="fieldset" <?= $canEdit ? '' : 'disabled' ?>>
            <legend class="fieldset__legend">Operations</legend>

            <div class="form-grid form-grid--2">
                <?= component('forms/field', [
                    'name' => 'category_id', 'label' => 'Category', 'type' => 'select',
                    'value' => (string) $value('category_id'), 'options' => $categoryOptions,
                    'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'location_id', 'label' => 'Location', 'type' => 'select',
                    'value' => (string) $value('location_id'), 'options' => $locationOptions,
                    'errors' => $errors,
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true,
                    'value' => $value('status', 'available'), 'options' => $statusOptions,
                    'errors' => $errors,
                    'hint' => 'Maintenance and inactive cars never appear in public search.',
                ]) ?>
                <?= component('forms/field', [
                    'name' => 'plate_number', 'label' => 'Plate number',
                    'value' => $value('plate_number'), 'errors' => $errors,
                    'hint' => 'Never shown publicly.',
                ]) ?>
                <div class="form-grid__full">
                    <?= component('forms/field', [
                        'name' => 'vin', 'label' => 'VIN',
                        'value' => $value('vin'), 'errors' => $errors,
                        'hint' => 'Internal only; never shown publicly.',
                    ]) ?>
                </div>
                <div class="form-grid__full">
                    <?= component('forms/field', [
                        'name' => 'description', 'label' => 'Description', 'type' => 'textarea',
                        'value' => $value('description'), 'errors' => $errors,
                        'placeholder' => 'What should a customer know about this car?',
                    ]) ?>
                </div>
            </div>
        </fieldset>

        <?php if ($canEdit): ?>
            <div class="form-actions">
                <?= component('primitives/button', [
                    'label' => $isEdit ? 'Save changes' : 'Add car',
                ]) ?>
                <?= component('primitives/button', [
                    'label'   => 'Cancel',
                    'href'    => $base . '/cars',
                    'variant' => 'ghost',
                ]) ?>
            </div>
        <?php endif; ?>
    </form>

    <div class="stack">
        <?php if ($isEdit): ?>
            <?php if ($upcomingBookings !== []): ?>
                <?= component('feedback/alert', [
                    'type'    => 'warning',
                    'title'   => count($upcomingBookings) . ' upcoming confirmed booking(s)',
                    'message' => 'Putting this car into maintenance or archiving it will not cancel them. '
                        . 'Resolve those bookings first.',
                ]) ?>
            <?php endif; ?>

            <section class="card">
                <div class="card__header">
                    <h2 class="card__title">Photographs</h2>
                    <span class="text-xs text-muted">
                        <?= count($images) ?> of <?= (int) $maxImages ?>
                    </span>
                </div>

                <div class="card__body">
                    <?php if ($images === []): ?>
                        <?= component('feedback/empty-state', [
                            'title'       => 'No photographs yet',
                            'description' => 'A car needs at least one photograph to look right on the marketplace.',
                            'icon'        => 'image',
                            'flush'       => true,
                        ]) ?>
                    <?php else: ?>
                        <div class="image-manager">
                            <?php foreach ($images as $image): ?>
                                <div class="image-manager__item"
                                     data-primary="<?= (int) $image['is_primary'] ?>">
                                    <div class="image-manager__thumb">
                                        <img src="<?= e('/uploads/' . ltrim((string) $image['file_path'], '/')) ?>"
                                             alt="" loading="lazy">
                                    </div>
                                    <div class="image-manager__bar">
                                        <span class="image-manager__badge">
                                            <?= (int) $image['is_primary'] === 1 ? 'Primary' : 'Photo' ?>
                                        </span>

                                        <?php if ($canManageImages): ?>
                                            <span class="image-manager__actions">
                                                <?php if ((int) $image['is_primary'] !== 1): ?>
                                                    <form method="post"
                                                          action="<?= e($base) ?>/cars/<?= $carId ?>/images/<?= (int) $image['id'] ?>/primary">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="icon-btn icon-btn--sm"
                                                                aria-label="Make this the primary photograph">
                                                            <?= component('primitives/icon', ['name' => 'star', 'size' => 15]) ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="post"
                                                      action="<?= e($base) ?>/cars/<?= $carId ?>/images/<?= (int) $image['id'] ?>/delete"
                                                      data-confirm="This photograph will be permanently removed."
                                                      data-confirm-title="Remove photograph?"
                                                      data-confirm-label="Remove"
                                                      data-confirm-destructive="1">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="icon-btn icon-btn--sm icon-btn--danger"
                                                            aria-label="Remove this photograph">
                                                        <?= component('primitives/icon', ['name' => 'trash', 'size' => 15]) ?>
                                                    </button>
                                                </form>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canManageImages && count($images) < $maxImages): ?>
                        <form method="post" action="<?= e($base) ?>/cars/<?= $carId ?>/images"
                              enctype="multipart/form-data" style="margin-top: var(--space-5);"
                              data-guard-submit>
                            <?= csrf_field() ?>
                            <?= component('forms/file-upload', [
                                'name'     => 'images',
                                'multiple' => true,
                                'title'    => 'Upload photographs',
                                'hint'     => 'JPEG, PNG or WebP · up to ' . (int) $maxImages . ' per car',
                            ]) ?>
                            <div style="margin-top: var(--space-4);">
                                <?= component('primitives/button', [
                                    'label' => 'Upload',
                                    'block' => true,
                                    'icon'  => 'upload',
                                ]) ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($canArchive): ?>
                <div class="action-panel">
                    <p class="action-panel__title">
                        <?= ($car['archived_at'] ?? null) === null ? 'Archive this car' : 'Restore this car' ?>
                    </p>
                    <p class="action-panel__note">
                        Cars are never deleted. Archiving hides the car from the
                        marketplace while keeping every historical booking intact.
                    </p>

                    <?php if (($car['archived_at'] ?? null) === null): ?>
                        <form method="post" action="<?= e($base) ?>/cars/<?= $carId ?>/archive"
                              data-confirm="The car will be hidden from the marketplace. Its booking history is kept."
                              data-confirm-title="Archive this car?"
                              data-confirm-label="Archive">
                            <?= csrf_field() ?>
                            <?= component('primitives/button', [
                                'label'   => 'Archive car',
                                'variant' => 'danger',
                            ]) ?>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e($base) ?>/cars/<?= $carId ?>/restore">
                            <?= csrf_field() ?>
                            <?= component('primitives/button', [
                                'label'   => 'Restore car',
                                'variant' => 'secondary',
                            ]) ?>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card card--padded stack-sm stack">
                <p class="text-strong text-sm">Photographs come next</p>
                <p class="text-xs text-muted">
                    Save this car first, then upload its photographs. A car without a
                    photograph still appears in search, but converts far less well.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
