<?php
/**
 * Saved cars.
 *
 * @var array                       $cars
 * @var int                         $total
 * @var array                       $favoriteIds
 * @var \Rentivo\Support\Pagination $pagination
 */

$cars = $cars ?? [];
$total = (int) ($total ?? 0);
$favoriteIds = $favoriteIds ?? [];
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Account</p>
        <h1 class="page-header__title">Saved cars</h1>
        <p class="page-header__description">
            <span data-favorite-count><?= number_format($total) ?></span>
            <?= $total === 1 ? 'car' : 'cars' ?> saved for later.
        </p>
    </div>
    <div class="page-header__actions">
        <?= component('primitives/button', [
            'label'   => 'Browse more cars',
            'href'    => '/cars',
            'variant' => 'secondary',
        ]) ?>
    </div>
</div>

<?= component('cars/car-grid', [
    'cars'        => $cars,
    'favoriteIds' => $favoriteIds,
    'gridClass'   => 'car-grid car-grid--compact',
    'emptyState'  => [
        'title'       => 'No saved cars yet',
        'description' => 'Tap the heart on any car to keep it here for later.',
        'icon'        => 'heart',
        'actions'     => [['label' => 'Browse cars', 'href' => '/cars']],
    ],
]) ?>

<?= component('navigation/pagination', [
    'pagination' => $pagination,
    'path'       => '/account/favorites',
    'query'      => [],
]) ?>
