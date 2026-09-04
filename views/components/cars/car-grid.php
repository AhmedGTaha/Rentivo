<?php
/**
 * Car grid.
 *
 * Renders a collection of car cards, or the shared empty state when there is
 * nothing to show.
 *
 * @var array  $cars
 * @var array  $favoriteIds  Car ids the signed-in user has saved
 * @var string $variant      compact|standard|featured
 * @var bool   $showAgency
 * @var bool   $canFavorite
 * @var string $gridClass
 * @var array|null $emptyState  Props forwarded to feedback/empty-state
 */

$cars = $cars ?? [];
$favoriteIds = $favoriteIds ?? [];
$variant = $variant ?? 'standard';
$showAgency = $showAgency ?? true;
$canFavorite = $canFavorite ?? true;
$gridClass = $gridClass ?? 'car-grid';
$emptyState = $emptyState ?? null;

if ($cars === []) {
    echo component('feedback/empty-state', $emptyState ?? [
        'title'       => 'No cars found',
        'description' => 'Try widening your filters or clearing your search.',
        'icon'        => 'car',
    ]);

    return;
}
?>
<div class="<?= e($gridClass) ?>">
    <?php foreach ($cars as $car): ?>
        <?= component('cars/car-card', [
            'car'         => $car,
            'variant'     => $variant,
            'showAgency'  => $showAgency,
            'canFavorite' => $canFavorite,
            'isFavorite'  => in_array((int) $car['id'], $favoriteIds, true),
        ]) ?>
    <?php endforeach; ?>
</div>
