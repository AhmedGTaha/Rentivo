<?php
/**
 * Booking detail and action panel.
 *
 * Which actions appear is driven by the same permission checks the controller
 * enforces, and by the state machine's allowed transitions — the UI never
 * offers an action the server would reject.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array $booking, $rental, $inspectionImages, $documents, $customer
 * @var array $bookingSummary, $timeline, $allowedTransitions, $paymentStatuses
 * @var bool  $isOverdue
 * @var string $orgSlug
 */

use Rentivo\Security\Permissions;
use Rentivo\Services\BookingStatus;
use Rentivo\Services\DocumentService;
use Rentivo\Support\Currency;

$booking = $booking ?? [];
$rental = $rental ?? null;
$inspectionImages = $inspectionImages ?? [];
$documents = $documents ?? [];
$customer = $customer ?? null;
$bookingSummary = $bookingSummary ?? [];
$timeline = $timeline ?? [];
$allowedTransitions = $allowedTransitions ?? [];
$paymentStatuses = $paymentStatuses ?? BookingStatus::paymentStatuses();
$isOverdue = $isOverdue ?? false;
$orgSlug = $orgSlug ?? '';

$base = '/manage/' . rawurlencode($orgSlug);
$reference = (string) ($booking['reference'] ?? '');
$bookingBase = $base . '/bookings/' . rawurlencode($reference);
$status = (string) ($booking['status'] ?? 'pending');

