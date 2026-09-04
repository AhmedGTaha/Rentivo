<?php
/**
 * Price range inputs (minimum and maximum daily rate, in BHD).
 *
 * Values are submitted as decimal BHD strings and converted to integer fils
 * server-side by CarFilters; no monetary value is ever handled as a float.
 *
 * @var string $minName
 * @var string $maxName
 * @var string $minValue
 * @var string $maxValue
 */

$minName = $minName ?? 'min_price';
$maxName = $maxName ?? 'max_price';
$minValue = $minValue ?? '';
$maxValue = $maxValue ?? '';
?>
<div class="price-range">
    <div>
        <label class="visually-hidden" for="filter-min-price">Minimum daily rate in BHD</label>
        <input class="input" type="number" inputmode="decimal" min="0" step="0.5"
               id="filter-min-price" name="<?= e($minName) ?>" value="<?= e($minValue) ?>"
               placeholder="Min">
    </div>
    <span class="price-range__separator" aria-hidden="true">–</span>
    <div>
        <label class="visually-hidden" for="filter-max-price">Maximum daily rate in BHD</label>
        <input class="input" type="number" inputmode="decimal" min="0" step="0.5"
               id="filter-max-price" name="<?= e($maxName) ?>" value="<?= e($maxValue) ?>"
               placeholder="Max">
    </div>
</div>
