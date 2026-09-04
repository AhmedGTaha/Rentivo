<?php
/**
 * Customer dashboard.
 *
 * Deliberately simple: what is happening now, what is coming next, and the
 * few actions that need attention.
 *
 * @var array      $profile
 * @var array|null $nextBooking
 * @var array|null $activeBooking
 * @var array      $recentBookings
 * @var array      $counts
 * @var int        $favoriteCount
 * @var array      $notifications
 * @var array|null $currentUser
 */

$profile = $profile ?? [];
$nextBooking = $nextBooking ?? null;
$activeBooking = $activeBooking ?? null;
$recentBookings = $recentBookings ?? [];
$counts = $counts ?? [];
$favoriteCount = (int) ($favoriteCount ?? 0);
$notifications = $notifications ?? [];
$currentUser = $currentUser ?? [];

$highlight = $activeBooking ?? $nextBooking;
$hasPhone = isset($profile['phone']) && trim((string) $profile['phone']) !== '';
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Your account</p>
        <h1 class="page-header__title">
            Welcome back, <?= e(strtok((string) ($currentUser['name'] ?? 'there'), ' ')) ?>
        </h1>
    </div>
    <div class="page-header__actions">
        <?= component('primitives/button', [
            'label'   => 'Browse cars',
            'href'    => '/cars',
            'variant' => 'secondary',
        ]) ?>
    </div>
</div>

<?php if (!$hasPhone): ?>
    <div style="margin-bottom: var(--space-6);">
        <?= component('feedback/alert', [
            'type'    => 'warning',
            'title'   => 'Add a phone number',
            'message' => 'A phone number is required before you can submit a booking. '
                . 'Add one on your profile to be ready to book.',
        ]) ?>
    </div>
<?php endif; ?>

<?php if ($highlight !== null): ?>
    <div class="account-highlight" style="margin-bottom: var(--space-6);">
        <div class="grow">
            <p class="account-highlight__label">
                <?= $activeBooking !== null ? 'Currently rented' : 'Next booking' ?>
            </p>
            <p class="account-highlight__title">
                <?= e(trim(($highlight['brand'] ?? '') . ' ' . ($highlight['model'] ?? ''))) ?>
            </p>
            <p class="account-highlight__meta">
                <?= e($highlight['organization_name'] ?? '') ?>
                · <?= e(datetime_display((string) $highlight['pickup_at'])) ?>
                → <?= e(datetime_display((string) $highlight['return_at'])) ?>
            </p>
        </div>
        <div class="account-highlight__actions">
            <?= component('primitives/button', [
                'label'   => 'View booking',
                'href'    => '/account/bookings/' . rawurlencode((string) $highlight['reference']),
                'variant' => 'inverse',
            ]) ?>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom: var(--space-7);">
    <?= component('dashboard/metric-card', [
        'label' => 'Upcoming',
        'value' => (string) (int) ($counts['upcoming'] ?? 0),
        'icon'  => 'calendar',
        'href'  => '/account/bookings?group=upcoming',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Active',
        'value' => (string) (int) ($counts['active'] ?? 0),
        'icon'  => 'key',
        'href'  => '/account/bookings?group=active',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Completed',
        'value' => (string) (int) ($counts['completed'] ?? 0),
        'icon'  => 'check-circle',
        'href'  => '/account/bookings?group=completed',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Saved cars',
        'value' => (string) $favoriteCount,
        'icon'  => 'heart',
        'href'  => '/account/favorites',
    ]) ?>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Recent bookings</h2>
            <a class="text-sm text-muted" href="/account/bookings">View all</a>
        </div>
        <div class="card__body">
            <?php if ($recentBookings === []): ?>
                <?= component('feedback/empty-state', [
                    'title'       => 'No bookings yet',
                    'description' => 'When you book a car it will appear here.',
                    'icon'        => 'calendar',
                    'flush'       => true,
                    'actions'     => [['label' => 'Browse cars', 'href' => '/cars']],
                ]) ?>
            <?php else: ?>
                <div class="stack">
                    <?php foreach ($recentBookings as $booking): ?>
                        <?= component('bookings/booking-card', ['booking' => $booking]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Recent notifications</h2>
            <a class="text-sm text-muted" href="/account/notifications">View all</a>
        </div>
        <div class="card__body card__body--tight">
            <?php if ($notifications === []): ?>
                <?= component('feedback/empty-state', [
                    'title'       => 'Nothing new',
                    'description' => 'Booking updates will show up here.',
                    'icon'        => 'bell',
                    'flush'       => true,
                ]) ?>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item<?= $notification['read_at'] === null ? ' notification-item--unread' : '' ?>"
                         style="padding-inline: 0;">
                        <span class="notification-item__marker" aria-hidden="true"></span>
                        <div class="notification-item__body">
                            <p class="notification-item__title"><?= e($notification['title']) ?></p>
                            <p class="notification-item__message"><?= e($notification['message']) ?></p>
                            <p class="notification-item__meta">
                                <?= e(relative_time((string) $notification['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
