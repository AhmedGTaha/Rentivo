<?php
/**
 * Car price.
 *
 * Always formatted from integer fils through the Currency helper, so BHD's
 * three decimal places are consistent everywhere.
 *
 * @var int    $fils
 * @var string $period
 * @var bool   $large
 */

use Rentivo\Support\Currency;

$fils = (int) ($fils ?? 0);
$period = $period ?? '/ day';
$large = $large ?? false;
?>
<p class="<?= e(class_names(['car-price' => true, 'car-price--lg' => $large])) ?>">
    <span class="car-price__currency"><?= e(Currency::CODE) ?></span>
    <span class="car-price__amount"><?= e(Currency::amount($fils)) ?></span>
    <?php if ($period !== ''): ?>
        <span class="car-price__period"><?= e($period) ?></span>
    <?php endif; ?>
</p>
