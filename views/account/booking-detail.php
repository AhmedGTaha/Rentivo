<?php
/**
 * Customer booking detail.
 *
 * Shows only what belongs to the customer: their booking, the agency's public
 * contact details and terms. Internal agency notes are never rendered here.
 *
 * @var array $booking
 */

use Rentivo\Services\BookingStatus;

$booking = $booking ?? [];

$reference = (string) ($booking['reference'] ?? '');
$status = (string) ($booking['status'] ?? 'pending');
$canCancel = BookingStatus::customerMayCancel($status);
$name = trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''));
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Bookings', 'href' => '/account/bookings'],
    ['label' => $reference],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Booking <?= e($reference) ?></p>
        <h1 class="page-header__title"><?= e($name) ?></h1>
        <div class="row row-wrap" style="margin-top: var(--space-3);">
            <?= component('bookings/booking-status-badge', ['status' => $status]) ?>
            <?= component('primitives/badge', [
                'label' => 'Payment: ' . BookingStatus::paymentLabel((string) ($booking['payment_status'] ?? 'unpaid')),
                'tone'  => BookingStatus::paymentTone((string) ($booking['payment_status'] ?? 'unpaid')),
            ]) ?>
        </div>
    </div>
    <div class="page-header__actions">
        <?= component('primitives/button', [
            'label'   => 'View car',
            'href'    => '/cars/' . rawurlencode((string) ($booking['car_slug'] ?? '')),
            'variant' => 'secondary',
        ]) ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="stack-lg stack">
        <section class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-5);">Rental details</h2>

            <dl class="detail-list detail-list--rows">
                <div class="detail-list__item">
                    <dt class="detail-list__label">Agency</dt>
                    <dd class="detail-list__value">
                        <a href="/agency/<?= e(rawurlencode((string) ($booking['organization_slug'] ?? ''))) ?>">
                            <?= e($booking['organization_name'] ?? '') ?>
                        </a>
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Pickup</dt>
                    <dd class="detail-list__value"><?= e(datetime_display((string) $booking['pickup_at'])) ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Return</dt>
                    <dd class="detail-list__value"><?= e(datetime_display((string) $booking['return_at'])) ?></dd>
                </div>
                <?php if (($booking['pickup_location_name'] ?? null) !== null): ?>
                    <div class="detail-list__item">
                        <dt class="detail-list__label">Pickup location</dt>
                        <dd class="detail-list__value">
                            <?= e($booking['pickup_location_name']) ?>
                            <?php if (($booking['pickup_location_address'] ?? null) !== null): ?>
                                <br><span class="text-xs text-muted"><?= e($booking['pickup_location_address']) ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
                <?php if (($booking['return_location_name'] ?? null) !== null): ?>
                    <div class="detail-list__item">
                        <dt class="detail-list__label">Return location</dt>
                        <dd class="detail-list__value"><?= e($booking['return_location_name']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Payment method</dt>
                    <dd class="detail-list__value">Pay at pickup</dd>
                </div>
            </dl>
        </section>

        <section class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-5);">Progress</h2>
            <?= component('bookings/booking-timeline', ['booking' => $booking]) ?>
        </section>

        <?php if (($booking['organization_rental_terms'] ?? null) !== null
            && trim((string) $booking['organization_rental_terms']) !== ''): ?>
            <section class="card card--padded">
                <h2 class="card__title" style="margin-bottom: var(--space-4);">Rental terms</h2>
                <div class="car-terms"><?= e($booking['organization_rental_terms']) ?></div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?= component('bookings/booking-summary', [
            'car' => [
                'brand'             => $booking['brand'] ?? '',
                'model'             => $booking['model'] ?? '',
                'year'              => $booking['year'] ?? '',
                'organization_name' => $booking['organization_name'] ?? '',
                'primary_image'     => $booking['primary_image'] ?? null,
            ],
            'pickupAt'       => datetime_display((string) $booking['pickup_at']),
            'returnAt'       => datetime_display((string) $booking['return_at']),
            'pickupLocation' => $booking['pickup_location_name'] ?? null,
            'returnLocation' => $booking['return_location_name'] ?? null,
            'quote'          => [
                'daily_rate_fils'         => (int) $booking['daily_rate_snapshot_fils'],
                'rental_days'             => (int) $booking['rental_days'],
                'subtotal_fils'           => (int) $booking['subtotal_fils'],
                'additional_charges_fils' => (int) $booking['additional_charges_fils'],
                'total_fils'              => (int) $booking['total_fils'],
            ],
        ]) ?>

        <div class="card card--padded stack">
            <p class="text-strong">Need help?</p>
            <p class="text-sm text-muted">
                Contact <?= e($booking['organization_name'] ?? 'the agency') ?> directly
                for changes to this booking.
            </p>
            <div class="stack-sm stack">
                <?php if (($booking['organization_phone'] ?? null) !== null): ?>
                    <a class="row text-sm" style="gap: var(--space-2);"
                       href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $booking['organization_phone'])) ?>">
                        <?= component('primitives/icon', ['name' => 'phone', 'size' => 15]) ?>
                        <?= e($booking['organization_phone']) ?>
                    </a>
                <?php endif; ?>
                <?php if (($booking['organization_contact_email'] ?? null) !== null): ?>
                    <a class="row text-sm" style="gap: var(--space-2);"
                       href="mailto:<?= e($booking['organization_contact_email']) ?>">
                        <?= component('primitives/icon', ['name' => 'mail', 'size' => 15]) ?>
                        <?= e($booking['organization_contact_email']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canCancel): ?>
            <div class="action-panel">
                <p class="action-panel__title">Cancel this booking</p>
                <p class="action-panel__note">
                    You can cancel while a booking is still pending or confirmed.
                    Once it is ready for pickup, please contact the agency.
                </p>
                <form method="post"
                      action="/account/bookings/<?= e(rawurlencode($reference)) ?>/cancel"
                      data-confirm="This will cancel booking <?= e($reference) ?>. This cannot be undone."
                      data-confirm-title="Cancel this booking?"
                      data-confirm-label="Cancel booking"
                      data-confirm-destructive="1">
                    <?= csrf_field() ?>
                    <div class="stack-sm stack">
                        <label class="visually-hidden" for="cancel-reason">Reason for cancelling</label>
                        <textarea class="textarea" id="cancel-reason" name="cancellation_reason"
                                  rows="2" placeholder="Reason (optional)"></textarea>
                        <?= component('primitives/button', [
                            'label'   => 'Cancel booking',
                            'variant' => 'danger',
                            'block'   => true,
                        ]) ?>
                    </div>
                </form>
            </div>
        <?php elseif (($booking['cancellation_reason'] ?? null) !== null
            && trim((string) $booking['cancellation_reason']) !== ''): ?>
            <?= component('feedback/alert', [
                'type'    => 'info',
                'title'   => 'Cancellation reason',
                'message' => (string) $booking['cancellation_reason'],
            ]) ?>
        <?php elseif (($booking['rejection_reason'] ?? null) !== null
            && trim((string) $booking['rejection_reason']) !== ''): ?>
            <?= component('feedback/alert', [
                'type'    => 'warning',
                'title'   => 'Why this was not accepted',
                'message' => (string) $booking['rejection_reason'],
            ]) ?>
        <?php endif; ?>
    </div>
</div>
