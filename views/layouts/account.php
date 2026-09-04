<?php
/**
 * Customer account shell.
 *
 * Adds the account navigation rail inside the public chrome, so account pages
 * render only their own content.
 *
 * @var string     $content
 * @var string     $accountSection
 * @var array|null $currentUser
 * @var int        $unreadCount
 * @var array      $flashMessages
 * @var array      $memberships
 */

$content = $content ?? '';
$accountSection = $accountSection ?? 'dashboard';
$currentUser = $currentUser ?? null;
$unreadCount = (int) ($unreadCount ?? 0);
$flashMessages = $flashMessages ?? [];
$memberships = $memberships ?? [];
$currentPath = $currentPath ?? '/account';
$isLocal = $isLocal ?? false;
$appName = $appName ?? 'Rentivo';

$navItems = [
    ['key' => 'dashboard',     'label' => 'Overview',      'href' => '/account',               'icon' => 'gauge'],
    ['key' => 'bookings',      'label' => 'Bookings',      'href' => '/account/bookings',      'icon' => 'calendar'],
    ['key' => 'favorites',     'label' => 'Saved cars',    'href' => '/account/favorites',     'icon' => 'heart'],
    ['key' => 'documents',     'label' => 'Documents',     'href' => '/account/documents',     'icon' => 'file-text'],
    ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/account/notifications', 'icon' => 'bell',
     'badge' => $unreadCount > 0 ? $unreadCount : null],
    ['key' => 'profile',       'label' => 'Profile',       'href' => '/account/profile',       'icon' => 'user'],
];

ob_start();
?>
<div class="app-shell">
    <?= component('layout/public-header', [
        'currentUser' => $currentUser,
        'unreadCount' => $unreadCount,
        'memberships' => $memberships,
        'currentPath' => $currentPath,
    ]) ?>

    <main class="app-main" id="main">
        <div class="container account-layout">
            <?= component('feedback/flash', ['flashMessages' => $flashMessages]) ?>

            <div class="layout-sidebar">
                <aside class="layout-sidebar__rail layout-sidebar__rail--sticky"
                       aria-label="Account sections">
                    <?php if ($currentUser !== null): ?>
                        <div class="row" style="gap: var(--space-3); padding: 0 var(--space-4) var(--space-5);">
                            <?= component('primitives/avatar', [
                                'imageUrl' => $currentUser['google_avatar_url'] ?? null,
                                'name'     => (string) $currentUser['name'],
                            ]) ?>
                            <div class="grow">
                                <p class="text-strong text-sm truncate"><?= e($currentUser['name']) ?></p>
                                <p class="text-xs text-muted truncate"><?= e($currentUser['email']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <nav class="account-nav">
                        <?php foreach ($navItems as $item): ?>
                            <a class="account-nav__link" href="<?= e($item['href']) ?>"
                               <?= $accountSection === $item['key'] ? 'aria-current="page"' : '' ?>>
                                <?= component('primitives/icon', ['name' => $item['icon'], 'size' => 16]) ?>
                                <span class="grow"><?= e($item['label']) ?></span>
                                <?php if (($item['badge'] ?? null) !== null): ?>
                                    <span class="account-nav__badge"><?= (int) $item['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </aside>

                <div class="grow">
                    <?= $content ?>
                </div>
            </div>
        </div>
    </main>

    <?= component('layout/public-footer', ['appName' => $appName, 'isLocal' => $isLocal]) ?>
</div>
<?php
echo component('layout/document', [
    'body'            => (string) ob_get_clean(),
    'title'           => $title ?? 'Account',
    'metaDescription' => 'Manage your Rentivo bookings, saved cars and documents.',
    'appName'         => $appName,
]);
