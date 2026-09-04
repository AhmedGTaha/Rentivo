<?php
/**
 * Notification centre.
 *
 * @var array                       $notifications
 * @var int                         $total
 * @var bool                        $unreadOnly
 * @var int                         $unread
 * @var \Rentivo\Support\Pagination $pagination
 */

$notifications = $notifications ?? [];
$total = (int) ($total ?? 0);
$unreadOnly = $unreadOnly ?? false;
$unread = (int) ($unread ?? 0);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Account</p>
        <h1 class="page-header__title">Notifications</h1>
        <p class="page-header__description">
            <?= $unread === 0 ? 'You are all caught up.' : $unread . ' unread' ?>
        </p>
    </div>
    <div class="page-header__actions">
        <?php if ($unread > 0): ?>
            <form method="post" action="/account/notifications/read-all">
                <?= csrf_field() ?>
                <?= component('primitives/button', [
                    'label'   => 'Mark all as read',
                    'variant' => 'secondary',
                    'icon'    => 'check',
                ]) ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<?= component('navigation/tabs', [
    'label' => 'Notification filter',
    'items' => [
        ['label' => 'All', 'href' => '/account/notifications', 'active' => !$unreadOnly],
        ['label' => 'Unread', 'href' => '/account/notifications?filter=unread',
         'count' => $unread, 'active' => $unreadOnly],
    ],
]) ?>

<div class="card" style="margin-top: var(--space-6);">
    <?php if ($notifications === []): ?>
        <div class="card__body">
            <?= component('feedback/empty-state', [
                'title'       => $unreadOnly ? 'Nothing unread' : 'No notifications yet',
                'description' => 'Booking confirmations, reminders and document updates appear here.',
                'icon'        => 'bell',
                'flush'       => true,
            ]) ?>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
            <?php $isUnread = $notification['read_at'] === null; ?>
            <article class="notification-item<?= $isUnread ? ' notification-item--unread' : '' ?>">
                <span class="notification-item__marker" aria-hidden="true"></span>

                <div class="notification-item__body">
                    <p class="notification-item__title"><?= e($notification['title']) ?></p>
                    <p class="notification-item__message"><?= e($notification['message']) ?></p>
                    <p class="notification-item__meta">
                        <span><?= e(relative_time((string) $notification['created_at'])) ?></span>
                        <?php if (($notification['organization_name'] ?? null) !== null): ?>
                            <span><?= e($notification['organization_name']) ?></span>
                        <?php endif; ?>
                        <?php if (($notification['action_url'] ?? null) !== null): ?>
                            <a href="<?= e($notification['action_url']) ?>">View</a>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($isUnread): ?>
                    <div class="notification-item__actions">
                        <form method="post"
                              action="/account/notifications/<?= (int) $notification['id'] ?>/read">
                            <?= csrf_field() ?>
                            <button type="submit" class="icon-btn icon-btn--sm"
                                    aria-label="Mark as read">
                                <?= component('primitives/icon', ['name' => 'check', 'size' => 16]) ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= component('navigation/pagination', [
    'pagination' => $pagination,
    'path'       => '/account/notifications',
    'query'      => $unreadOnly ? ['filter' => 'unread'] : [],
]) ?>
