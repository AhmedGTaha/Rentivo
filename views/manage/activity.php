<?php
/**
 * Audit activity feed.
 *
 * Entries are immutable: there is no edit or delete anywhere in the UI.
 *
 * @var array    $entries, $filters, $actions
 * @var int      $total
 * @var callable $labeller
 * @var \Rentivo\Support\Pagination $pagination
 * @var string   $orgSlug
 */

use Rentivo\Services\AuditService;

$entries = $entries ?? [];
$filters = $filters ?? [];
$actions = $actions ?? [];
$total = (int) ($total ?? 0);
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$query = array_filter([
    'q'      => $filters['search'] ?? null,
    'action' => ($filters['action'] ?? '') ?: null,
]);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Audit</p>
        <h1 class="page-header__title">Activity</h1>
        <p class="page-header__description">
            An immutable record of every material action taken in your organization.
        </p>
    </div>
</div>

<form class="table-toolbar" method="get" action="<?= e($base) ?>/activity" data-filter-form>
    <div class="table-toolbar__search">
        <?= component('forms/search-field', [
            'name'        => 'q',
            'value'       => (string) ($filters['search'] ?? ''),
            'placeholder' => 'Search by action or person',
            'label'       => 'Search activity',
            'attributes'  => ['data-filter-debounce' => true],
        ]) ?>
    </div>

    <div class="table-toolbar__filters">
        <label class="visually-hidden" for="activity-action">Filter by action</label>
        <select class="select" id="activity-action" name="action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e($action) ?>"
                    <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>>
                    <?= e(AuditService::label($action)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn--secondary btn--sm">Filter</button></noscript>
    </div>
</form>

<?php if ($entries === []): ?>
    <?= component('feedback/empty-state', [
        'title'       => 'No activity recorded',
        'description' => 'Actions taken by you and your team will be logged here.',
        'icon'        => 'activity',
    ]) ?>
<?php else: ?>
    <div class="card card--padded">
        <?php foreach ($entries as $entry): ?>
            <?= component('dashboard/activity-item', [
                'entry'        => $entry,
                'showMetadata' => true,
            ]) ?>
        <?php endforeach; ?>
    </div>

    <p class="table-summary"><?= number_format($total) ?> recorded events</p>

    <?= component('navigation/pagination', [
        'pagination' => $pagination,
        'path'       => $base . '/activity',
        'query'      => $query,
    ]) ?>
<?php endif; ?>