$can = static fn (string $to): bool => in_array($to, $allowedTransitions, true);
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Bookings', 'href' => $base . '/bookings'],
    ['label' => $reference],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Booking <?= e($reference) ?></p>
        <h1 class="page-header__title">
            <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
        </h1>
        <div class="row row-wrap" style="margin-top: var(--space-3);">
            <?= component('bookings/booking-status-badge', ['status' => $status]) ?>
            <?= component('primitives/badge', [
                'label' => 'Payment: ' . BookingStatus::paymentLabel((string) $booking['payment_status']),
                'tone'  => BookingStatus::paymentTone((string) $booking['payment_status']),
            ]) ?>
            <?php if ($isOverdue): ?>
                <span class="overdue-flag">
                    <?= component('primitives/icon', ['name' => 'alert', 'size' => 14]) ?>
                    Overdue since <?= e(datetime_display((string) $booking['return_at'])) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="booking-detail">
    <div class="stack-lg stack">
        <section class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-5);">Rental</h2>

            <dl class="detail-list">
                <div class="detail-list__item">
                    <dt class="detail-list__label">Pickup</dt>
                    <dd class="detail-list__value"><?= e(datetime_display((string) $booking['pickup_at'])) ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Return</dt>
                    <dd class="detail-list__value"><?= e(datetime_display((string) $booking['return_at'])) ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Duration</dt>
                    <dd class="detail-list__value">
                        <?= (int) $booking['rental_days'] ?> day<?= (int) $booking['rental_days'] === 1 ? '' : 's' ?>
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Pickup location</dt>
                    <dd class="detail-list__value"><?= e($booking['pickup_location_name'] ?? '—') ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Return location</dt>
                    <dd class="detail-list__value"><?= e($booking['return_location_name'] ?? '—') ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Plate</dt>
                    <dd class="detail-list__value"><?= e($booking['plate_number'] ?? '—') ?></dd>
                </div>
            </dl>

            <?php if (($booking['customer_notes'] ?? null) !== null
                && trim((string) $booking['customer_notes']) !== ''): ?>
                <div style="margin-top: var(--space-6);">
                    <p class="eyebrow" style="margin-bottom: var(--space-2);">Customer note</p>
                    <p class="text-sm text-muted"><?= e($booking['customer_notes']) ?></p>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($rental !== null): ?>
            <section class="card card--padded">
                <h2 class="card__title" style="margin-bottom: var(--space-5);">Rental record</h2>

                <dl class="detail-list">
                    <div class="detail-list__item">
                        <dt class="detail-list__label">Checked out</dt>
                        <dd class="detail-list__value"><?= e(datetime_display((string) $rental['checkout_at'])) ?></dd>
                    </div>
                    <div class="detail-list__item">
                        <dt class="detail-list__label">Checkout odometer</dt>
                        <dd class="detail-list__value numeric">
                            <?= number_format((int) $rental['checkout_mileage']) ?> km
                        </dd>
                    </div>
                    <div class="detail-list__item">
                        <dt class="detail-list__label">Checkout fuel</dt>
                        <dd class="detail-list__value"><?= (int) $rental['checkout_fuel_percentage'] ?>%</dd>
                    </div>
                    <div class="detail-list__item">
                        <dt class="detail-list__label">Checked out by</dt>
                        <dd class="detail-list__value"><?= e($rental['checkout_employee_name'] ?? '—') ?></dd>
                    </div>

                    <?php if (($rental['actual_return_at'] ?? null) !== null): ?>
                        <div class="detail-list__item">
                            <dt class="detail-list__label">Returned</dt>
                            <dd class="detail-list__value">
                                <?= e(datetime_display((string) $rental['actual_return_at'])) ?>
                            </dd>
                        </div>
                        <div class="detail-list__item">
                            <dt class="detail-list__label">Return odometer</dt>
                            <dd class="detail-list__value numeric">
                                <?= number_format((int) $rental['return_mileage']) ?> km
                                <span class="text-xs text-muted">
                                    (<?= number_format((int) $rental['return_mileage'] - (int) $rental['checkout_mileage']) ?> km driven)
                                </span>
                            </dd>
                        </div>
                        <div class="detail-list__item">
                            <dt class="detail-list__label">Return fuel</dt>
                            <dd class="detail-list__value"><?= (int) $rental['return_fuel_percentage'] ?>%</dd>
                        </div>
                        <div class="detail-list__item">
                            <dt class="detail-list__label">Returned to</dt>
                            <dd class="detail-list__value"><?= e($rental['return_employee_name'] ?? '—') ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php foreach (['checkout_condition' => 'Checkout condition',
                                'return_condition'   => 'Return condition',
                                'damage_notes'       => 'Damage notes'] as $field => $label): ?>
                    <?php if (($rental[$field] ?? null) !== null && trim((string) $rental[$field]) !== ''): ?>
                        <div style="margin-top: var(--space-5);">
                            <p class="eyebrow" style="margin-bottom: var(--space-2);"><?= e($label) ?></p>
                            <p class="text-sm text-muted"><?= e($rental[$field]) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($inspectionImages !== []): ?>
                    <div style="margin-top: var(--space-6);">
                        <p class="eyebrow" style="margin-bottom: var(--space-3);">
                            Inspection photographs (private)
                        </p>
                        <div class="inspection-grid">
                            <?php foreach ($inspectionImages as $image): ?>
                                <a class="inspection-grid__item"
                                   href="<?= e($base) ?>/inspections/<?= (int) $image['id'] ?>/file"
                                   target="_blank" rel="noopener"
                                   aria-label="<?= e(ucfirst((string) $image['phase'])) ?> inspection photograph">
                                    <img src="<?= e($base) ?>/inspections/<?= (int) $image['id'] ?>/file"
                                         alt="" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-5);">Timeline</h2>
            <?= component('bookings/booking-timeline', [
                'booking' => $booking,
                'entries' => $timeline,
            ]) ?>
        </section>
    </div>

    <div class="booking-detail__aside">
        <?php /* Actions */ ?>
        <div class="action-panel">
            <p class="action-panel__title">Actions</p>

            <?php
            $hasAction = false;
            ?>

            <?php if ($can(BookingStatus::CONFIRMED) && $context->can(Permissions::BOOKINGS_CONFIRM)): ?>
                <?php $hasAction = true; ?>
                <form method="post" action="<?= e($bookingBase) ?>/confirm" data-guard-submit>
                    <?= csrf_field() ?>
                    <?= component('primitives/button', [
                        'label' => 'Confirm booking',
                        'block' => true,
                        'icon'  => 'check',
                    ]) ?>
                </form>
                <p class="action-panel__note">
                    Confirming re-checks availability against other confirmed bookings
                    and fails safely if the car is already taken.
                </p>
            <?php endif; ?>

            <?php if ($can(BookingStatus::READY_FOR_PICKUP) && $context->can(Permissions::BOOKINGS_CHECKOUT)): ?>
                <?php $hasAction = true; ?>
                <form method="post" action="<?= e($bookingBase) ?>/ready">
                    <?= csrf_field() ?>
                    <?= component('primitives/button', [
                        'label'   => 'Mark ready for pickup',
                        'variant' => 'secondary',
                        'block'   => true,
                    ]) ?>
                </form>
            <?php endif; ?>

            <?php if ($status === BookingStatus::READY_FOR_PICKUP && $context->can(Permissions::BOOKINGS_CHECKOUT)): ?>
                <?php $hasAction = true; ?>
                <?= component('primitives/button', [
                    'label' => 'Process pickup',
                    'href'  => $bookingBase . '/checkout',
                    'block' => true,
                    'icon'  => 'key',
                ]) ?>
            <?php endif; ?>

            <?php if ($status === BookingStatus::ACTIVE && $context->can(Permissions::BOOKINGS_COMPLETE_RETURN)): ?>
                <?php $hasAction = true; ?>
                <?= component('primitives/button', [
                    'label' => 'Process return',
                    'href'  => $bookingBase . '/return',
                    'block' => true,
                    'icon'  => 'check-circle',
                ]) ?>
            <?php endif; ?>

            <?php if ($can(BookingStatus::REJECTED) && $context->can(Permissions::BOOKINGS_REJECT)): ?>
                <?php $hasAction = true; ?>
                <details>
                    <summary class="btn btn--secondary btn--block" style="margin-top: var(--space-2);">
                        Reject request
                    </summary>
                    <form method="post" action="<?= e($bookingBase) ?>/reject"
                          style="margin-top: var(--space-3);">
                        <?= csrf_field() ?>
                        <label class="visually-hidden" for="reject-reason">Reason for rejecting</label>
                        <textarea class="textarea" id="reject-reason" name="reason" rows="2"
                                  placeholder="Reason shown to the customer (optional)"></textarea>
                        <div style="margin-top: var(--space-3);">
                            <?= component('primitives/button', [
                                'label'   => 'Reject booking',
                                'variant' => 'danger',
                                'block'   => true,
                            ]) ?>
                        </div>
                    </form>
                </details>
            <?php endif; ?>

            <?php if ($can(BookingStatus::CANCELLED) && $context->can(Permissions::BOOKINGS_CANCEL)): ?>
                <?php $hasAction = true; ?>
                <details>
                    <summary class="btn btn--secondary btn--block" style="margin-top: var(--space-2);">
                        Cancel booking
                    </summary>
                    <form method="post" action="<?= e($bookingBase) ?>/cancel"
                          style="margin-top: var(--space-3);">
                        <?= csrf_field() ?>
                        <label class="visually-hidden" for="cancel-reason">Reason for cancelling</label>
                        <textarea class="textarea" id="cancel-reason" name="reason" rows="2"
                                  placeholder="Reason shown to the customer (optional)"></textarea>
                        <div style="margin-top: var(--space-3);">
                            <?= component('primitives/button', [
                                'label'   => 'Cancel booking',
                                'variant' => 'danger',
                                'block'   => true,
                            ]) ?>
                        </div>
                    </form>
                </details>
            <?php endif; ?>

            <?php if ($can(BookingStatus::NO_SHOW) && $context->can(Permissions::BOOKINGS_CANCEL)): ?>
                <?php $hasAction = true; ?>
                <form method="post" action="<?= e($bookingBase) ?>/no-show"
                      data-confirm="Mark this booking as a no-show?"
                      data-confirm-title="Customer did not arrive?"
                      data-confirm-label="Mark no-show">
                    <?= csrf_field() ?>
                    <?= component('primitives/button', [
                        'label'   => 'Mark as no-show',
                        'variant' => 'ghost',
                        'block'   => true,
                    ]) ?>
                </form>
            <?php endif; ?>

            <?php if (!$hasAction): ?>
                <p class="action-panel__note">
                    No further action is available for this booking
                    <?= BookingStatus::isTerminal($status) ? ' — it has reached a final state.' : '.' ?>
                </p>
            <?php endif; ?>
        </div>

        <?php /* Pricing */ ?>
        <div class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-4);">Pricing</h2>
            <?= component('bookings/price-breakdown', [
                'dailyRateFils'         => (int) $booking['daily_rate_snapshot_fils'],
                'rentalDays'            => (int) $booking['rental_days'],
                'subtotalFils'          => (int) $booking['subtotal_fils'],
                'additionalChargesFils' => (int) $booking['additional_charges_fils'],
                'totalFils'             => (int) $booking['total_fils'],
                'note'                  => 'Rate snapshotted when the booking was made.',
            ]) ?>

            <?php if ($context->can(Permissions::BOOKINGS_MANAGE_PAYMENT)): ?>
                <form method="post" action="<?= e($bookingBase) ?>/payment"
                      style="margin-top: var(--space-5);" data-filter-form>
                    <?= csrf_field() ?>
                    <?= component('forms/field', [
                        'name'    => 'payment_status',
                        'label'   => 'Payment status',
                        'type'    => 'select',
                        'value'   => (string) $booking['payment_status'],
                        'options' => array_combine(
                            $paymentStatuses,
                            array_map([BookingStatus::class, 'paymentLabel'], $paymentStatuses)
                        ),
                    ]) ?>
                    <div style="margin-top: var(--space-3);">
                        <?= component('primitives/button', [
                            'label'   => 'Update payment',
                            'variant' => 'secondary',
                            'size'    => 'sm',
                            'block'   => true,
                        ]) ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php /* Customer */ ?>
        <div class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-4);">Customer</h2>

            <div class="row" style="gap: var(--space-3);">
                <?= component('primitives/avatar', [
                    'imageUrl' => $booking['customer_avatar'] ?? null,
                    'name'     => (string) ($booking['customer_name'] ?? ''),
                ]) ?>
                <div class="grow" style="min-width: 0;">
                    <p class="text-strong"><?= e($booking['customer_name'] ?? '') ?></p>
                    <p class="text-xs text-muted" style="overflow-wrap: anywhere;">
                        <?= e($booking['customer_email'] ?? '') ?>
                    </p>
                </div>
            </div>

            <dl class="detail-list detail-list--rows" style="margin-top: var(--space-5);">
                <div class="detail-list__item">
                    <dt class="detail-list__label">Phone</dt>
                    <dd class="detail-list__value"><?= e($booking['customer_phone'] ?? '—') ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Bookings with you</dt>
                    <dd class="detail-list__value"><?= (int) ($bookingSummary['total'] ?? 0) ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Completed</dt>
                    <dd class="detail-list__value"><?= (int) ($bookingSummary['completed'] ?? 0) ?></dd>
                </div>
            </dl>

            <?php if ($customer !== null && $context->can(Permissions::CUSTOMERS_VIEW)): ?>
                <div style="margin-top: var(--space-5);">
                    <?= component('primitives/button', [
                        'label'   => 'Open customer record',
                        'href'    => $base . '/customers/' . (int) $customer['id'],
                        'variant' => 'secondary',
                        'size'    => 'sm',
                        'block'   => true,
                    ]) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php /* Documents */ ?>
        <?php if ($context->can(Permissions::DOCUMENTS_VIEW)): ?>
            <div class="card card--padded">
                <h2 class="card__title" style="margin-bottom: var(--space-4);">Documents</h2>

                <?php if ($documents === []): ?>
                    <p class="text-sm text-muted">
                        This customer has not uploaded any documents.
                    </p>
                <?php else: ?>
                    <div class="stack-sm stack">
                        <?php foreach ($documents as $document): ?>
                            <div class="row row-between" style="gap: var(--space-3);">
                                <div style="min-width: 0;">
                                    <p class="text-sm text-strong">
                                        <?= e(DocumentService::typeLabel((string) $document['document_type'])) ?>
                                    </p>
                                    <?= component('primitives/badge', [
                                        'label' => ucfirst((string) ($document['review_status'] ?? 'pending')),
                                        'tone'  => DocumentService::statusTone(
                                            (string) ($document['review_status'] ?? 'pending')
                                        ),
                                        'small' => true,
                                    ]) ?>
                                </div>
                                <a class="btn btn--secondary btn--sm"
                                   href="/documents/<?= (int) $document['id'] ?>/file"
                                   target="_blank" rel="noopener">View</a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: var(--space-4);">
                        <?= component('primitives/button', [
                            'label'   => 'Review documents',
                            'href'    => $base . '/documents',
                            'variant' => 'ghost',
                            'size'    => 'sm',
                        ]) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php /* Internal notes */ ?>
        <?php if ($context->can(Permissions::BOOKINGS_VIEW)): ?>
            <form class="card card--padded" method="post" action="<?= e($bookingBase) ?>/notes">
                <?= csrf_field() ?>
                <h2 class="card__title" style="margin-bottom: var(--space-2);">Internal notes</h2>
                <p class="text-xs text-muted" style="margin-bottom: var(--space-4);">
                    Never shown to the customer.
                </p>

                <label class="visually-hidden" for="admin-notes">Internal notes</label>
                <textarea class="textarea" id="admin-notes" name="admin_notes" rows="4"
                          placeholder="Notes for your team"><?= e($booking['admin_notes'] ?? '') ?></textarea>

                <div style="margin-top: var(--space-3);">
                    <?= component('primitives/button', [
                        'label'   => 'Save notes',
                        'variant' => 'secondary',
                        'size'    => 'sm',
                    ]) ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
