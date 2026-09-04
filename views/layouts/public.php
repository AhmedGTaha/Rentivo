<?php
/**
 * Public application shell.
 *
 * Used by the marketplace, authentication pages and error pages.
 *
 * @var string      $content
 * @var string      $title
 * @var string|null $metaDescription
 * @var array       $flashMessages
 * @var array|null  $currentUser
 * @var int         $unreadCount
 * @var array       $memberships
 * @var string      $currentPath
 * @var bool        $isLocal
 * @var string|null $accentColor  Organization accent for storefront pages
 * @var bool        $fullWidth    Page manages its own containers
 */

$content = $content ?? '';
$flashMessages = $flashMessages ?? [];
$currentUser = $currentUser ?? null;
$unreadCount = (int) ($unreadCount ?? 0);
$memberships = $memberships ?? [];
$currentPath = $currentPath ?? '/';
$isLocal = $isLocal ?? false;
$appName = $appName ?? 'Rentivo';
$fullWidth = $fullWidth ?? false;

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
        <?php if ($fullWidth): ?>
            <?php if ($flashMessages !== []): ?>
                <div class="container" style="padding-top: var(--space-6);">
                    <?= component('feedback/flash', ['flashMessages' => $flashMessages]) ?>
                </div>
            <?php endif; ?>
            <?= $content ?>
        <?php else: ?>
            <div class="container" style="padding-top: var(--space-7); padding-bottom: var(--space-11);">
                <?= component('feedback/flash', ['flashMessages' => $flashMessages]) ?>
                <?= $content ?>
            </div>
        <?php endif; ?>
    </main>

    <?= component('layout/public-footer', ['appName' => $appName, 'isLocal' => $isLocal]) ?>
</div>
<?php
echo component('layout/document', [
    'body'            => (string) ob_get_clean(),
    'title'           => $title ?? 'Rentivo',
    'metaDescription' => $metaDescription ?? null,
    'appName'         => $appName,
    'accentColor'     => $accentColor ?? null,
]);
