<?php
/**
 * Organization dashboard.
 *
 * Every figure comes from ReportService scoped to the current organization.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array $metrics
 * @var array $todayPickups
 * @var array $todayReturns
 * @var array $overdue
 * @var array $recentBookings
 * @var array $recentActivity
 * @var string $orgSlug
 */

use Rentivo\Support\Currency;

$metrics = $metrics ?? [];
$todayPickups = $todayPickups ?? [];
$todayReturns = $todayReturns ?? [];
$overdue = $overdue ?? [];
$recentBookings = $recentBookings ?? [];
$recentActivity = $recentActivity ?? [];
$orgSlug = $orgSlug ?? '';

$base = '/manage/' . rawurlencode($orgSlug);
$fleet = $metrics['fleet'] ?? [];
$totalCars = (int) ($metrics['total_cars'] ?? 0);

$overdueCount = (int) ($metrics['overdue_rentals'] ?? 0);

// A brand new organization gets a setup checklist instead of empty tiles.
$isNew = $totalCars === 0;
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Overview</p>
        <h1 class="page-header__title">Dashboard</h1>
    </div>
    <div class="page-header__actions">
        <?= component('primitives/button', [
            'label'   => 'View storefront',
            'href'    => '/agency/' . rawurlencode($orgSlug),
            'variant' => 'secondary',
            'icon'    => 'external',
        ]) ?>
    </div>
</div>

<?php if ($isNew): ?>
    <section class="card card--padded" style="margin-bottom: var(--space-7);">
        <h2 class="card__title" style="margin-bottom: var(--space-2);">Get your fleet online</h2>
        <p class="text-sm text-muted" style="margin-bottom: var(--space-5);">
            Three steps and your cars are bookable on the marketplace.
        </p>

        <div class="setup-checklist">
            <div class="setup-checklist__item" data-done="<?= $metrics['customers'] !== null ? '0' : '0' ?>">
                <span class="setup-checklist__marker">
                    <?= component('primitives/icon', ['name' => 'map-pin', 'size' => 13]) ?>
                </span>
                <span class="setup-checklist__label">Add a pickup location</span>
                <?= component('primitives/button', [
                    'label'   => 'Add location',
                    'href'    => $base . '/locations',
                    'variant' => 'secondary',
                    'size'    => 'sm',
                ]) ?>
            </div>
            <div class="setup-checklist__item" data-done="0">
                <span class="setup-checklist__marker">
                    <?= component('primitives/icon', ['name' => 'car', 'size' => 13]) ?>
                </span>
                <span class="setup-checklist__label">Add your first car</span>
                <?= component('primitives/button', [
                    'label'   => 'Add car',
                    'href'    => $base . '/cars/create',
                    'variant' => 'secondary',
                    'size'    => 'sm',
                ]) ?>
            </div>
            <div class="setup-checklist__item" data-done="0">
                <span class="setup-checklist__marker">
                    <?= component('primitives/icon', ['name' => 'image', 'size' => 13]) ?>
                </span>
                <span class="setup-checklist__label">Upload photographs so it appears on the marketplace</span>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="dashboard-metrics">
    <?= component('dashboard/metric-card', [
        'label' => 'Total cars',
        'value' => (string) $totalCars,
        'icon'  => 'car',
        'href'  => $base . '/cars',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Available',
        'value' => (string) (int) ($fleet['available'] ?? 0),
        'icon'  => 'check-circle',
        'href'  => $base . '/cars?status=available',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Rented',
        'value' => (string) (int) ($fleet['rented'] ?? 0),
        'icon'  => 'key',
        'href'  => $base . '/cars?status=rented',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Maintenance',
        'value' => (string) (int) ($fleet['maintenance'] ?? 0),
        'icon'  => 'settings',
        'href'  => $base . '/cars?status=maintenance',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Pending',
        'value' => (string) (int) ($metrics['pending_bookings'] ?? 0),
        'icon'  => 'inbox',
        'href'  => $base . '/bookings?status=pending',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label'   => 'This month',
        'value'   => Currency::amount((int) ($metrics['month_revenue_fils'] ?? 0)),
        'meta'    => 'BHD recorded revenue',
        'icon'    => 'chart',
        'variant' => 'emphasis',
    ]) ?>
</div>

