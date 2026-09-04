<?php
/**
 * Price breakdown.
 *
 * Accepts either a live quote (checkout) or a stored booking (detail pages).
 * In both cases the figures are integer fils formatted through Currency; the
 * daily rate shown for a stored booking is its snapshot, never the car's
 * current rate.
 *
 * @var int    $dailyRateFils
 * @var int    $rentalDays
 * @var int    $subtotalFils
 * @var int    $additionalChargesFils
 * @var int    $totalFils
 * @var string|null $note
 */

use Rentivo\Support\Currency;

$dailyRateFils = (int) ($dailyRateFils ?? 0);
$rentalDays = (int) ($rentalDays ?? 0);
$subtotalFils = (int) ($subtotalFils ?? 0);
$additionalChargesFils = (int) ($additionalChargesFils ?? 0);
$totalFils = (int) ($totalFils ?? 0);
$note = $note ?? null;
?>
<div class="price-breakdown">
    <div class="price-breakdown__row">
        <span class="price-breakdown__label">
            <?= e(Currency::format($dailyRateFils)) ?> ×
            <?= (int) $rentalDays ?> day<?= $rentalDays === 1 ? '' : 's' ?>
        </span>
        <span class="price-breakdown__value"><?= e(Currency::format($subtotalFils)) ?></span>
    </div>

    <?php if ($additionalChargesFils > 0): ?>
        <div class="price-breakdown__row">
            <span class="price-breakdown__label">Additional charges</span>
            <span class="price-breakdown__value"><?= e(Currency::format($additionalChargesFils)) ?></span>
        </div>
    <?php endif; ?>

    <div class="price-breakdown__total">
        <span class="price-breakdown__total-label">Total</span>
        <span class="price-breakdown__total-value"><?= e(Currency::format($totalFils)) ?></span>
    </div>

    <p class="price-breakdown__note">
        <?= e($note ?? 'Payable at pickup. No online payment is taken.') ?>
    </p>
</div>
