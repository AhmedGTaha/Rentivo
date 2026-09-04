<?php
/**
 * Booking card.
 *
 * Used by the customer booking list and by management lists that prefer cards
 * over a table on narrow screens.
 *
 * @var array  $booking
 * @var string $href       Detail link
 * @var bool   $showCustomer  Management context shows who booked
 * @var bool   $isOverdue
 */

use Rentivo\Support\Currency;

$booking = $booking ?? [];
$showCustomer = $showCustomer ?? false;
$isOverdue = $isOverdue ?? false;

$reference = (string) ($booking['reference'] ?? '');
$href = $href ?? '/account/bookings/' . rawurlencode($reference);
$name = trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''));
$image = $booking['primary_image'] ?? null;
?>
<article class="<?= e(class_names(['booking-card' => true, 'booking-card--overdue' => $isOverdue])) ?>">
    <div class="booking-card__top">
        <p class="booking-card__reference"><?= e($reference) ?></p>
        <div class="row" style="gap: var(--space-2);">
            <?php if ($isOverdue): ?>
                <span class="overdue-flag">
                    <?= component('primitives/icon', ['name' => 'alert', 'size' => 14]) ?>
                    Overdue
                </span>
            <?php endif; ?>
            <?= component('bookings/booking-status-badge', [
                'status' => (string) ($booking['status'] ?? 'pending'),
                'small'  => true,
            ]) ?>
        </div>
    </div>

    <div class="booking-card__main">
        <div class="booking-card__thumb">
            <?php if ($image !== null && $image !== ''): ?>
                <img src="<?= e('/uploads/' . ltrim((string) $image, '/')) ?>" alt="" loading="lazy">
            <?php else: ?>
                <div class="booking-card__thumb-placeholder" aria-hidden="true">
                    <?= component('primitives/icon', ['name' => 'car', 'size' => 24]) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="booking-card__details">
            <h3 class="booking-card__car">
                <a href="<?= e($href) ?>"><?= e($name) ?></a>
            </h3>

            <p class="booking-card__agency">
                <?php if ($showCustomer): ?>
                    <?= e($booking['customer_name'] ?? 'Customer') ?>
                <?php else: ?>
                    <?= e($booking['organization_name'] ?? '') ?>
                <?php endif; ?>
            </p>

            <p class="booking-card__dates">
                <?= component('primitives/icon', ['name' => 'calendar', 'size' => 14]) ?>
                <span><?= e(datetime_display((string) ($booking['pickup_at'] ?? ''))) ?></span>
                <span class="booking-card__arrow" aria-hidden="true">→</span>
                <span><?= e(datetime_display((string) ($booking['return_at'] ?? ''))) ?></span>
            </p>
        </div>
    </div>

    <div class="booking-card__footer">
        <div>
            <p class="text-xs text-muted">
                <?= (int) ($booking['rental_days'] ?? 0) ?>
                day<?= (int) ($booking['rental_days'] ?? 0) === 1 ? '' : 's' ?>
            </p>
            <p class="text-strong numeric"><?= e(Currency::format((int) ($booking['total_fils'] ?? 0))) ?></p>
        </div>

        <?= component('primitives/button', [
            'label'   => 'View booking',
            'href'    => $href,
            'variant' => 'secondary',
            'size'    => 'sm',
        ]) ?>
    </div>
</article>
