<?php
/**
 * Organization reports.
 *
 * Charts are plain CSS bars — V1 deliberately adds no charting dependency.
 *
 * @var array  $report
 * @var string $from, $to
 * @var string $orgSlug
 */

use Rentivo\Services\CarService;
use Rentivo\Support\Currency;

$report = $report ?? [];
$from = $from ?? '';
$to = $to ?? '';
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$bookings = $report['bookings'] ?? [];
$byMonth = $report['by_month'] ?? [];
$mostRented = $report['most_rented'] ?? [];
$fleet = $report['fleet'] ?? [];

$maxMonth = 0;
foreach ($byMonth as $month) {
    $maxMonth = max($maxMonth, (int) $month['total']);
}

$fleetTotal = array_sum($fleet);

$fleetTones = [
    'available'   => 'var(--color-success)',
    'reserved'    => 'var(--color-info)',
    'rented'      => 'var(--color-ink)',
    'maintenance' => 'var(--color-danger)',
    'inactive'    => 'var(--color-border-strong)',
];
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Insight</p>
        <h1 class="page-header__title">Reports</h1>
        <p class="page-header__description">
            Everything here is scoped to your organization alone.
        </p>
    </div>
</div>

<form class="table-toolbar" method="get" action="<?= e($base) ?>/reports">
    <div class="filter-panel__row" style="flex: 1 1 420px;">
        <?= component('forms/field', [
            'name'  => 'from',
            'label' => 'From',
            'type'  => 'datetime-local',
            'value' => $from,
        ]) ?>
        <?= component('forms/field', [
            'name'  => 'to',
            'label' => 'To',
            'type'  => 'datetime-local',
            'value' => $to,
        ]) ?>
    </div>
    <?= component('primitives/button', ['label' => 'Apply range']) ?>
    <?php if ($from !== '' || $to !== ''): ?>
        <?= component('primitives/button', [
            'label'   => 'Reset',
            'href'    => $base . '/reports',
            'variant' => 'ghost',
        ]) ?>
    <?php endif; ?>
</form>

<div class="dashboard-metrics">
    <?= component('dashboard/metric-card', [
        'label' => 'Total bookings',
        'value' => (string) (int) ($bookings['total'] ?? 0),
        'icon'  => 'calendar',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Pending',
        'value' => (string) (int) ($bookings['pending'] ?? 0),
        'icon'  => 'inbox',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Confirmed',
        'value' => (string) (int) ($bookings['confirmed'] ?? 0),
        'icon'  => 'check',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Completed',
        'value' => (string) (int) ($bookings['completed'] ?? 0),
        'icon'  => 'check-circle',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Cancelled',
        'value' => (string) ((int) ($bookings['cancelled'] ?? 0) + (int) ($bookings['rejected'] ?? 0)),
        'icon'  => 'x-circle',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label'   => 'Recorded revenue',
        'value'   => Currency::amount((int) ($report['revenue_fils'] ?? 0)),
        'meta'    => 'BHD from completed rentals',
        'icon'    => 'chart',
        'variant' => 'emphasis',
    ]) ?>
</div>

<div class="grid grid-2" style="margin-bottom: var(--space-6);">
    <?= component('dashboard/metric-card', [
        'label' => 'Active rentals',
        'value' => (string) (int) ($report['active_rentals'] ?? 0),
        'icon'  => 'key',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label'   => 'Overdue rentals',
        'value'   => (string) (int) ($report['overdue_rentals'] ?? 0),
        'icon'    => 'alert',
        'variant' => (int) ($report['overdue_rentals'] ?? 0) > 0 ? 'alert' : 'default',
        'href'    => $base . '/bookings?overdue=1',
    ]) ?>
</div>

