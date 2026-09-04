<?php
/**
 * Organization booking list.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array $bookings, $filters, $statusCounts, $statusOptions, $carOptions
 * @var int   $total
 * @var \Rentivo\Support\Pagination $pagination
 * @var string $orgSlug
 */

use Rentivo\Services\BookingStatus;
use Rentivo\Support\Currency;
use Rentivo\Support\DateTimeHelper;

$bookings = $bookings ?? [];
$filters = $filters ?? [];
$statusCounts = $statusCounts ?? [];
$statusOptions = $statusOptions ?? BookingStatus::options();
$carOptions = $carOptions ?? [];
$total = (int) ($total ?? 0);
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$now = DateTimeHelper::nowDb();

$query = array_filter([
    'q'              => $filters['search'] ?? null,
    'status'         => $filters['status'] ?? null,
    'payment_status' => $filters['payment_status'] ?? null,
    'car_id'         => ($filters['car_id'] ?? 0) ?: null,
    'overdue'        => ($filters['overdue'] ?? false) ? '1' : null,
]);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Operations</p>
        <h1 class="page-header__title">Bookings</h1>
        <p class="page-header__description">
            <?= number_format($total) ?> <?= $total === 1 ? 'booking' : 'bookings' ?> matching your filters
        </p>
    </div>
</div>

<div class="fleet-filters">
    <a class="pill<?= ($filters['status'] ?? null) === null && !($filters['overdue'] ?? false) ? ' is-active' : '' ?>"
       href="<?= e($base) ?>/bookings">All</a>
    <?php foreach (['pending', 'confirmed', 'ready_for_pickup', 'active', 'completed'] as $status): ?>
        <a class="pill<?= ($filters['status'] ?? null) === $status ? ' is-active' : '' ?>"
           href="<?= e($base) ?>/bookings?status=<?= e($status) ?>">
            <?= e(BookingStatus::label($status)) ?>
            <span class="pill__count"><?= (int) ($statusCounts[$status] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
    <a class="pill<?= ($filters['overdue'] ?? false) ? ' is-active' : '' ?>"
       href="<?= e($base) ?>/bookings?overdue=1">Overdue</a>
</div>

<form class="table-toolbar" method="get" action="<?= e($base) ?>/bookings" data-filter-form>
    <div class="table-toolbar__search">
        <?= component('forms/search-field', [
            'name'        => 'q',
            'value'       => (string) ($filters['search'] ?? ''),
            'placeholder' => 'Reference, customer, car or plate',
            'label'       => 'Search bookings',
            'attributes'  => ['data-filter-debounce' => true],
        ]) ?>
    </div>

    <div class="table-toolbar__filters">
        <label class="visually-hidden" for="booking-status">Status</label>
        <select class="select" id="booking-status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= e($value) ?>"
                    <?= ($filters['status'] ?? null) === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="visually-hidden" for="booking-payment">Payment status</label>
        <select class="select" id="booking-payment" name="payment_status">
            <option value="">Any payment</option>
            <?php foreach (BookingStatus::paymentStatuses() as $value): ?>
                <option value="<?= e($value) ?>"
                    <?= ($filters['payment_status'] ?? null) === $value ? 'selected' : '' ?>>
                    <?= e(BookingStatus::paymentLabel($value)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($carOptions !== []): ?>
            <label class="visually-hidden" for="booking-car">Car</label>
            <select class="select" id="booking-car" name="car_id">
                <option value="">All cars</option>
                <?php foreach ($carOptions as $car): ?>
                    <option value="<?= (int) $car['id'] ?>"
                        <?= (int) ($filters['car_id'] ?? 0) === (int) $car['id'] ? 'selected' : '' ?>>
                        <?= e(trim($car['brand'] . ' ' . $car['model'])) ?>
                        <?= ($car['plate_number'] ?? null) !== null ? '· ' . e($car['plate_number']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <noscript><button type="submit" class="btn btn--secondary btn--sm">Filter</button></noscript>
    </div>
</form>

<?php if ($bookings === []): ?>
    <?= component('feedback/empty-state', [
        'title'       => 'No bookings match',
        'description' => 'Clear the filters, or wait for new requests from the marketplace.',
        'icon'        => 'calendar',
        'actions'     => $query !== []
            ? [['label' => 'Clear filters', 'href' => $base . '/bookings', 'variant' => 'secondary']]
            : [],
    ]) ?>
<?php else: ?>
    <div class="table-wrap table-wrap--stack">
        <table class="data-table data-table--stack">
            <caption class="visually-hidden">Organization bookings</caption>
            <thead>
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Car</th>
                    <th scope="col">Period</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="data-table__numeric">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $isOverdue = (string) $booking['status'] === BookingStatus::ACTIVE
                        && (string) $booking['return_at'] < $now;
                    ?>
                    <tr>
                        <td data-label="Reference">
                            <a class="data-table__link"
                               href="<?= e($base) ?>/bookings/<?= e(rawurlencode((string) $booking['reference'])) ?>">
                                <?= e($booking['reference']) ?>
                            </a>
                            <span class="data-table__secondary">
                                <?= e(relative_time((string) $booking['created_at'])) ?>
                            </span>
                        </td>
                        <td data-label="Customer">
                            <span class="data-table__primary"><?= e($booking['customer_name'] ?? '') ?></span>
                            <span class="data-table__secondary"><?= e($booking['customer_phone'] ?? '') ?></span>
                        </td>
                        <td data-label="Car">
                            <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
                            <span class="data-table__secondary"><?= e($booking['plate_number'] ?? '') ?></span>
                        </td>
                        <td data-label="Period">
                            <?= e(datetime_display((string) $booking['pickup_at'], 'd M, H:i')) ?>
                            <span class="data-table__secondary">
                                to <?= e(datetime_display((string) $booking['return_at'], 'd M, H:i')) ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <?= component('bookings/booking-status-badge', [
                                'status' => (string) $booking['status'],
                                'small'  => true,
                            ]) ?>
                            <?php if ($isOverdue): ?>
                                <span class="overdue-flag" style="margin-top: var(--space-1);">
                                    <?= component('primitives/icon', ['name' => 'alert', 'size' => 13]) ?>
                                    Overdue
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Total" class="data-table__numeric">
                            <?= e(Currency::amount((int) $booking['total_fils'])) ?>
                            <span class="data-table__secondary">
                                <?= e(BookingStatus::paymentLabel((string) $booking['payment_status'])) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= component('navigation/pagination', [
        'pagination' => $pagination,
        'path'       => $base . '/bookings',
        'query'      => $query,
    ]) ?>
<?php endif; ?>
