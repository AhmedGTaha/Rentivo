<?php
/**
 * Management console shell.
 *
 * Sidebar navigation is filtered by the caller's real permissions, and the
 * same permission is asserted server-side by every controller — hiding a link
 * is a convenience, never the authorization.
 *
 * @var string                                  $content
 * @var \Rentivo\Security\OrganizationContext   $context
 * @var array                                   $organization
 * @var string                                  $manageSection
 * @var array                                   $flashMessages
 * @var array|null                              $currentUser
 * @var array                                   $memberships
 */

use Rentivo\Security\Permissions;

$content = $content ?? '';
$context = $context ?? null;
$organization = $organization ?? [];
$manageSection = $manageSection ?? 'dashboard';
$flashMessages = $flashMessages ?? [];
$currentUser = $currentUser ?? null;
$memberships = $memberships ?? [];
$appName = $appName ?? 'Rentivo';

$slug = (string) ($organization['slug'] ?? '');
$base = '/manage/' . rawurlencode($slug);

/** Nav entries the current user is actually allowed to open. */
$sections = [
    ['key' => 'dashboard',  'label' => 'Dashboard',  'href' => $base,                 'icon' => 'gauge',
     'visible' => true],
    ['key' => 'bookings',   'label' => 'Bookings',   'href' => $base . '/bookings',   'icon' => 'calendar',
     'visible' => $context !== null && $context->can(Permissions::BOOKINGS_VIEW)],
    ['key' => 'cars',       'label' => 'Fleet',      'href' => $base . '/cars',       'icon' => 'car',
     'visible' => $context !== null && $context->can(Permissions::CARS_VIEW)],
    ['key' => 'categories', 'label' => 'Categories', 'href' => $base . '/categories', 'icon' => 'star',
     'visible' => $context !== null && $context->can(Permissions::CARS_VIEW)],
    ['key' => 'locations',  'label' => 'Locations',  'href' => $base . '/locations',  'icon' => 'map-pin',
     'visible' => $context !== null && $context->canAny([Permissions::LOCATIONS_VIEW, Permissions::LOCATIONS_MANAGE])],
    ['key' => 'customers',  'label' => 'Customers',  'href' => $base . '/customers',  'icon' => 'users',
     'visible' => $context !== null && $context->can(Permissions::CUSTOMERS_VIEW)],
    ['key' => 'documents',  'label' => 'Documents',  'href' => $base . '/documents',  'icon' => 'file-text',
     'visible' => $context !== null && $context->can(Permissions::DOCUMENTS_VIEW)],
    ['key' => 'employees',  'label' => 'Employees',  'href' => $base . '/employees',  'icon' => 'shield',
     'visible' => $context !== null && $context->isAdmin()],
    ['key' => 'reports',    'label' => 'Reports',    'href' => $base . '/reports',    'icon' => 'chart',
     'visible' => $context !== null && $context->can(Permissions::REPORTS_VIEW)],
    ['key' => 'activity',   'label' => 'Activity',   'href' => $base . '/activity',   'icon' => 'activity',
     'visible' => $context !== null && $context->can(Permissions::REPORTS_VIEW)],
    ['key' => 'settings',   'label' => 'Settings',   'href' => $base . '/settings',   'icon' => 'settings',
     'visible' => $context !== null && $context->isAdmin()],
];

$sections = array_values(array_filter($sections, static fn (array $s): bool => $s['visible']));

$renderNav = static function (array $sections, string $active): string {
    $html = '<div class="manage-sidebar__group">';

    foreach ($sections as $section) {
        $html .= '<a class="manage-sidebar__link" href="' . e($section['href']) . '"'
            . ($active === $section['key'] ? ' aria-current="page"' : '') . '>'
            . '<span class="manage-sidebar__link-accent" aria-hidden="true"></span>'
            . component('primitives/icon', ['name' => $section['icon'], 'size' => 16])
            . '<span>' . e($section['label']) . '</span>'
            . '</a>';
    }

    return $html . '</div>';
};

$logo = $organization['logo_path'] ?? null;
$roleLabel = $context !== null && $context->isAdmin() ? 'Administrator' : 'Employee';

