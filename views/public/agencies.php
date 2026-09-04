<?php
/**
 * Public agency directory.
 *
 * @var array                       $agencies
 * @var int                         $total
 * @var string                      $search
 * @var \Rentivo\Support\Pagination $pagination
 */

$agencies = $agencies ?? [];
$total = (int) ($total ?? 0);
$search = $search ?? '';
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Marketplace</p>
        <h1 class="page-header__title">Rental agencies</h1>
        <p class="page-header__description">
            Every agency operating on Rentivo, with its own fleet, locations and
            rental terms.
        </p>
    </div>
</div>

<form method="get" action="/agencies" class="table-toolbar">
    <div class="table-toolbar__search">
        <?= component('forms/search-field', [
            'name'        => 'q',
            'value'       => $search,
            'placeholder' => 'Search agencies by name or area',
            'label'       => 'Search agencies',
        ]) ?>
    </div>
    <?= component('primitives/button', ['label' => 'Search', 'icon' => 'search']) ?>
    <?php if ($search !== ''): ?>
        <?= component('primitives/button', [
            'label'   => 'Clear',
            'href'    => '/agencies',
            'variant' => 'ghost',
        ]) ?>
    <?php endif; ?>
</form>

<p class="text-sm text-muted" style="margin-bottom: var(--space-5);">
    <strong class="text-strong"><?= number_format($total) ?></strong>
    <?= $total === 1 ? 'agency' : 'agencies' ?>
</p>

<?php if ($agencies === []): ?>
    <?= component('feedback/empty-state', [
        'title'       => $search === '' ? 'No agencies yet' : 'No agencies match that search',
        'description' => $search === ''
            ? 'Agencies that join Rentivo will be listed here.'
            : 'Try a different name or clear the search.',
        'icon'        => 'building',
        'actions'     => $search === ''
            ? [['label' => 'List your agency', 'href' => '/organizations/create']]
            : [['label' => 'Clear search', 'href' => '/agencies', 'variant' => 'secondary']],
    ]) ?>
<?php else: ?>
    <div class="agency-grid">
        <?php foreach ($agencies as $agency): ?>
            <?= component('organizations/agency-card', ['agency' => $agency]) ?>
        <?php endforeach; ?>
    </div>

    <?= component('navigation/pagination', [
        'pagination' => $pagination,
        'path'       => '/agencies',
        'query'      => $search === '' ? [] : ['q' => $search],
    ]) ?>
<?php endif; ?>
