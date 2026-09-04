<?php
/**
 * Booking timeline.
 *
 * Combines the canonical lifecycle steps with the real audit entries recorded
 * for this booking, so the timeline reflects what actually happened rather
 * than a decorative fixed list.
 *
 * @var array  $booking
 * @var array  $entries  activity_logs rows for this booking (may be empty)
 */

use Rentivo\Services\AuditService;
use Rentivo\Services\BookingStatus;

$booking = $booking ?? [];
$entries = $entries ?? [];

$status = (string) ($booking['status'] ?? BookingStatus::PENDING);

$steps = [
    ['status' => BookingStatus::PENDING, 'label' => 'Requested', 'at' => $booking['created_at'] ?? null],
    ['status' => BookingStatus::CONFIRMED, 'label' => 'Confirmed', 'at' => $booking['confirmed_at'] ?? null],
    ['status' => BookingStatus::READY_FOR_PICKUP, 'label' => 'Ready for pickup', 'at' => null],
    ['status' => BookingStatus::ACTIVE, 'label' => 'Picked up', 'at' => null],
    ['status' => BookingStatus::COMPLETED, 'label' => 'Returned', 'at' => $booking['completed_at'] ?? null],
];

// Where the booking currently sits in the happy path.
$order = [
    BookingStatus::PENDING => 0,
    BookingStatus::CONFIRMED => 1,
    BookingStatus::READY_FOR_PICKUP => 2,
    BookingStatus::ACTIVE => 3,
    BookingStatus::COMPLETED => 4,
];

$reached = $order[$status] ?? 0;
$isTerminalFailure = in_array($status, [BookingStatus::REJECTED, BookingStatus::CANCELLED, BookingStatus::NO_SHOW], true);
?>
<div class="timeline">
    <?php foreach ($steps as $index => $step): ?>
        <?php
        $done = $index <= $reached && !($isTerminalFailure && $index > 0);
        ?>
        <div class="timeline__item">
            <span class="<?= e(class_names(['timeline__marker' => true, 'timeline__marker--done' => $done])) ?>">
                <?= component('primitives/icon', ['name' => $done ? 'check' : 'circle', 'size' => 14]) ?>
            </span>
            <div class="timeline__content">
                <p class="timeline__title"><?= e($step['label']) ?></p>
                <?php if ($step['at'] !== null): ?>
                    <p class="timeline__meta"><?= e(datetime_display((string) $step['at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($isTerminalFailure): ?>
        <div class="timeline__item">
            <span class="timeline__marker" style="background: var(--color-danger-bg); color: var(--color-danger);">
                <?= component('primitives/icon', ['name' => 'x', 'size' => 14]) ?>
            </span>
            <div class="timeline__content">
                <p class="timeline__title"><?= e(BookingStatus::label($status)) ?></p>
                <?php if (($booking['cancelled_at'] ?? null) !== null): ?>
                    <p class="timeline__meta"><?= e(datetime_display((string) $booking['cancelled_at'])) ?></p>
                <?php endif; ?>
                <?php
                $reason = $booking['rejection_reason'] ?? $booking['cancellation_reason'] ?? null;
                ?>
                <?php if ($reason !== null && trim((string) $reason) !== ''): ?>
                    <p class="timeline__meta"><?= e($reason) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($entries as $entry): ?>
        <div class="timeline__item">
            <span class="timeline__marker">
                <?= component('primitives/icon', ['name' => 'activity', 'size' => 14]) ?>
            </span>
            <div class="timeline__content">
                <p class="timeline__title"><?= e(AuditService::label((string) $entry['action_key'])) ?></p>
                <p class="timeline__meta">
                    <?= e($entry['actor_name'] ?? 'System') ?>
                    · <?= e(datetime_display((string) $entry['created_at'])) ?>
                </p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
