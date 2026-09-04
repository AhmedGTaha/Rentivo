<?php
/**
 * Car specifications grid.
 *
 * Deliberately excludes VIN and plate number: neither is ever shown publicly.
 *
 * @var array $car
 */

use Rentivo\Services\CarService;

$car = $car ?? [];

$specs = [
    ['icon' => 'gear-shift', 'label' => 'Transmission',
     'value' => CarService::transmissions()[$car['transmission'] ?? ''] ?? '—'],
    ['icon' => 'fuel', 'label' => 'Fuel',
     'value' => CarService::fuelTypes()[$car['fuel_type'] ?? ''] ?? '—'],
    ['icon' => 'seat', 'label' => 'Seats', 'value' => (string) (int) ($car['seats'] ?? 0)],
    ['icon' => 'door', 'label' => 'Doors', 'value' => (string) (int) ($car['doors'] ?? 0)],
    ['icon' => 'calendar', 'label' => 'Year', 'value' => (string) (int) ($car['year'] ?? 0)],
];

if (($car['color'] ?? null) !== null && $car['color'] !== '') {
    $specs[] = ['icon' => 'palette', 'label' => 'Colour', 'value' => (string) $car['color']];
}

if (($car['category_name'] ?? null) !== null) {
    $specs[] = ['icon' => 'car', 'label' => 'Category', 'value' => (string) $car['category_name']];
}
?>
<div class="car-specs">
    <?php foreach ($specs as $spec): ?>
        <div class="car-spec">
            <span class="car-spec__icon">
                <?= component('primitives/icon', ['name' => $spec['icon'], 'size' => 18]) ?>
            </span>
            <span>
                <span class="car-spec__label"><?= e($spec['label']) ?></span><br>
                <span class="car-spec__value"><?= e($spec['value']) ?></span>
            </span>
        </div>
    <?php endforeach; ?>
</div>
