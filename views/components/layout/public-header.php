<?php
/**
 * Public header.
 *
 * Shows the marketplace navigation plus, for signed-in users, notifications
 * and an account menu that also lists the organizations they belong to.
 *
 * @var array|null $currentUser
 * @var int        $unreadCount
 * @var array      $memberships
 * @var string     $currentPath
 */

$currentUser = $currentUser ?? null;
$unreadCount = (int) ($unreadCount ?? 0);
$memberships = $memberships ?? [];
$currentPath = $currentPath ?? '/';

$navItems = [
    ['label' => 'Browse cars', 'href' => '/cars'],
    ['label' => 'Agencies', 'href' => '/agencies'],
];

$isActive = static function (string $href) use ($currentPath): bool {
    return $href === '/' ? $currentPath === '/' : str_starts_with($currentPath, $href);
};

$avatarUrl = $currentUser === null
    ? null
    : ($currentUser['google_avatar_url'] ?? null);
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-header__brand" href="/" aria-label="Rentivo home">
            <span class="site-header__brand-mark" aria-hidden="true">R</span>
            <span>Rentivo</span>
        </a>

        <nav class="site-nav" aria-label="Primary">
            <?php foreach ($navItems as $item): ?>
                <a class="site-nav__link" href="<?= e($item['href']) ?>"
                   <?= $isActive($item['href']) ? 'aria-current="page"' : '' ?>>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="site-header__actions">
            <a class="icon-btn hide-lg" href="/cars" aria-label="Search cars">
                <?= component('primitives/icon', ['name' => 'search']) ?>
            </a>

            <?php if ($currentUser === null): ?>
                <?= component('primitives/button', [
                    'label'   => 'Sign in',
                    'href'    => '/login',
                    'variant' => 'secondary',
                    'size'    => 'sm',
                ]) ?>
            <?php else: ?>
                <a class="icon-btn site-header__notification" href="/account/notifications"
                   aria-label="Notifications<?= $unreadCount > 0 ? ' (' . $unreadCount . ' unread)' : '' ?>">
                    <?= component('primitives/icon', ['name' => 'bell']) ?>
                    <?php if ($unreadCount > 0): ?>
                        <span class="site-header__notification-dot" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>

                <div class="dropdown" data-dropdown>
                    <button type="button" class="icon-btn" data-dropdown-toggle
                            aria-label="Account menu">
                        <?= component('primitives/avatar', [
                            'imageUrl' => $avatarUrl,
                            'name'     => (string) $currentUser['name'],
                            'size'     => 'sm',
                        ]) ?>
                    </button>

                    <div class="dropdown__menu" role="menu">
                        <p class="dropdown__meta">
                            <strong><?= e($currentUser['name']) ?></strong><br>
                            <?= e($currentUser['email']) ?>
                        </p>
                        <div class="dropdown__divider"></div>

                        <a class="dropdown__item" href="/account" role="menuitem">
                            <?= component('primitives/icon', ['name' => 'user', 'size' => 16]) ?>
                            Account
                        </a>
                        <a class="dropdown__item" href="/account/bookings" role="menuitem">
                            <?= component('primitives/icon', ['name' => 'calendar', 'size' => 16]) ?>
                            Bookings
                        </a>
                        <a class="dropdown__item" href="/account/favorites" role="menuitem">
                            <?= component('primitives/icon', ['name' => 'heart', 'size' => 16]) ?>
                            Saved cars
                        </a>

                        <?php if ($memberships !== []): ?>
                            <div class="dropdown__divider"></div>
                            <p class="dropdown__label">Manage</p>
                            <?php foreach ($memberships as $membership): ?>
                                <a class="dropdown__item" href="/manage/<?= e($membership['slug']) ?>" role="menuitem">
                                    <?= component('primitives/icon', ['name' => 'building', 'size' => 16]) ?>
                                    <span class="truncate"><?= e($membership['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="dropdown__divider"></div>
                        <a class="dropdown__item" href="/organizations/create" role="menuitem">
                            <?= component('primitives/icon', ['name' => 'plus', 'size' => 16]) ?>
                            Create an organization
                        </a>

                        <div class="dropdown__divider"></div>
                        <form method="post" action="/logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown__item dropdown__item--danger" role="menuitem">
                                <?= component('primitives/icon', ['name' => 'log-out', 'size' => 16]) ?>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