ob_start();
?>
<div class="manage-shell">
    <aside class="manage-sidebar" aria-label="Organization navigation">
        <div class="manage-sidebar__brand">
            <span class="org-chip__logo">
                <?php if ($logo !== null && $logo !== ''): ?>
                    <img src="<?= e('/uploads/' . ltrim((string) $logo, '/')) ?>" alt="">
                <?php else: ?>
                    <?= e(\Rentivo\Support\Str::initials((string) ($organization['name'] ?? ''))) ?>
                <?php endif; ?>
            </span>
            <span class="grow">
                <span class="manage-sidebar__brand-name truncate">
                    <?= e($organization['name'] ?? '') ?>
                </span><br>
                <span class="manage-sidebar__brand-role"><?= e($roleLabel) ?></span>
            </span>
        </div>

        <?= $renderNav($sections, $manageSection) ?>

        <div class="manage-sidebar__footer">
            <div class="manage-sidebar__group">
                <a class="manage-sidebar__link" href="/agency/<?= e(rawurlencode($slug)) ?>">
                    <span class="manage-sidebar__link-accent" aria-hidden="true"></span>
                    <?= component('primitives/icon', ['name' => 'external', 'size' => 16]) ?>
                    <span>View storefront</span>
                </a>
                <a class="manage-sidebar__link" href="/account">
                    <span class="manage-sidebar__link-accent" aria-hidden="true"></span>
                    <?= component('primitives/icon', ['name' => 'user', 'size' => 16]) ?>
                    <span>Your account</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="manage-body">
        <header class="manage-topbar">
            <div class="manage-mobile-nav">
                <button type="button" class="icon-btn icon-btn--bordered"
                        data-drawer-open="manage-nav-drawer" aria-expanded="false"
                        aria-label="Open organization navigation">
                    <?= component('primitives/icon', ['name' => 'menu']) ?>
                </button>
            </div>

            <div class="grow" style="min-width: 0;">
                <p class="manage-topbar__title truncate"><?= e($title ?? 'Dashboard') ?></p>
                <p class="manage-topbar__meta truncate"><?= e($organization['name'] ?? '') ?></p>
            </div>

            <div class="manage-topbar__actions">
                <?php if (count($memberships) > 1): ?>
                    <div class="dropdown" data-dropdown>
                        <button type="button" class="btn btn--secondary btn--sm" data-dropdown-toggle>
                            Switch
                            <?= component('primitives/icon', ['name' => 'chevron-down', 'size' => 14]) ?>
                        </button>
                        <div class="dropdown__menu" role="menu">
                            <p class="dropdown__label">Your organizations</p>
                            <?php foreach ($memberships as $membership): ?>
                                <a class="dropdown__item" href="/manage/<?= e($membership['slug']) ?>" role="menuitem">
                                    <?= component('primitives/icon', ['name' => 'building', 'size' => 16]) ?>
                                    <span class="truncate"><?= e($membership['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($currentUser !== null): ?>
                    <?= component('primitives/avatar', [
                        'imageUrl' => $currentUser['google_avatar_url'] ?? null,
                        'name'     => (string) $currentUser['name'],
                        'size'     => 'sm',
                    ]) ?>
                <?php endif; ?>
            </div>
        </header>

        <main class="manage-content" id="main">
            <?= component('feedback/flash', ['flashMessages' => $flashMessages]) ?>
            <?= $content ?>
        </main>
    </div>
</div>

<div class="drawer" id="manage-nav-drawer" hidden role="dialog" aria-modal="true"
     aria-label="Organization navigation" data-close-on-desktop>
    <div class="drawer__backdrop" data-overlay-dismiss></div>
    <div class="drawer__panel" data-overlay-panel style="background: var(--color-surface-inverse);">
        <div class="drawer__header" style="border-color: rgba(255,255,255,0.1);">
            <p class="drawer__title" style="color: #fff;"><?= e($organization['name'] ?? '') ?></p>
            <button type="button" class="icon-btn" data-drawer-close aria-label="Close navigation"
                    style="color: rgba(255,255,255,0.7);">
                <?= component('primitives/icon', ['name' => 'x']) ?>
            </button>
        </div>
        <div class="drawer__body manage-sidebar" style="display: flex; padding: var(--space-4);">
            <?= $renderNav($sections, $manageSection) ?>
        </div>
    </div>
</div>
<?php
echo component('layout/document', [
    'body'                    => (string) ob_get_clean(),
    'title'                   => $title ?? 'Manage',
    'metaDescription'         => 'Rentivo organization management.',
    'appName'                 => $appName,
    'accentColor'             => $context?->accentColor(),
    'includeManagementStyles' => true,
]);
