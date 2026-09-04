<?php
/**
 * Organization customer list.
 *
 * Only customers who have actually transacted with this organization appear
 * here — the relationship is created by their first booking.
 *
 * @var array  $customers
 * @var int    $total
 * @var string $search
 * @var \Rentivo\Support\Pagination $pagination
 * @var string $orgSlug
 */

$customers = $customers ?? [];
$total = (int) ($total ?? 0);
$search = $search ?? '';
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Relationships</p>
        <h1 class="page-header__title">Customers</h1>
        <p class="page-header__description">
            <?= number_format($total) ?> <?= $total === 1 ? 'customer has' : 'customers have' ?>
            booked with you.
        </p>
    </div>
</div>

<form class="table-toolbar" method="get" action="<?= e($base) ?>/customers">
    <div class="table-toolbar__search">
        <?= component('forms/search-field', [
            'name'        => 'q',
            'value'       => $search,
            'placeholder' => 'Search by name, email or phone',
            'label'       => 'Search customers',
        ]) ?>
    </div>
    <?= component('primitives/button', ['label' => 'Search', 'icon' => 'search']) ?>
</form>

<?php if ($customers === []): ?>
    <?= component('feedback/empty-state', [
        'title'       => $search === '' ? 'No customers yet' : 'No customers match',
        'description' => $search === ''
            ? 'A customer record is created automatically when someone books with you.'
            : 'Try a different name, email or phone number.',
        'icon'        => 'users',
    ]) ?>
<?php else: ?>
    <div class="table-wrap table-wrap--stack">
        <table class="data-table data-table--stack">
            <caption class="visually-hidden">Organization customers</caption>
            <thead>
                <tr>
                    <th scope="col">Customer</th>
                    <th scope="col">Phone</th>
                    <th scope="col" class="data-table__numeric">Bookings</th>
                    <th scope="col" class="data-table__numeric">Active</th>
                    <th scope="col" class="data-table__actions">
                        <span class="visually-hidden">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td data-label="Customer">
                            <div class="data-table__cell-stack">
                                <?= component('primitives/avatar', [
                                    'imageUrl' => $customer['google_avatar_url'] ?? null,
                                    'name'     => (string) $customer['name'],
                                    'size'     => 'sm',
                                ]) ?>
                                <span style="min-width: 0;">
                                    <a class="data-table__link"
                                       href="<?= e($base) ?>/customers/<?= (int) $customer['id'] ?>">
                                        <?= e($customer['name']) ?>
                                    </a>
                                    <span class="data-table__secondary"><?= e($customer['email']) ?></span>
                                </span>
                            </div>
                        </td>
                        <td data-label="Phone"><?= e($customer['phone'] ?? '—') ?></td>
                        <td data-label="Bookings" class="data-table__numeric">
                            <?= (int) ($customer['total_bookings'] ?? 0) ?>
                        </td>
                        <td data-label="Active" class="data-table__numeric">
                            <?= (int) ($customer['active_bookings'] ?? 0) ?>
                        </td>
                        <td data-label="" class="data-table__actions">
                            <a class="btn btn--secondary btn--sm"
                               href="<?= e($base) ?>/customers/<?= (int) $customer['id'] ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= component('navigation/pagination', [
        'pagination' => $pagination,
        'path'       => $base . '/customers',
        'query'      => $search === '' ? [] : ['q' => $search],
    ]) ?>
<?php endif; ?>
