<?php
/**
 * Fleet list.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array $cars, $filters, $statusCounts, $categories, $statuses
 * @var int   $total
 * @var \Rentivo\Support\Pagination $pagination
 * @var string $orgSlug
 */

use Rentivo\Security\Permissions;
use Rentivo\Services\CarService;
use Rentivo\Support\Currency;

$cars = $cars ?? [];
$filters = $filters ?? [];
$statusCounts = $statusCounts ?? [];
$categories = $categories ?? [];
$total = (int) ($total ?? 0);
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$query = array_filter([
    'q'           => $filters['search'] ?? null,
    'status'      => $filters['status'] ?? null,
    'category_id' => ($filters['category_id'] ?? 0) ?: null,
    'archived'    => ($filters['include_archived'] ?? false) ? '1' : null,
]);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Fleet</p>
        <h1 class="page-header__title">Cars</h1>
        <p class="page-header__description">
            <?= number_format($total) ?> <?= $total === 1 ? 'vehicle' : 'vehicles' ?>
            <?= ($filters['include_archived'] ?? false) ? ' including archived' : '' ?>
        </p>
    </div>
    <?php if ($context->can(Permissions::CARS_CREATE)): ?>
        <div class="page-header__actions">
            <?= component('primitives/button', [
                'label' => 'Add a car',
                'href'  => $base . '/cars/create',
                'icon'  => 'plus',
            ]) ?>
        </div>
    <?php endif; ?>
</div>

<form class="table-toolbar" method="get" action="<?= e($base) ?>/cars" data-filter-form>
    <div class="table-toolbar__search">
        <?= component('forms/search-field', [
            'name'        => 'q',
            'value'       => (string) ($filters['search'] ?? ''),
            'placeholder' => 'Search brand, model or plate',
            'label'       => 'Search fleet',
            'attributes'  => ['data-filter-debounce' => true],
        ]) ?>
    </div>

    <div class="table-toolbar__filters">
        <label class="visually-hidden" for="fleet-status">Filter by status</label>
        <select class="select" id="fleet-status" name="status">
            <option value="">All statuses</option>
            <?php foreach (($statuses ?? CarService::statuses()) as $status): ?>
                <option value="<?= e($status) ?>"
                    <?= ($filters['status'] ?? null) === $status ? 'selected' : '' ?>>
                    <?= e(CarService::statusLabel($status)) ?>
                    (<?= (int) ($statusCounts[$status] ?? 0) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($categories !== []): ?>
            <label class="visually-hidden" for="fleet-category">Filter by category</label>
            <select class="select" id="fleet-category" name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= (int) ($filters['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?= component('forms/checkbox', [
            'name'    => 'archived',
            'value'   => '1',
            'label'   => 'Include archived',
            'checked' => (bool) ($filters['include_archived'] ?? false),
        ]) ?>

        <noscript><button type="submit" class="btn btn--secondary btn--sm">Filter</button></noscript>
    </div>
</form>

<?php if ($cars === []): ?>
    <?= component('feedback/empty-state', [
        'title'       => 'No cars match',
        'description' => 'Adjust the filters, or add your first vehicle to start renting.',
        'icon'        => 'car',
        'actions'     => $context->can(Permissions::CARS_CREATE)
            ? [['label' => 'Add a car', 'href' => $base . '/cars/create']]
            : [],
    ]) ?>
<?php else: ?>
    <div class="table-wrap table-wrap--stack">
        <table class="data-table data-table--stack">
            <caption class="visually-hidden">Fleet vehicles</caption>
            <thead>
                <tr>
                    <th scope="col">Vehicle</th>
                    <th scope="col">Category</th>
                    <th scope="col">Location</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="data-table__numeric">Daily rate</th>
                    <th scope="col" class="data-table__actions">
                        <span class="visually-hidden">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cars as $car): ?>
                    <tr>
                        <td data-label="Vehicle">
                            <div class="data-table__cell-stack">
                                <span class="avatar avatar--square" aria-hidden="true">
                                    <?php if (($car['primary_image'] ?? null) !== null): ?>
                                        <img src="<?= e('/uploads/' . ltrim((string) $car['primary_image'], '/')) ?>"
                                             alt="" loading="lazy">
                                    <?php else: ?>
                                        <?= component('primitives/icon', ['name' => 'car', 'size' => 16]) ?>
                                    <?php endif; ?>
                                </span>
                                <span style="min-width: 0;">
                                    <a class="data-table__link"
                                       href="<?= e($base) ?>/cars/<?= (int) $car['id'] ?>/edit">
                                        <?= e(trim($car['brand'] . ' ' . $car['model'])) ?>
                                    </a>
                                    <span class="data-table__secondary">
                                        <?= e($car['year']) ?>
                                        <?php if (($car['plate_number'] ?? null) !== null): ?>
                                            · <?= e($car['plate_number']) ?>
                                        <?php endif; ?>
                                        <?php if (($car['archived_at'] ?? null) !== null): ?>
                                            · Archived
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td data-label="Category"><?= e($car['category_name'] ?? '—') ?></td>
                        <td data-label="Location"><?= e($car['location_name'] ?? '—') ?></td>
                        <td data-label="Status">
                            <?= component('cars/car-status-badge', [
                                'status' => (string) $car['status'],
                                'small'  => true,
                            ]) ?>
                        </td>
                        <td data-label="Daily rate" class="data-table__numeric">
                            <?= e(Currency::format((int) $car['daily_rate_fils'])) ?>
                        </td>
                        <td data-label="" class="data-table__actions">
                            <a class="btn btn--secondary btn--sm"
                               href="<?= e($base) ?>/cars/<?= (int) $car['id'] ?>/edit">
                                <?= $context->can(Permissions::CARS_EDIT) ? 'Edit' : 'View' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= component('navigation/pagination', [
        'pagination' => $pagination,
        'path'       => $base . '/cars',
        'query'      => $query,
    ]) ?>
<?php endif; ?>
