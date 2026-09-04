<?php
/**
 * Return form.
 *
 * The odometer minimum is enforced here as a hint and authoritatively by
 * RentalService, which rejects a return reading below the checkout reading.
 *
 * @var array  $booking
 * @var array  $rental
 * @var bool   $isOverdue
 * @var array  $errors, $old
 * @var string $orgSlug
 */

use Rentivo\Support\Currency;

$booking = $booking ?? [];
$rental = $rental ?? [];
$isOverdue = $isOverdue ?? false;
$errors = $errors ?? [];
$old = $old ?? [];
$orgSlug = $orgSlug ?? '';

$base = '/manage/' . rawurlencode($orgSlug);
$reference = (string) ($booking['reference'] ?? '');
$checkoutMileage = (int) ($rental['checkout_mileage'] ?? 0);
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Bookings', 'href' => $base . '/bookings'],
    ['label' => $reference, 'href' => $base . '/bookings/' . rawurlencode($reference)],
    ['label' => 'Return'],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Return</p>
        <h1 class="page-header__title">
            Take back the <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
        </h1>
        <p class="page-header__description">
            Booking <?= e($reference) ?>. Completing this closes the booking and
            returns the car to your chosen status.
        </p>
    </div>
</div>

<?php if ($isOverdue): ?>
    <div style="margin-bottom: var(--space-6);">
        <?= component('feedback/alert', [
            'type'    => 'warning',
            'title'   => 'This rental is overdue',
            'message' => 'It was due back on ' . datetime_display((string) $booking['return_at'])
                . '. Add any late charge below.',
        ]) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2" style="align-items: start;">
    <form class="card card--padded" method="post"
          action="<?= e($base) ?>/bookings/<?= e(rawurlencode($reference)) ?>/return"
          enctype="multipart/form-data" data-guard-submit>
        <?= csrf_field() ?>

        <fieldset class="fieldset">
            <legend class="fieldset__legend">Vehicle condition on return</legend>

            <div class="form-grid form-grid--2">
                <?= component('forms/field', [
                    'name'     => 'mileage',
                    'label'    => 'Odometer reading (km)',
                    'type'     => 'number',
                    'required' => true,
                    'value'    => $old['mileage'] ?? '',
                    'errors'   => $errors,
                    'hint'     => 'Must be at least ' . number_format($checkoutMileage)
                        . ' km, the reading at pickup.',
                    'attributes' => [
                        'min'       => $checkoutMileage,
                        'inputmode' => 'numeric',
                        'placeholder' => (string) $checkoutMileage,
                    ],
                ]) ?>

                <?= component('forms/field', [
                    'name'     => 'fuel_percentage',
                    'label'    => 'Fuel level (%)',
                    'type'     => 'number',
                    'required' => true,
                    'value'    => $old['fuel_percentage'] ?? '',
                    'errors'   => $errors,
                    'attributes' => ['min' => 0, 'max' => 100, 'inputmode' => 'numeric'],
                ]) ?>

                <div class="form-grid__full">
                    <?= component('forms/field', [
                        'name'   => 'condition',
                        'label'  => 'Condition notes',
                        'type'   => 'textarea',
                        'value'  => $old['condition'] ?? '',
                        'errors' => $errors,
                    ]) ?>
                </div>

                <div class="form-grid__full">
                    <?= component('forms/field', [
                        'name'        => 'damage_notes',
                        'label'       => 'Damage notes',
                        'type'        => 'textarea',
                        'value'       => $old['damage_notes'] ?? '',
                        'errors'      => $errors,
                        'placeholder' => 'New damage found during the return inspection',
                    ]) ?>
                </div>
            </div>
        </fieldset>

        <div class="divider"></div>

        <fieldset class="fieldset">
            <legend class="fieldset__legend">Charges and vehicle status</legend>

            <div class="form-grid form-grid--2">
                <?= component('forms/field', [
                    'name'   => 'additional_charges',
                    'label'  => 'Additional charges',
                    'value'  => $old['additional_charges'] ?? '',
                    'prefix' => 'BHD',
                    'errors' => $errors,
                    'hint'   => 'Late return, fuel, damage. Added to the booking total.',
                    'attributes' => ['inputmode' => 'decimal', 'placeholder' => '0.000'],
                ]) ?>

                <?= component('forms/field', [
                    'name'     => 'final_car_status',
                    'label'    => 'Car status after return',
                    'type'     => 'select',
                    'required' => true,
                    'value'    => $old['final_car_status'] ?? 'available',
                    'options'  => [
                        'available'   => 'Available — back in the fleet',
                        'maintenance' => 'Maintenance — needs attention first',
                    ],
                    'errors'   => $errors,
                ]) ?>
            </div>
        </fieldset>

        <div class="divider"></div>

        <fieldset class="fieldset">
            <legend class="fieldset__legend">Return photographs</legend>
            <?= component('forms/file-upload', [
                'name'     => 'inspection_images',
                'multiple' => true,
                'title'    => 'Add return photographs',
                'hint'     => 'JPEG, PNG or WebP · optional, stored privately',
            ]) ?>
        </fieldset>

        <div class="form-actions">
            <?= component('primitives/button', ['label' => 'Complete return', 'size' => 'lg']) ?>
            <?= component('primitives/button', [
                'label'   => 'Cancel',
                'href'    => $base . '/bookings/' . rawurlencode($reference),
                'variant' => 'ghost',
            ]) ?>
        </div>
    </form>

    <div class="stack">
        <div class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-4);">At pickup</h2>
            <dl class="detail-list detail-list--rows">
                <div class="detail-list__item">
                    <dt class="detail-list__label">Checked out</dt>
                    <dd class="detail-list__value">
                        <?= e(datetime_display((string) $rental['checkout_at'])) ?>
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Odometer</dt>
                    <dd class="detail-list__value numeric">
                        <?= number_format($checkoutMileage) ?> km
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Fuel</dt>
                    <dd class="detail-list__value">
                        <?= (int) ($rental['checkout_fuel_percentage'] ?? 0) ?>%
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Due back</dt>
                    <dd class="detail-list__value">
                        <?= e(datetime_display((string) $booking['return_at'])) ?>
                    </dd>
                </div>
            </dl>

            <?php if (($rental['checkout_condition'] ?? null) !== null
                && trim((string) $rental['checkout_condition']) !== ''): ?>
                <div style="margin-top: var(--space-5);">
                    <p class="eyebrow" style="margin-bottom: var(--space-2);">Condition at pickup</p>
                    <p class="text-sm text-muted"><?= e($rental['checkout_condition']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-4);">Current total</h2>
            <?= component('bookings/price-breakdown', [
                'dailyRateFils'         => (int) $booking['daily_rate_snapshot_fils'],
                'rentalDays'            => (int) $booking['rental_days'],
                'subtotalFils'          => (int) $booking['subtotal_fils'],
                'additionalChargesFils' => (int) $booking['additional_charges_fils'],
                'totalFils'             => (int) $booking['total_fils'],
                'note'                  => 'Additional charges you enter are added to this total.',
            ]) ?>
        </div>
    </div>
</div>
