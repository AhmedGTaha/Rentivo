<?php
/**
 * Link tabs.
 *
 * These are real anchors that reload the page with a different query
 * parameter, which is what the customer bookings and management lists need —
 * the server does the filtering, so the tab state is bookmarkable.
 *
 * @var array  $items   List of ['label' => ..., 'href' => ..., 'count' => ?int, 'active' => bool]
 * @var string $label   Accessible label for the tab list.
 */

$items = $items ?? [];
$label = $label ?? 'Sections';

if ($items === []) {
    return;
}
?>
<nav class="tabs" aria-label="<?= e($label) ?>">
    <?php foreach ($items as $item): ?>
        <a class="tabs__tab" href="<?= e($item['href']) ?>"
           <?= ($item['active'] ?? false) ? 'aria-current="page"' : '' ?>>
            <?= e($item['label']) ?>
            <?php if (($item['count'] ?? null) !== null): ?>
                <span class="tabs__count"><?= (int) $item['count'] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
