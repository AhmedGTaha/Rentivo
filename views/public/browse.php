<?php
/**
 * Browse page (/cars and /agency/{slug}/cars).
 *
 * The filter panel is rendered twice from one component — desktop rail and
 * mobile drawer — so the two can never disagree.
 *
 * @var array                            $cars
 * @var int                              $total
 * @var \Rentivo\Repositories\CarFilters $filters
 * @var \Rentivo\Support\Pagination      $pagination
 * @var string                           $basePath
 * @var array|null                       $organization
 * @var array $agencies, $brands, $categories, $locationNames, $favoriteIds
 * @var array $sortLabels, $transmissions, $fuelTypes
 */

use Rentivo\Repositories\CarFilters;

$cars = $cars ?? [];
$total = (int) ($total ?? 0);
$filters = $filters ?? CarFilters::fromQuery([]);
$basePath = $basePath ?? '/cars';
$organization = $organization ?? null;
$favoriteIds = $favoriteIds ?? [];
$sortLabels = $sortLabels ?? CarFilters::sortLabels();

$panelProps = [
    'filters'       => $filters,
    'agencies'      => $agencies ?? [],
    'brands'        => $brands ?? [],
    'categories'    => $categories ?? [],
    'locationNames' => $locationNames ?? [],
    'transmissions' => $transmissions ?? [],
    'fuelTypes'     => $fuelTypes ?? [],
    'basePath'      => $basePath,
];

$activeCount = $filters->activeCount();
$query = $filters->toQuery();
?>

<section class="browse-header">
    <div class="container">
        <?php if ($organization !== null): ?>
            <?= component('navigation/breadcrumbs', ['items' => [
                ['label' => 'Agencies', 'href' => '/agencies'],
                ['label' => (string) $organization['name'], 'href' => '/agency/' . $organization['slug']],
                ['label' => 'Cars'],
            ]]) ?>
        <?php endif; ?>

        <h1 class="browse-header__title">
            <?= $organization === null ? 'Browse cars' : e($organization['name'] . ' fleet') ?>
        </h1>
        <p class="browse-header__lead">
            Search by brand, model or agency, then narrow by price, category and dates.
        </p>

        <form class="browse-search" method="get" action="<?= e($basePath) ?>">
            <?php /* Every non-search filter is carried through the search form. */ ?>
            <?php foreach ($query as $key => $value): ?>
                <?php if ($key === 'q' || $key === 'page') { continue; } ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
            <?php endforeach; ?>

            <div class="browse-search__field">
                <?= component('forms/search-field', [
                    'name'        => 'q',
                    'value'       => $filters->search,
                    'placeholder' => 'Search by brand, model or agency',
                    'label'       => 'Search cars',
                ]) ?>
            </div>

            <?= component('primitives/button', ['label' => 'Search', 'icon' => 'search']) ?>
        </form>
    </div>
</section>

<div class="container browse-layout">
    <aside class="browse-rail" aria-label="Filters">
        <div class="filter-panel__header">
            <p class="filter-panel__title">Filters</p>
            <?php if ($activeCount > 0): ?>
                <a class="text-xs text-muted" href="<?= e($basePath) ?>">Clear all</a>
            <?php endif; ?>
        </div>
        <?= component('cars/car-filter-panel', $panelProps + ['idPrefix' => 'rail']) ?>
    </aside>

    <div class="browse-results">
        <div class="browse-toolbar">
            <p class="browse-toolbar__count">
                <strong><?= number_format($total) ?></strong>
                <?= $total === 1 ? 'car' : 'cars' ?> available
            </p>

            <div class="browse-toolbar__controls">
                <button type="button" class="btn btn--secondary btn--sm browse-toolbar__filter-btn"
                        data-drawer-open="filters-drawer" aria-expanded="false">
                    <?= component('primitives/icon', ['name' => 'filter', 'size' => 15]) ?>
                    Filters
                    <?php if ($activeCount > 0): ?>
                        <span class="browse-toolbar__filter-count"><?= (int) $activeCount ?></span>
                    <?php endif; ?>
                </button>

                <form method="get" action="<?= e($basePath) ?>" data-filter-form>
                    <?php foreach ($query as $key => $value): ?>
                        <?php if ($key === 'sort' || $key === 'page') { continue; } ?>
                        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="page" value="1">

                    <label class="visually-hidden" for="browse-sort">Sort results</label>
                    <select class="select" id="browse-sort" name="sort">
                        <?php foreach ($sortLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>"
                                <?= $filters->sort === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript>
                        <button type="submit" class="btn btn--secondary btn--sm">Sort</button>
                    </noscript>
                </form>
            </div>
        </div>

        <?php if ($filters->hasDateWindow()): ?>
            <p class="availability-note">
                <?= component('primitives/icon', [
                    'name'  => 'calendar',
                    'class' => 'availability-note__icon',
                    'size'  => 18,
                ]) ?>
                <span>
                    Showing cars free from
                    <strong><?= e(datetime_display(\Rentivo\Support\DateTimeHelper::toDb($filters->pickupAt))) ?></strong>
                    to
                    <strong><?= e(datetime_display(\Rentivo\Support\DateTimeHelper::toDb($filters->returnAt))) ?></strong>.
                </span>
                <a class="text-xs" href="<?= e($basePath . query_string($filters->toQuery([
                    'pickup_at' => '',
                    'return_at' => '',
                ]))) ?>">Clear dates</a>
            </p>
        <?php endif; ?>

        <?= component('cars/car-grid', [
            'cars'        => $cars,
            'favoriteIds' => $favoriteIds,
            'showAgency'  => $organization === null,
            'emptyState'  => [
                'title'       => 'No cars match those filters',
                'description' => $activeCount > 0
                    ? 'Try widening your price range, clearing the dates, or removing a filter.'
                    : 'There are no published vehicles here yet. Please check back soon.',
                'icon'        => 'car',
                'actions'     => $activeCount > 0
                    ? [['label' => 'Clear all filters', 'href' => $basePath, 'variant' => 'secondary']]
                    : [],
            ],
        ]) ?>

        <?= component('navigation/pagination', [
            'pagination' => $pagination,
            'path'       => $basePath,
            'query'      => $query,
        ]) ?>
    </div>
</div>

<?php /* Mobile filters: the same panel component inside a drawer. */ ?>
<div class="drawer" id="filters-drawer" hidden role="dialog" aria-modal="true"
     aria-label="Filter cars" data-close-on-desktop>
    <div class="drawer__backdrop" data-overlay-dismiss></div>
    <div class="drawer__panel" data-overlay-panel>
        <div class="drawer__header">
            <p class="drawer__title">Filters</p>
            <button type="button" class="icon-btn" data-drawer-close aria-label="Close filters">
                <?= component('primitives/icon', ['name' => 'x']) ?>
            </button>
        </div>
        <div class="drawer__body">
            <?= component('cars/car-filter-panel', $panelProps + ['idPrefix' => 'drawer']) ?>
        </div>
    </div>
</div>
