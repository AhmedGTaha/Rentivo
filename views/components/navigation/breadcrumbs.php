<?php
/**
 * Breadcrumbs.
 *
 * @var array $items List of ['label' => ..., 'href' => ...|null]
 */

$items = $items ?? [];

if ($items === []) {
    return;
}

$last = count($items) - 1;
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol class="breadcrumbs" style="margin: 0;">
        <?php foreach ($items as $index => $item): ?>
            <li class="breadcrumbs__item">
                <?php if (($item['href'] ?? null) !== null && $index !== $last): ?>
                    <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= e($item['label']) ?></span>
                <?php endif; ?>

                <?php if ($index !== $last): ?>
                    <span class="breadcrumbs__separator" aria-hidden="true">/</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
