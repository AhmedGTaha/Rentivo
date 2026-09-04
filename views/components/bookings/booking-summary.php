<?php
/**
 * Booking summary panel.
 *
 * Shows the car, agency, period and locations for a booking or a pending
 * selection at checkout.
 *
 * @var array       $car
 * @var string|null $pickupAt   Display string
 * @var string|null $returnAt
 * @var string|null $pickupLocation
 * @var string|null $returnLocation
 * @var array|null  $quote      PricingService quote or stored booking figures
 */

$car = $car ?? [];
$pickupAt = $pickupAt ?? null;
$returnAt = $returnAt ?? null;
$pickupLocation = $pickupLocation ?? null;
$returnLocation = $returnLocation ?? null;
$quote = $quote ?? null;

$name = trim(($car['brand'] ?? '') . ' ' . ($car['model'] ?? ''));
$image = $car['primary_image'] ?? null;
?>
<div class="booking-summary">
    <div class="booking-summary__car">
        <div class="booking-summary__thumb">
            <?php if ($image !== null && $image !== ''): ?>
                <img src="<?= e('/uploads/' . ltrim((string) $image, '/')) ?>" alt="" loading="lazy">
            <?php else: ?>
                <div class="booking-card__thumb-placeholder" aria-hidden="true">
                    <?= component('primitives/icon', ['name' => 'car', 'size' => 22]) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="grow">
            <p class="text-strong"><?= e($name) ?></p>
            <p class="text-xs text-muted">
                <?= e($car['year'] ?? '') ?> · <?= e($car['organization_name'] ?? '') ?>
            </p>
        </div>
    </div>

    <dl class="detail-list detail-list--rows">
        <div class="detail-list__item">
            <dt class="detail-list__label">Pickup</dt>
            <dd class="detail-list__value"><?= e($pickupAt ?? 'Not selected') ?></dd>
        </div>
        <div class="detail-list__item">
            <dt class="detail-list__label">Return</dt>
            <dd class="detail-list__value"><?= e($returnAt ?? 'Not selected') ?></dd>
        </div>
        <?php if ($pickupLocation !== null): ?>
            <div class="detail-list__item">
                <dt class="detail-list__label">Pickup location</dt>
                <dd class="detail-list__value"><?= e($pickupLocation) ?></dd>
            </div>
        <?php endif; ?>
        <?php if ($returnLocation !== null): ?>
            <div class="detail-list__item">
                <dt class="detail-list__label">Return location</dt>
                <dd class="detail-list__value"><?= e($returnLocation) ?></dd>
            </div>
        <?php endif; ?>
    </dl>

    <?php if ($quote !== null): ?>
        <?= component('bookings/price-breakdown', [
            'dailyRateFils'         => (int) ($quote['daily_rate_fils'] ?? 0),
            'rentalDays'            => (int) ($quote['rental_days'] ?? 0),
            'subtotalFils'          => (int) ($quote['subtotal_fils'] ?? 0),
            'additionalChargesFils' => (int) ($quote['additional_charges_fils'] ?? 0),
            'totalFils'             => (int) ($quote['total_fils'] ?? 0),
        ]) ?>
    <?php else: ?>
        <p class="text-sm text-muted">Choose your pickup and return times to see the total.</p>
    <?php endif; ?>
</div>
