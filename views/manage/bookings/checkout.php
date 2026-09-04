<?php
/**
 * Pickup (checkout) form.
 *
 * Capturing the odometer and fuel level here is what makes the return
 * validation meaningful later.
 *
 * @var array      $booking
 * @var array|null $car
 * @var array      $errors, $old
 * @var string     $orgSlug
 */

$booking = $booking ?? [];
$car = $car ?? null;
$errors = $errors ?? [];
$old = $old ?? [];
$orgSlug = $orgSlug ?? '';

$base = '/manage/' . rawurlencode($orgSlug);
$reference = (string) ($booking['reference'] ?? '');
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Bookings', 'href' => $base . '/bookings'],
    ['label' => $reference, 'href' => $base . '/bookings/' . rawurlencode($reference)],
    ['label' => 'Pickup'],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Pickup</p>
        <h1 class="page-header__title">
            Hand over the <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
        </h1>
        <p class="page-header__description">
            Booking <?= e($reference) ?> for <?= e($booking['customer_name'] ?? 'the customer') ?>.
            Completing this makes the rental active and marks the car as rented.
        </p>
    </div>
</div>

<div class="grid grid-2" style="align-items: start;">
    <form class="card card--padded" method="post"
          action="<?= e($base) ?>/bookings/<?= e(rawurlencode($reference)) ?>/checkout"
          enctype="multipart/form-data" data-guard-submit>
        <?= csrf_field() ?>

        <fieldset class="fieldset">
            <legend class="fieldset__legend">Vehicle condition at handover</legend>

            <div class="form-grid form-grid--2">
                <?= component('forms/field', [
                    'name'     => 'mileage',
                    'label'    => 'Odometer reading (km)',
                    'type'     => 'number',
                    'required' => true,
                    'value'    => $old['mileage'] ?? ($car['mileage'] ?? ''),
                    'errors'   => $errors,
                    'attributes' => ['min' => 0, 'inputmode' => 'numeric'],
                    'hint'     => 'The return reading must be at least this value.',
                ]) ?>

                <?= component('forms/field', [
                    'name'     => 'fuel_percentage',
                    'label'    => 'Fuel level (%)',
                    'type'     => 'number',
                    'required' => true,
                    'value'    => $old['fuel_percentage'] ?? '100',
                    'errors'   => $errors,
                    'attributes' => ['min' => 0, 'max' => 100, 'inputmode' => 'numeric'],
                ]) ?>

                <div class="form-grid__full">
                    <?= component('forms/field', [
                        'name'        => 'condition',
                        'label'       => 'Condition notes',
                        'type'        => 'textarea',
                        'value'       => $old['condition'] ?? '',
                        'errors'      => $errors,
                        'placeholder' => 'Existing scratches, tyre condition, accessories handed over…',
                    ]) ?>
                </div>
            </div>
        </fieldset>

        <div class="divider"></div>

        <fieldset class="fieldset">
            <legend class="fieldset__legend">Inspection photographs</legend>
            <p class="field__hint" style="margin-bottom: var(--space-4);">
                Stored privately against this rental. They are never shown on the
                public car page.
            </p>

            <?= component('forms/file-upload', [
                'name'     => 'inspection_images',
                'multiple' => true,
                'title'    => 'Add condition photographs',
                'hint'     => 'JPEG, PNG or WebP · optional',
            ]) ?>
        </fieldset>

        <div class="form-actions">
            <?= component('primitives/button', ['label' => 'Complete pickup', 'size' => 'lg']) ?>
            <?= component('primitives/button', [
                'label'   => 'Cancel',
                'href'    => $base . '/bookings/' . rawurlencode($reference),
                'variant' => 'ghost',
            ]) ?>
        </div>
    </form>

    <div class="stack">
        <?= component('bookings/booking-summary', [
            'car' => [
                'brand'             => $booking['brand'] ?? '',
                'model'             => $booking['model'] ?? '',
                'year'              => $booking['year'] ?? '',
                'organization_name' => $booking['plate_number'] ?? '',
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

        <div class="card card--padded stack-sm stack">
            <p class="text-strong text-sm">Before you hand over the keys</p>
            <p class="text-xs text-muted">
                Check the customer's driving licence, confirm the return time, and
                photograph any existing damage.
            </p>
        </div>
    </div>
</div>
