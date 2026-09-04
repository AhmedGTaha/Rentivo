<?php
/**
 * Category management.
 *
 * Categories are ordinary database rows scoped to the organization; nothing in
 * the application hard-codes a category list.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array      $categories
 * @var array|null $editing
 * @var array      $errors, $old
 * @var string     $orgSlug
 */

use Rentivo\Security\Permissions;

$categories = $categories ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$old = $old ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$canManage = $context->can(Permissions::CARS_EDIT);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Fleet</p>
        <h1 class="page-header__title">Categories</h1>
        <p class="page-header__description">
            Group your vehicles so customers can filter the marketplace by the kind
            of car they need.
        </p>
    </div>
</div>

<div class="grid grid-2" style="align-items: start;">
    <div>
        <?php if ($categories === []): ?>
            <?= component('feedback/empty-state', [
                'title'       => 'No categories yet',
                'description' => 'Add categories such as Economy, SUV or Luxury.',
                'icon'        => 'star',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap table-wrap--stack">
                <table class="data-table data-table--stack">
                    <caption class="visually-hidden">Car categories</caption>
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col" class="data-table__numeric">Cars</th>
                            <th scope="col" class="data-table__actions">
                                <span class="visually-hidden">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td data-label="Category">
                                    <span class="data-table__primary"><?= e($category['name']) ?></span>
                                    <span class="data-table__secondary"><?= e($category['slug']) ?></span>
                                </td>
                                <td data-label="Cars" class="data-table__numeric">
                                    <?= (int) ($category['car_count'] ?? 0) ?>
                                </td>
                                <td data-label="" class="data-table__actions">
                                    <?php if ($canManage): ?>
                                        <div class="btn-group">
                                            <a class="btn btn--secondary btn--sm"
                                               href="<?= e($base) ?>/categories?edit=<?= (int) $category['id'] ?>">
                                                Rename
                                            </a>
                                            <?php if ((int) ($category['car_count'] ?? 0) === 0): ?>
                                                <form method="post"
                                                      action="<?= e($base) ?>/categories/<?= (int) $category['id'] ?>/delete"
                                                      data-confirm="This category will be removed."
                                                      data-confirm-title="Remove category?"
                                                      data-confirm-label="Remove"
                                                      data-confirm-destructive="1">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn--danger btn--sm">
                                                        Remove
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <form class="card card--padded" method="post"
              action="<?= e($base) ?>/categories<?= $editing !== null ? '/' . (int) $editing['id'] : '' ?>">
            <?= csrf_field() ?>

            <h2 class="card__title" style="margin-bottom: var(--space-5);">
                <?= $editing !== null ? 'Rename category' : 'Add a category' ?>
            </h2>

            <?= component('forms/field', [
                'name'     => 'name',
                'label'    => 'Category name',
                'value'    => $old['name'] ?? ($editing['name'] ?? ''),
                'required' => true,
                'errors'   => $errors,
                'attributes' => ['placeholder' => 'Economy, SUV, Luxury…'],
            ]) ?>

            <div class="form-actions">
                <?= component('primitives/button', [
                    'label' => $editing !== null ? 'Save' : 'Add category',
                ]) ?>
                <?php if ($editing !== null): ?>
                    <?= component('primitives/button', [
                        'label'   => 'Cancel',
                        'href'    => $base . '/categories',
                        'variant' => 'ghost',
                    ]) ?>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>
