<?php
/**
 * Car card.
 *
 * Image-first and deliberately restrained: identity, a little context, and the
 * daily rate. Specifications belong on the detail page, not here.
 *
 * The component receives an already-loaded car row and never queries anything.
 *
 * @var array  $car          Public car row (primary_image, organization_*, category_name)
 * @var string $variant      compact|standard|featured
 * @var bool   $showAgency
 * @var bool   $isFavorite
 * @var bool   $canFavorite  Whether to render the favorite control at all
 * @var string|null $href    Overrides the default detail link
 */

use Rentivo\Services\CarService;

$car = $car ?? [];
$variant = $variant ?? 'standard';
$showAgency = $showAgency ?? true;
$isFavorite = $isFavorite ?? false;
$canFavorite = $canFavorite ?? true;

$slug = (string) ($car['slug'] ?? '');
$href = $href ?? '/cars/' . rawurlencode($slug);

$name = trim(($car['brand'] ?? '') . ' ' . ($car['model'] ?? ''));
$image = $car['primary_image'] ?? null;
$status = (string) ($car['status'] ?? 'available');
?>
<article class="<?= e(class_names([
    'car-card' => true,
    'car-card--' . $variant => $variant !== 'standard',
])) ?>">
    <div class="car-card__media">
        <?php if ($image !== null && $image !== ''): ?>
            <img class="car-card__image"
                 src="<?= e('/uploads/' . ltrim((string) $image, '/')) ?>"
                 alt="<?= e($car['year'] . ' ' . $name) ?>"
                 loading="lazy" decoding="async">
        <?php else: ?>
            <div class="car-card__placeholder" role="img"
                 aria-label="No photograph available for <?= e($name) ?>">
                <?= component('primitives/icon', ['name' => 'car', 'size' => 40]) ?>
            </div>
        <?php endif; ?>

        <div class="car-card__badges">
            <?php if (($car['category_name'] ?? null) !== null): ?>
                <?= component('primitives/badge', [
                    'label' => (string) $car['category_name'],
                    'tone'  => 'neutral',
                    'small' => true,
                ]) ?>
            <?php endif; ?>

            <?php if ($status !== 'available' && $status !== 'reserved'): ?>
                <?= component('primitives/badge', [
                    'label' => CarService::statusLabel($status),
                    'tone'  => CarService::statusTone($status),
                    'small' => true,
                ]) ?>
            <?php endif; ?>
        </div>

        <?php if ($canFavorite): ?>
            <div class="car-card__favorite">
                <?= component('cars/favorite-button', [
                    'slug'       => $slug,
                    'isFavorite' => $isFavorite,
                    'carName'    => $name,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="car-card__body">
        <?php if ($showAgency && ($car['organization_name'] ?? null) !== null): ?>
            <p class="car-card__agency">
                <?php if (($car['organization_logo_path'] ?? null) !== null): ?>
                    <img class="car-card__agency-logo"
                         src="<?= e('/uploads/' . ltrim((string) $car['organization_logo_path'], '/')) ?>"
                         alt="" loading="lazy">
                <?php endif; ?>
                <span class="truncate"><?= e($car['organization_name']) ?></span>
            </p>
        <?php endif; ?>

        <h3 class="car-card__title">
            <a href="<?= e($href) ?>"><?= e($name) ?></a>
        </h3>

        <p class="car-card__meta">
            <span class="car-card__meta-item"><?= e($car['year'] ?? '') ?></span>
            <span class="car-card__meta-separator" aria-hidden="true"></span>
            <span class="car-card__meta-item">
                <?= e(CarService::transmissions()[$car['transmission'] ?? 'automatic'] ?? '') ?>
            </span>
            <span class="car-card__meta-separator" aria-hidden="true"></span>
            <span class="car-card__meta-item"><?= (int) ($car['seats'] ?? 0) ?> seats</span>
        </p>

        <div class="car-card__footer">
            <?= component('cars/car-price', ['fils' => (int) ($car['daily_rate_fils'] ?? 0)]) ?>
        </div>
    </div>
</article>
