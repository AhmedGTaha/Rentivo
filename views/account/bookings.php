<?php
/**
 * Customer booking list.
 *
 * @var array                       $bookings
 * @var array                       $counts
 * @var string                      $group
 * @var int                         $total
 * @var \Rentivo\Support\Pagination $pagination
 */

$bookings = $bookings ?? [];
$counts = $counts ?? [];
$group = $group ?? 'upcoming';
$total = (int) ($total ?? 0);

$tabs = [
    ['key' => 'upcoming',  'label' => 'Upcoming'],
    ['key' => 'active',    'label' => 'Active'],
    ['key' => 'completed', 'label' => 'Completed'],
    ['key' => 'cancelled', 'label' => 'Cancelled'],
    ['key' => 'all',       'label' => 'All'],
];

$tabItems = array_map(static function (array $tab) use ($group, $counts): array {
    return [
        'label'  => $tab['label'],
        'href'   => '/account/bookings?group=' . $tab['key'],
        'count'  => $counts[$tab['key']] ?? null,
        'active' => $group === $tab['key'],
    ];
}, $tabs);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Account</p>
        <h1 class="page-header__title">Your bookings</h1>
    </div>
    <div class="page-header__actions">
        <?= component('primitives/button', [
            'label'   => 'Book another car',
            'href'    => '/cars',
            'variant' => 'secondary',
        ]) ?>
    </div>
</div>

<?= component('navigation/tabs', ['items' => $tabItems, 'label' => 'Booking status']) ?>

<div style="margin-top: var(--space-6);">
    <?php if ($bookings === []): ?>
        <?= component('feedback/empty-state', [
            'title'       => match ($group) {
                'upcoming'  => 'No upcoming bookings',
                'active'    => 'No active rentals',
                'completed' => 'No completed bookings yet',
                'cancelled' => 'No cancelled bookings',
                default     => 'No bookings yet',
            },
            'description' => 'Browse the marketplace and book a car to get started.',
            'icon'        => 'calendar',
            'actions'     => [['label' => 'Browse cars', 'href' => '/cars']],
        ]) ?>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($bookings as $booking): ?>
                <?= component('bookings/booking-card', ['booking' => $booking]) ?>
            <?php endforeach; ?>
        </div>

        <?= component('navigation/pagination', [
            'pagination' => $pagination,
            'path'       => '/account/bookings',
            'query'      => ['group' => $group],
        ]) ?>
    <?php endif; ?>
</div>
