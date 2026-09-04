<?php
/**
 * Car detail page.
 *
 * Deliberately never renders VIN, plate number, internal notes, customer data
 * or audit history — those are management-only fields.
 *
 * @var array      $car
 * @var array      $images
 * @var array|null $organization
 * @var array      $locations
 * @var array      $relatedCars
 * @var bool       $isFavorite
 * @var \Rentivo\Repositories\CarFilters $filters
 * @var array|null $availability  ['checked'=>bool,'available'=>bool,'reason'=>?string,'quote'=>?array]
 */

use Rentivo\Services\CarService;
use Rentivo\Support\DateTimeHelper;

$car = $car ?? [];
$images = $images ?? [];
$locations = $locations ?? [];
$relatedCars = $relatedCars ?? [];
$isFavorite = $isFavorite ?? false;
$availability = $availability ?? null;

$name = trim(($car['brand'] ?? '') . ' ' . ($car['model'] ?? ''));
$slug = (string) ($car['slug'] ?? '');
$agencySlug = (string) ($car['organization_slug'] ?? '');
$status = (string) ($car['status'] ?? 'available');

$pickupValue = $filters->hasDateWindow() ? DateTimeHelper::toInput($filters->pickupAt) : '';
$returnValue = $filters->hasDateWindow() ? DateTimeHelper::toInput($filters->returnAt) : '';

// The booking link carries the visitor's selection through to checkout, and
// through Google sign-in if they are not yet authenticated.
$bookQuery = array_filter([
    'pickup_at' => $pickupValue,
    'return_at' => $returnValue,
]);
$bookUrl = '/cars/' . rawurlencode($slug) . '/book' . query_string($bookQuery);

$isRentable = !in_array($status, ['maintenance', 'inactive'], true);
?>