<?php if ($overdueCount > 0): ?>
    <div style="margin-bottom: var(--space-6);">
        <?= component('feedback/alert', [
            'type'    => 'error',
            'title'   => $overdueCount . ' overdue rental' . ($overdueCount === 1 ? '' : 's'),
            'message' => 'These cars were due back and have not been returned yet.',
        ]) ?>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="stack-lg stack">
        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2 class="dashboard-section__title">Recent bookings</h2>
                <a class="text-sm text-muted" href="<?= e($base) ?>/bookings">View all</a>
            </div>
            <div class="dashboard-section__body">
                <?php if ($recentBookings === []): ?>
                    <?= component('feedback/empty-state', [
                        'title'       => 'No bookings yet',
                        'description' => 'Requests from the marketplace will appear here.',
                        'icon'        => 'calendar',
                        'flush'       => true,
                    ]) ?>
                <?php else: ?>
                    <div class="stack">
                        <?php foreach ($recentBookings as $booking): ?>
                            <?= component('bookings/booking-card', [
                                'booking'      => $booking,
                                'showCustomer' => true,
                                'href'         => $base . '/bookings/' . rawurlencode((string) $booking['reference']),
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($overdue !== []): ?>
            <section class="dashboard-section">
                <div class="dashboard-section__header">
                    <h2 class="dashboard-section__title">Overdue rentals</h2>
                    <a class="text-sm text-muted" href="<?= e($base) ?>/bookings?overdue=1">View all</a>
                </div>
                <div class="dashboard-section__body dashboard-section__body--flush">
                    <?php foreach ($overdue as $booking): ?>
                        <div class="schedule-item">
                            <span class="schedule-item__time" style="color: var(--color-danger);">
                                <?= e(date_display((string) $booking['return_at'])) ?>
                            </span>
                            <div class="schedule-item__body">
                                <p class="schedule-item__title">
                                    <a href="<?= e($base) ?>/bookings/<?= e(rawurlencode((string) $booking['reference'])) ?>">
                                        <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
                                    </a>
                                </p>
                                <p class="schedule-item__meta">
                                    <?= e($booking['customer_name'] ?? '') ?>
                                    · <?= e($booking['reference']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack-lg stack">
        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2 class="dashboard-section__title">Today</h2>
            </div>
            <div class="dashboard-section__body dashboard-section__body--flush">
                <?php if ($todayPickups === [] && $todayReturns === []): ?>
                    <div class="dashboard-section__body">
                        <?= component('feedback/empty-state', [
                            'title'       => 'Nothing scheduled today',
                            'description' => 'Pickups and returns due today appear here.',
                            'icon'        => 'calendar',
                            'flush'       => true,
                        ]) ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayPickups as $booking): ?>
                        <div class="schedule-item">
                            <span class="schedule-item__time">
                                <?= e(datetime_display((string) $booking['pickup_at'], 'H:i')) ?>
                            </span>
                            <div class="schedule-item__body">
                                <p class="schedule-item__title">
                                    Pickup — <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
                                </p>
                                <p class="schedule-item__meta"><?= e($booking['customer_name'] ?? '') ?></p>
                            </div>
                            <?= component('primitives/badge', ['label' => 'Out', 'tone' => 'info', 'small' => true]) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($todayReturns as $booking): ?>
                        <div class="schedule-item">
                            <span class="schedule-item__time">
                                <?= e(datetime_display((string) $booking['return_at'], 'H:i')) ?>
                            </span>
                            <div class="schedule-item__body">
                                <p class="schedule-item__title">
                                    Return — <?= e(trim(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''))) ?>
                                </p>
                                <p class="schedule-item__meta"><?= e($booking['customer_name'] ?? '') ?></p>
                            </div>
                            <?= component('primitives/badge', ['label' => 'In', 'tone' => 'success', 'small' => true]) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2 class="dashboard-section__title">Recent activity</h2>
                <?php if ($context !== null && $context->can(\Rentivo\Security\Permissions::REPORTS_VIEW)): ?>
                    <a class="text-sm text-muted" href="<?= e($base) ?>/activity">View all</a>
                <?php endif; ?>
            </div>
            <div class="dashboard-section__body">
                <?php if ($recentActivity === []): ?>
                    <?= component('feedback/empty-state', [
                        'title'       => 'No activity yet',
                        'description' => 'Actions taken by your team are recorded here.',
                        'icon'        => 'activity',
                        'flush'       => true,
                    ]) ?>
                <?php else: ?>
                    <?php foreach ($recentActivity as $entry): ?>
                        <?= component('dashboard/activity-item', ['entry' => $entry]) ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
