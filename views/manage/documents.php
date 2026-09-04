<?php
/**
 * Document review queue.
 *
 * The queue is confined by an inner join to organization_customers, so it can
 * only ever contain documents belonging to this organization's own customers.
 * A decision here is scoped to (organization, document) and has no effect on
 * how any other agency sees the same file.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array       $documents, $statusCounts, $statuses
 * @var string|null $status
 * @var string      $search
 * @var int         $total
 * @var \Rentivo\Support\Pagination $pagination
 * @var string      $orgSlug
 */

use Rentivo\Security\Permissions;
use Rentivo\Services\DocumentService;

$documents = $documents ?? [];
$statusCounts = $statusCounts ?? [];
$statuses = $statuses ?? DocumentService::STATUSES;
$status = $status ?? null;
$search = $search ?? '';
$total = (int) ($total ?? 0);
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);

$canVerify = $context->can(Permissions::DOCUMENTS_VERIFY);
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Compliance</p>
        <h1 class="page-header__title">Documents</h1>
        <p class="page-header__description">
            Verify identity documents for customers who book with you. Your decision
            applies only to your organization.
        </p>
    </div>
</div>

<div class="fleet-filters">
    <a class="pill<?= $status === null ? ' is-active' : '' ?>" href="<?= e($base) ?>/documents">All</a>
    <?php foreach ($statuses as $option): ?>
        <a class="pill<?= $status === $option ? ' is-active' : '' ?>"
           href="<?= e($base) ?>/documents?status=<?= e($option) ?>">
            <?= e(ucfirst($option)) ?>
            <span class="pill__count"><?= (int) ($statusCounts[$option] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<form class="table-toolbar" method="get" action="<?= e($base) ?>/documents">
    <?php if ($status !== null): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
    <?php endif; ?>
    <div class="table-toolbar__search">
        <?= component('forms/search-field', [
            'name'        => 'q',
            'value'       => $search,
            'placeholder' => 'Search by customer name or email',
            'label'       => 'Search documents',
        ]) ?>
    </div>
    <?= component('primitives/button', ['label' => 'Search', 'icon' => 'search']) ?>
</form>

<?php if ($documents === []): ?>
    <?= component('feedback/empty-state', [
        'title'       => 'Nothing to review',
        'description' => 'Documents appear here once your customers upload them.',
        'icon'        => 'file-text',
    ]) ?>
<?php else: ?>
    <div class="stack">
        <?php foreach ($documents as $document): ?>
            <?php $reviewStatus = (string) ($document['review_status'] ?? 'pending'); ?>
            <article class="card card--padded">
                <div class="row row-wrap row-between" style="gap: var(--space-5); align-items: flex-start;">
                    <div class="row" style="gap: var(--space-4); min-width: 0; flex: 1 1 260px;">
                        <?= component('primitives/avatar', [
                            'imageUrl' => $document['customer_avatar'] ?? null,
                            'name'     => (string) ($document['customer_name'] ?? ''),
                        ]) ?>
                        <div style="min-width: 0;">
                            <p class="text-strong"><?= e($document['customer_name'] ?? '') ?></p>
                            <p class="text-xs text-muted" style="overflow-wrap: anywhere;">
                                <?= e($document['customer_email'] ?? '') ?>
                            </p>
                            <p class="text-xs text-muted" style="margin-top: var(--space-2);">
                                <?= e(DocumentService::typeLabel((string) $document['document_type'])) ?>
                                · uploaded <?= e(date_display((string) $document['created_at'])) ?>
                                <?php if (($document['expires_at'] ?? null) !== null): ?>
                                    · expires <?= e(date_display((string) $document['expires_at'] . ' 00:00:00')) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="row" style="gap: var(--space-3);">
                        <?= component('primitives/badge', [
                            'label' => ucfirst($reviewStatus),
                            'tone'  => DocumentService::statusTone($reviewStatus),
                        ]) ?>
                        <a class="btn btn--secondary btn--sm"
                           href="/documents/<?= (int) $document['id'] ?>/file"
                           target="_blank" rel="noopener">
                            <?= component('primitives/icon', ['name' => 'external', 'size' => 14]) ?>
                            Open document
                        </a>
                    </div>
                </div>

                <?php if ($reviewStatus === 'rejected' && ($document['rejection_reason'] ?? null) !== null): ?>
                    <p class="text-xs" style="margin-top: var(--space-4); color: var(--color-danger);">
                        Rejected: <?= e($document['rejection_reason']) ?>
                    </p>
                <?php endif; ?>

                <?php if ($canVerify): ?>
                    <div class="row row-wrap" style="gap: var(--space-3); margin-top: var(--space-5);
                                padding-top: var(--space-5); border-top: var(--border);">
                        <form method="post" action="<?= e($base) ?>/documents/<?= (int) $document['id'] ?>/review">
                            <?= csrf_field() ?>
                            <input type="hidden" name="decision" value="verified">
                            <?= component('primitives/button', [
                                'label' => 'Verify',
                                'size'  => 'sm',
                                'icon'  => 'check',
                            ]) ?>
                        </form>

                        <details style="flex: 1 1 260px;">
                            <summary class="btn btn--danger btn--sm">Reject</summary>
                            <form method="post"
                                  action="<?= e($base) ?>/documents/<?= (int) $document['id'] ?>/review"
                                  style="margin-top: var(--space-3);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="rejected">
                                <label class="visually-hidden"
                                       for="reject-<?= (int) $document['id'] ?>">Reason for rejecting</label>
                                <textarea class="textarea" id="reject-<?= (int) $document['id'] ?>"
                                          name="rejection_reason" rows="2" required
                                          placeholder="Why can this document not be accepted?"></textarea>
                                <div style="margin-top: var(--space-3);">
                                    <?= component('primitives/button', [
                                        'label'   => 'Reject document',
                                        'variant' => 'danger',
                                        'size'    => 'sm',
                                    ]) ?>
                                </div>
                            </form>
                        </details>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?= component('navigation/pagination', [
        'pagination' => $pagination,
        'path'       => $base . '/documents',
        'query'      => array_filter(['status' => $status, 'q' => $search]),
    ]) ?>
<?php endif; ?>