<div class="container car-detail">
    <?= component('navigation/breadcrumbs', ['items' => [
        ['label' => 'Cars', 'href' => '/cars'],
        ['label' => (string) ($car['organization_name'] ?? ''), 'href' => '/agency/' . $agencySlug],
        ['label' => $name],
    ]]) ?>

    <div class="car-detail__layout">
        <div>
            <?= component('cars/car-gallery', [
                'images'     => $images,
                'car'        => $car,
                'isFavorite' => $isFavorite,
            ]) ?>

            <header class="car-detail__header" style="margin-top: var(--space-7);">
                <a class="car-detail__agency" href="/agency/<?= e(rawurlencode($agencySlug)) ?>">
                    <?php if (($car['organization_logo_path'] ?? null) !== null): ?>
                        <img class="car-card__agency-logo"
                             src="<?= e('/uploads/' . ltrim((string) $car['organization_logo_path'], '/')) ?>"
                             alt="" loading="lazy">
                    <?php else: ?>
                        <?= component('primitives/icon', ['name' => 'building', 'size' => 15]) ?>
                    <?php endif; ?>
                    <?= e($car['organization_name'] ?? '') ?>
                </a>

                <h1 class="car-detail__title"><?= e($name) ?></h1>

                <div class="car-detail__tags">
                    <?= component('primitives/badge', [
                        'label' => (string) ($car['year'] ?? ''),
                        'tone'  => 'outline',
                    ]) ?>
                    <?php if (($car['category_name'] ?? null) !== null): ?>
                        <?= component('primitives/badge', [
                            'label' => (string) $car['category_name'],
                            'tone'  => 'neutral',
                        ]) ?>
                    <?php endif; ?>
                    <?php if (!$isRentable): ?>
                        <?= component('cars/car-status-badge', ['status' => $status]) ?>
                    <?php endif; ?>
                </div>
            </header>

            <section class="car-section">
                <h2 class="car-section__title">Specifications</h2>
                <?= component('cars/car-specifications', ['car' => $car]) ?>
            </section>

            <?php if (($car['description'] ?? null) !== null && trim((string) $car['description']) !== ''): ?>
                <section class="car-section">
                    <h2 class="car-section__title">Overview</h2>
                    <p class="car-section__body"><?= e($car['description']) ?></p>
                </section>
            <?php endif; ?>

            <?php if ($locations !== []): ?>
                <section class="car-section">
                    <h2 class="car-section__title">Pickup locations</h2>
                    <div class="stack">
                        <?php foreach ($locations as $location): ?>
                            <div class="location-card">
                                <span class="location-card__icon">
                                    <?= component('primitives/icon', ['name' => 'map-pin', 'size' => 18]) ?>
                                </span>
                                <div class="grow">
                                    <p class="location-card__name"><?= e($location['name']) ?></p>
                                    <p class="location-card__meta"><?= e($location['address']) ?></p>
                                    <?php if (($location['opening_hours'] ?? null) !== null): ?>
                                        <p class="location-card__meta">
                                            <?= e($location['opening_hours']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (($car['organization_rental_terms'] ?? null) !== null
                && trim((string) $car['organization_rental_terms']) !== ''): ?>
                <section class="car-section">
                    <h2 class="car-section__title">Rental terms</h2>
                    <div class="car-terms"><?= e($car['organization_rental_terms']) ?></div>
                </section>
            <?php endif; ?>
        </div>

        <?php /* Booking panel */ ?>
        <aside>
            <div class="booking-panel">
                <div class="booking-panel__price">
                    <?= component('cars/car-price', [
                        'fils'  => (int) ($car['daily_rate_fils'] ?? 0),
                        'large' => true,
                    ]) ?>
                </div>

                <?php if (!$isRentable): ?>
                    <?= component('feedback/alert', [
                        'type'    => 'warning',
                        'message' => $status === 'maintenance'
                            ? 'This car is currently under maintenance and cannot be booked.'
                            : 'This car is not accepting new bookings.',
                    ]) ?>
                <?php else: ?>
                    <form class="booking-panel__form" method="get"
                          action="/cars/<?= e(rawurlencode($slug)) ?>">
                        <?= component('forms/datetime-field', [
                            'startValue' => $pickupValue,
                            'endValue'   => $returnValue,
                            'startLabel' => 'Pickup date and time',
                            'endLabel'   => 'Return date and time',
                        ]) ?>

                        <?= component('primitives/button', [
                            'label'   => 'Check availability',
                            'variant' => 'secondary',
                            'block'   => true,
                        ]) ?>
                    </form>

                    <?php if ($availability !== null): ?>
                        <?php if ($availability['available']): ?>
                            <?= component('feedback/alert', [
                                'type'    => 'success',
                                'message' => 'Available for the dates you selected.',
                            ]) ?>

                            <?php if (($availability['quote'] ?? null) !== null): ?>
                                <?= component('bookings/price-breakdown', [
                                    'dailyRateFils'         => (int) $availability['quote']['daily_rate_fils'],
                                    'rentalDays'            => (int) $availability['quote']['rental_days'],
                                    'subtotalFils'          => (int) $availability['quote']['subtotal_fils'],
                                    'additionalChargesFils' => 0,
                                    'totalFils'             => (int) $availability['quote']['total_fils'],
                                ]) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= component('feedback/alert', [
                                'type'    => 'warning',
                                'message' => (string) ($availability['reason'] ?? 'Not available for those dates.'),
                            ]) ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?= component('primitives/button', [
                        'label' => 'Book now',
                        'href'  => $bookUrl,
                        'size'  => 'lg',
                        'block' => true,
                    ]) ?>

                    <p class="booking-panel__note">
                        No payment is taken online. You pay the agency at pickup.
                    </p>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <?php if ($relatedCars !== []): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-header__title">You might also like</h2>
            </div>
            <div class="car-grid car-grid--compact">
                <?php foreach ($relatedCars as $related): ?>
                    <?= component('cars/car-card', [
                        'car'     => $related,
                        'variant' => 'compact',
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if ($isRentable): ?>
    <?php /* Sticky mobile booking bar. */ ?>
    <div class="booking-sticky">
        <div>
            <?= component('cars/car-price', ['fils' => (int) ($car['daily_rate_fils'] ?? 0)]) ?>
        </div>
        <?= component('primitives/button', ['label' => 'Book now', 'href' => $bookUrl]) ?>
    </div>
<?php endif; ?>
