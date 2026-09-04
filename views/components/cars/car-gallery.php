<?php
/**
 * Car gallery.
 *
 * Every image is rendered server-side with only the first visible, so the
 * gallery shows a photograph even without JavaScript; gallery.js adds
 * navigation on top.
 *
 * @var array  $images   car_images rows
 * @var array  $car
 * @var bool   $isFavorite
 * @var bool   $canFavorite
 */

$images = $images ?? [];
$car = $car ?? [];
$isFavorite = $isFavorite ?? false;
$canFavorite = $canFavorite ?? true;

$name = trim(($car['brand'] ?? '') . ' ' . ($car['model'] ?? ''));
$total = count($images);
?>
<div class="gallery" data-gallery tabindex="0" role="region" aria-label="<?= e($name) ?> photographs">
    <div class="gallery__stage">
        <?php if ($total === 0): ?>
            <div class="gallery__empty" role="img" aria-label="No photographs available">
                <?= component('primitives/icon', ['name' => 'car', 'size' => 56]) ?>
                <span class="text-sm">No photographs yet</span>
            </div>
        <?php else: ?>
            <?php foreach ($images as $index => $image): ?>
                <img class="gallery__image" data-gallery-image
                     src="<?= e('/uploads/' . ltrim((string) $image['file_path'], '/')) ?>"
                     alt="<?= e($name . ' — photograph ' . ($index + 1) . ' of ' . $total) ?>"
                     <?= $index === 0 ? '' : 'hidden' ?>
                     <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            <?php endforeach; ?>

            <?php if ($total > 1): ?>
                <button type="button" class="gallery__nav gallery__nav--prev" data-gallery-prev
                        aria-label="Previous photograph">
                    <?= component('primitives/icon', ['name' => 'chevron-left', 'size' => 18]) ?>
                </button>
                <button type="button" class="gallery__nav gallery__nav--next" data-gallery-next
                        aria-label="Next photograph">
                    <?= component('primitives/icon', ['name' => 'chevron-right', 'size' => 18]) ?>
                </button>
                <p class="gallery__counter" data-gallery-counter aria-live="polite">1 / <?= (int) $total ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($canFavorite): ?>
            <div class="gallery__favorite">
                <?= component('cars/favorite-button', [
                    'slug'       => (string) ($car['slug'] ?? ''),
                    'isFavorite' => $isFavorite,
                    'carName'    => $name,
                    'large'      => true,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total > 1): ?>
        <div class="gallery__thumbs">
            <?php foreach ($images as $index => $image): ?>
                <button type="button" class="gallery__thumb" data-gallery-thumb
                        aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                        aria-label="Show photograph <?= (int) ($index + 1) ?>">
                    <img src="<?= e('/uploads/' . ltrim((string) $image['file_path'], '/')) ?>"
                         alt="" loading="lazy">
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