<div class="report-grid report-grid--split">
    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Bookings by month</h2>
        </div>
        <div class="card__body">
            <?php if ($byMonth === []): ?>
                <?= component('feedback/empty-state', [
                    'title'       => 'Not enough data yet',
                    'description' => 'Monthly trends appear once you have bookings.',
                    'icon'        => 'chart',
                    'flush'       => true,
                ]) ?>
            <?php else: ?>
                <div class="bar-chart">
                    <?php foreach ($byMonth as $month): ?>
                        <?php
                        $height = $maxMonth === 0 ? 0 : (int) round(((int) $month['total'] / $maxMonth) * 100);
                        ?>
                        <div class="bar-chart__column">
                            <span class="text-xs numeric text-muted"><?= (int) $month['total'] ?></span>
                            <div class="bar-chart__bar" style="height: <?= $height ?>%"
                                 role="img"
                                 aria-label="<?= e($month['month']) ?>: <?= (int) $month['total'] ?> bookings"></div>
                            <span class="bar-chart__label">
                                <?= e(substr((string) $month['month'], 5)) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Fleet status</h2>
            <span class="text-xs text-muted"><?= (int) $fleetTotal ?> vehicles</span>
        </div>
        <div class="card__body">
            <?php if ($fleetTotal === 0): ?>
                <?= component('feedback/empty-state', [
                    'title'       => 'No vehicles yet',
                    'description' => 'Add cars to see how your fleet is distributed.',
                    'icon'        => 'car',
                    'flush'       => true,
                ]) ?>
            <?php else: ?>
                <div class="meter">
                    <?php foreach ($fleet as $statusKey => $count): ?>
                        <?php if ((int) $count === 0) { continue; } ?>
                        <span class="meter__segment"
                              style="width: <?= round(((int) $count / $fleetTotal) * 100, 2) ?>%;
                                     background: <?= e($fleetTones[$statusKey] ?? 'var(--color-neutral)') ?>;"></span>
                    <?php endforeach; ?>
                </div>

                <div class="meter__legend">
                    <?php foreach ($fleet as $statusKey => $count): ?>
                        <span class="meter__legend-item">
                            <span class="meter__swatch"
                                  style="background: <?= e($fleetTones[$statusKey] ?? 'var(--color-neutral)') ?>;"></span>
                            <?= e(CarService::statusLabel((string) $statusKey)) ?>
                            <strong class="numeric"><?= (int) $count ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="card" style="margin-top: var(--space-6);">
    <div class="card__header">
        <h2 class="card__title">Most rented cars</h2>
    </div>

    <?php if ($mostRented === []): ?>
        <div class="card__body">
            <?= component('feedback/empty-state', [
                'title'       => 'No rentals recorded yet',
                'description' => 'Once bookings complete, your best performers appear here.',
                'icon'        => 'car',
                'flush'       => true,
            ]) ?>
        </div>
    <?php else: ?>
        <div class="table-wrap table-wrap--stack" style="border: none; border-radius: 0;">
            <table class="data-table data-table--stack">
                <caption class="visually-hidden">Most rented cars</caption>
                <thead>
                    <tr>
                        <th scope="col">Car</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="data-table__numeric">Bookings</th>
                        <th scope="col" class="data-table__numeric">Completed</th>
                        <th scope="col" class="data-table__numeric">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mostRented as $car): ?>
                        <tr>
                            <td data-label="Car">
                                <span class="data-table__primary">
                                    <?= e(trim($car['brand'] . ' ' . $car['model'])) ?>
                                </span>
                                <span class="data-table__secondary">
                                    <?= e($car['year']) ?>
                                    <?= ($car['plate_number'] ?? null) !== null ? '· ' . e($car['plate_number']) : '' ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <?= component('cars/car-status-badge', [
                                    'status' => (string) $car['status'],
                                    'small'  => true,
                                ]) ?>
                            </td>
                            <td data-label="Bookings" class="data-table__numeric">
                                <?= (int) $car['booking_count'] ?>
                            </td>
                            <td data-label="Completed" class="data-table__numeric">
                                <?= (int) $car['completed_count'] ?>
                            </td>
                            <td data-label="Revenue" class="data-table__numeric">
                                <?= e(Currency::amount((int) $car['revenue_fils'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
