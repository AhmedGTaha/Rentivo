<?php
/**
 * Booking checkout.
 *
 * A guest never reaches this template: the controller stores their selection
 * and sends them through Google first, then returns them straight back here
 * with everything preserved.
 *
 * @var array      $car
 * @var array      $locations
 * @var array      $selection
 * @var array|null $quote
 * @var array|null $availability
 * @var array      $profile
 * @var bool       $hasPhone
 * @var array      $errors
 * @var array      $old
 */

$car = $car ?? [];
$locations = $locations ?? [];
$selection = $selection ?? [];
$quote = $quote ?? null;
$availability = $availability ?? null;
$profile = $profile ?? [];
$hasPhone = $hasPhone ?? false;
$errors = $errors ?? [];
$old = $old ?? [];

$slug = (string) ($car['slug'] ?? '');
$name = trim(($car['brand'] ?? '') . ' ' . ($car['model'] ?? ''));

$locationOptions = ['' => 'Not specified'];
foreach ($locations as $location) {
    $locationOptions[(string) $location['id']] = (string) $location['name'];
}

$pickupValue = $old['pickup_at'] ?? ($selection['pickup_at'] ?? '');
$returnValue = $old['return_at'] ?? ($selection['return_at'] ?? '');
$pickupLocation = (string) ($old['pickup_location_id'] ?? ($selection['pickup_location_id'] ?? ''));
$returnLocation = (string) ($old['return_location_id'] ?? ($selection['return_location_id'] ?? ''));
?>

<div class="container checkout-layout">
    <div>
        <?= component('navigation/breadcrumbs', ['items' => [
            ['label' => 'Cars', 'href' => '/cars'],
            ['label' => $name, 'href' => '/cars/' . rawurlencode($slug)],
            ['label' => 'Checkout'],
        ]]) ?>

        <div class="page-header">
            <div class="page-header__heading">
                <p class="eyebrow">Checkout</p>
                <h1 class="page-header__title">Complete your booking</h1>
                <p class="page-header__description">
                    Your request goes to <?= e($car['organization_name'] ?? 'the agency') ?>
                    for confirmation. No payment is taken now.
                </p>
            </div>
        </div>

        <?php if ($availability !== null && !$availability['available']): ?>
            <div style="margin-bottom: var(--space-5);">
                <?= component('feedback/alert', [
                    'type'    => 'warning',
                    'title'   => 'Not available for those dates',
                    'message' => (string) ($availability['reason'] ?? 'Please choose a different period.'),
                ]) ?>
            </div>
        <?php endif; ?>

        <?php if (!$hasPhone): ?>
            <div style="margin-bottom: var(--space-5);">
                <?= component('feedback/alert', [
                    'type'    => 'warning',
                    'title'   => 'Phone number required',
                    'message' => 'Add a phone number to your profile before submitting this booking.',
                ]) ?>
                <div style="margin-top: var(--space-3);">
                    <?= component('primitives/button', [
                        'label' => 'Add phone number',
                        'href'  => '/account/profile?redirect=' . rawurlencode('/cars/' . $slug . '/book'),
                        'size'  => 'sm',
                    ]) ?>
                </div>
            </div>
        <?php endif; ?>

        <form class="checkout-panel" method="post"
              action="/cars/<?= e(rawurlencode($slug)) ?>/book" data-guard-submit>
            <?= csrf_field() ?>

            <div class="checkout-step">
                <h2 class="checkout-step__title">
                    <span class="checkout-step__number">1</span>
                    Rental period
                </h2>

                <?= component('forms/datetime-field', [
                    'startValue' => $pickupValue,
                    'endValue'   => $returnValue,
                    'startLabel' => 'Pickup date and time',
                    'endLabel'   => 'Return date and time',
                    'errors'     => $errors,
                    'required'   => true,
                ]) ?>

                <p class="field__hint" style="margin-top: var(--space-3);">
                    Rentivo charges in whole 24-hour days. A period that runs even
                    slightly past a day boundary counts as an extra day.
                </p>
            </div>

            <div class="checkout-step">
                <h2 class="checkout-step__title">
                    <span class="checkout-step__number">2</span>
                    Pickup and return
                </h2>

                <div class="form-grid form-grid--2">
                    <?= component('forms/field', [
                        'name'    => 'pickup_location_id',
                        'label'   => 'Pickup location',
                        'type'    => 'select',
                        'value'   => $pickupLocation,
                        'options' => $locationOptions,
                        'errors'  => $errors,
                    ]) ?>
                    <?= component('forms/field', [
                        'name'    => 'return_location_id',
                        'label'   => 'Return location',
                        'type'    => 'select',
                        'value'   => $returnLocation,
                        'options' => $locationOptions,
                        'errors'  => $errors,
                    ]) ?>
                </div>
            </div>

            <div class="checkout-step">
                <h2 class="checkout-step__title">
                    <span class="checkout-step__number">3</span>
                    Anything else?
                </h2>

                <?= component('forms/field', [
                    'name'        => 'customer_notes',
                    'label'       => 'Notes for the agency',
                    'type'        => 'textarea',
                    'value'       => $old['customer_notes'] ?? '',
                    'placeholder' => 'Flight number, preferred pickup time, extra driver…',
                    'errors'      => $errors,
                ]) ?>
            </div>

            <div class="form-actions">
                <?= component('primitives/button', [
                    'label'    => 'Submit booking request',
                    'size'     => 'lg',
                    'disabled' => !$hasPhone,
                ]) ?>
                <?= component('primitives/button', [
                    'label'   => 'Back to car',
                    'href'    => '/cars/' . rawurlencode($slug),
                    'variant' => 'ghost',
                ]) ?>
            </div>
        </form>
    </div>

    <aside class="checkout-aside">
        <?= component('bookings/booking-summary', [
            'car'            => $car,
            'pickupAt'       => $pickupValue === '' ? null : str_replace('T', ' ', (string) $pickupValue),
            'returnAt'       => $returnValue === '' ? null : str_replace('T', ' ', (string) $returnValue),
            'pickupLocation' => $locationOptions[$pickupLocation] ?? null,
            'returnLocation' => $locationOptions[$returnLocation] ?? null,
            'quote'          => $quote,
        ]) ?>

        <div class="card card--padded stack-sm stack">
            <p class="text-strong text-sm">What happens next</p>
            <p class="text-xs text-muted">
                The agency reviews your request and confirms or declines it. You will
                be notified either way, and you can cancel while it is still pending
                or confirmed.
            </p>
        </div>
    </aside>
</div>
