<?php
/**
 * Car filter panel.
 *
 * Rendered twice per browse page — once in the desktop rail, once inside the
 * mobile drawer — from this single definition, so the two can never drift.
 *
 * The panel is a plain GET form: every filter lives in the query string and
 * the server does all the work. filters.js only auto-submits on change.
 *
 * @var \Rentivo\Repositories\CarFilters $filters
 * @var array  $agencies
 * @var array  $brands
 * @var array  $categories
 * @var array  $locationNames
 * @var array  $transmissions
 * @var array  $fuelTypes
 * @var string $basePath
 * @var string $idPrefix   Distinguishes the rail and drawer instances
 */

use Rentivo\Repositories\CarFilters;

/** @var CarFilters $filters */
$filters = $filters ?? CarFilters::fromQuery([]);
$agencies = $agencies ?? [];
$brands = $brands ?? [];
$categories = $categories ?? [];
$locationNames = $locationNames ?? [];
$transmissions = $transmissions ?? [];
$fuelTypes = $fuelTypes ?? [];
$basePath = $basePath ?? '/cars';
$idPrefix = $idPrefix ?? 'rail';

$query = $filters->toQuery();

$select = static function (string $label, string $name, array $options, string $current, string $anyLabel): string {
    $choices = ['' => $anyLabel];

    foreach ($options as $value => $optionLabel) {
        $choices[(string) $value] = (string) $optionLabel;
    }

    return component('forms/field', [
        'name'    => $name,
        'label'   => $label,
        'type'    => 'select',
        'value'   => $current,
        'options' => $choices,
    ]);
};

$agencyOptions = [];
foreach ($agencies as $agency) {
    $agencyOptions[(string) $agency['slug']] = (string) $agency['name'];
}

$brandOptions = [];
foreach ($brands as $brand) {
    $brandOptions[(string) $brand] = (string) $brand;
}

$categoryOptions = [];
foreach ($categories as $category) {
    $categoryOptions[(string) $category] = (string) $category;
}

$locationOptions = [];
foreach ($locationNames as $location) {
    $locationOptions[(string) $location] = (string) $location;
}
?>
<form class="filter-panel" method="get" action="<?= e($basePath) ?>" data-filter-form>
    <?php /* Search and sort are preserved so filtering never discards them. */ ?>
    <input type="hidden" name="q" value="<?= e($filters->search) ?>">
    <input type="hidden" name="sort" value="<?= e($filters->sort) ?>">
    <input type="hidden" name="page" value="1">

    <div class="filter-panel__section">
        <p class="filter-panel__legend" id="<?= e($idPrefix) ?>-dates">Rental period</p>
        <?= component('forms/datetime-field', [
            'startValue' => $query['pickup_at'] ?? '',
            'endValue'   => $query['return_at'] ?? '',
            'startLabel' => 'Pickup',
            'endLabel'   => 'Return',
        ]) ?>
        <p class="field__hint">Only cars free for the whole period are shown.</p>
    </div>

    <?php if ($agencyOptions !== []): ?>
        <div class="filter-panel__section">
            <p class="filter-panel__legend">Agency</p>
            <?= $select('Agency', 'agency', $agencyOptions, $filters->agency, 'All agencies') ?>
        </div>
    <?php endif; ?>

    <div class="filter-panel__section">
        <p class="filter-panel__legend">Daily rate (BHD)</p>
        <?= component('forms/price-range', [
            'minValue' => $query['min_price'] ?? '',
            'maxValue' => $query['max_price'] ?? '',
        ]) ?>
    </div>

    <div class="filter-panel__section">
        <p class="filter-panel__legend">Vehicle</p>
        <div class="filter-panel__options">
            <?= $select('Category', 'category', $categoryOptions, $filters->category, 'All categories') ?>
            <?= $select('Brand', 'brand', $brandOptions, $filters->brand, 'All brands') ?>
            <?= component('forms/field', [
                'name'        => 'model',
                'label'       => 'Model',
                'value'       => $filters->model,
                'placeholder' => 'Any model',
                'attributes'  => ['data-filter-debounce' => true],
            ]) ?>
            <?= component('forms/field', [
                'name'        => 'year',
                'label'       => 'Year',
                'type'        => 'number',
                'value'       => $filters->year ?? '',
                'placeholder' => 'Any year',
                'attributes'  => ['min' => 1950, 'max' => (int) date('Y') + 2],
            ]) ?>
        </div>
    </div>

    <div class="filter-panel__section">
        <p class="filter-panel__legend">Drivetrain</p>
        <div class="filter-panel__options">
            <?= $select('Transmission', 'transmission', $transmissions, $filters->transmission, 'Any transmission') ?>
            <?= $select('Fuel', 'fuel_type', $fuelTypes, $filters->fuelType, 'Any fuel type') ?>
            <?= $select('Minimum seats', 'min_seats', [
                '2' => '2+', '4' => '4+', '5' => '5+', '7' => '7+', '9' => '9+',
            ], (string) ($filters->minSeats ?? ''), 'Any') ?>
        </div>
    </div>

    <?php if ($locationOptions !== []): ?>
        <div class="filter-panel__section">
            <p class="filter-panel__legend">Location</p>
            <?= $select('Location', 'location', $locationOptions, $filters->location, 'All locations') ?>
        </div>
    <?php endif; ?>

    <div class="filter-panel__actions">
        <?= component('primitives/button', [
            'label' => 'Apply filters',
            'block' => true,
        ]) ?>
        <?php if ($filters->activeCount() > 0): ?>
            <?= component('primitives/button', [
                'label'   => 'Clear all',
                'href'    => $basePath,
                'variant' => 'ghost',
                'block'   => true,
            ]) ?>
        <?php endif; ?>
    </div>
</form>
