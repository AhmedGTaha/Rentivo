<?php
/**
 * Pagination.
 *
 * Page state lives in the query string alongside every other filter, so a
 * paginated URL is shareable and back/forward behave naturally.
 *
 * @var \Rentivo\Support\Pagination $pagination
 * @var string $path   Base path, e.g. "/cars"
 * @var array  $query  Current query parameters to preserve
 */

use Rentivo\Support\Pagination;

/** @var Pagination $pagination */
$pagination = $pagination ?? null;
$path = $path ?? '/';
$query = $query ?? [];

if ($pagination === null || !$pagination->hasPages()) {
    return;
}
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($pagination->page > 1): ?>
        <a class="pagination__link" href="<?= e(Pagination::url($path, $query, $pagination->page - 1)) ?>"
           rel="prev" aria-label="Previous page">
            <?= component('primitives/icon', ['name' => 'chevron-left', 'size' => 16]) ?>
        </a>
    <?php else: ?>
        <span class="pagination__link" aria-disabled="true" aria-hidden="true">
            <?= component('primitives/icon', ['name' => 'chevron-left', 'size' => 16]) ?>
        </span>
    <?php endif; ?>

    <?php foreach ($pagination->window() as $page): ?>
        <?php if ($page === 0): ?>
            <span class="pagination__gap" aria-hidden="true">…</span>
        <?php elseif ($page === $pagination->page): ?>
            <a class="pagination__link" href="<?= e(Pagination::url($path, $query, $page)) ?>"
               aria-current="page"><?= (int) $page ?></a>
        <?php else: ?>
            <a class="pagination__link" href="<?= e(Pagination::url($path, $query, $page)) ?>"
               aria-label="Page <?= (int) $page ?>"><?= (int) $page ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($pagination->page < $pagination->lastPage): ?>
        <a class="pagination__link" href="<?= e(Pagination::url($path, $query, $pagination->page + 1)) ?>"
           rel="next" aria-label="Next page">
            <?= component('primitives/icon', ['name' => 'chevron-right', 'size' => 16]) ?>
        </a>
    <?php else: ?>
        <span class="pagination__link" aria-disabled="true" aria-hidden="true">
            <?= component('primitives/icon', ['name' => 'chevron-right', 'size' => 16]) ?>
        </span>
    <?php endif; ?>

    <p class="pagination__summary">
        Showing <?= (int) $pagination->from() ?>–<?= (int) $pagination->to() ?>
        of <?= number_format($pagination->total) ?>
    </p>
</nav>
