<?php
/**
 * Customer record as seen by one organization.
 *
 * Internal notes recorded here are private to this organization and are never
 * rendered anywhere the customer can see.
 *
 * @var \Rentivo\Security\OrganizationContext $context
 * @var array  $customer, $summary, $bookings, $documents
 * @var string $orgSlug
 */

use Rentivo\Security\Permissions;
use Rentivo\Services\BookingStatus;
use Rentivo\Services\DocumentService;
use Rentivo\Support\Currency;

$customer = $customer ?? [];
$summary = $summary ?? [];
$bookings = $bookings ?? [];
$documents = $documents ?? [];
$orgSlug = $orgSlug ?? '';
$base = '/manage/' . rawurlencode($orgSlug);
?>

<?= component('navigation/breadcrumbs', ['items' => [
    ['label' => 'Customers', 'href' => $base . '/customers'],
    ['label' => (string) ($customer['name'] ?? '')],
]]) ?>

<div class="page-header">
    <div class="page-header__heading">
        <div class="row" style="gap: var(--space-4);">
            <?= component('primitives/avatar', [
                'imageUrl' => $customer['google_avatar_url'] ?? null,
                'name'     => (string) ($customer['name'] ?? ''),
                'size'     => 'lg',
            ]) ?>
            <div style="min-width: 0;">
                <h1 class="page-header__title"><?= e($customer['name'] ?? '') ?></h1>
                <p class="page-header__description" style="overflow-wrap: anywhere;">
                    <?= e($customer['email'] ?? '') ?>
                    <?php if (($customer['phone'] ?? null) !== null): ?>
                        · <?= e($customer['phone']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom: var(--space-7);">
    <?= component('dashboard/metric-card', [
        'label' => 'Total bookings',
        'value' => (string) (int) ($summary['total'] ?? 0),
        'icon'  => 'calendar',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Active',
        'value' => (string) (int) ($summary['active'] ?? 0),
        'icon'  => 'key',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Completed',
        'value' => (string) (int) ($summary['completed'] ?? 0),
        'icon'  => 'check-circle',
    ]) ?>
    <?= component('dashboard/metric-card', [
        'label' => 'Cancelled',
        'value' => (string) (int) ($summary['cancelled'] ?? 0),
        'icon'  => 'x-circle',
    ]) ?>
</div>

<div class="booking-detail">
    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Bookings with you</h2>
            <a class="text-sm text-muted"
               href="<?= e($base) ?>/bookings?user_id=<?= (int) $customer['user_id'] ?>">View all</a>
        </div>

        <div class="card__body">
            <?php if ($bookings === []): ?>
                <?= component('feedback/empty-state', [
                    'title'       => 'No bookings',
                    'description' => 'This customer has no bookings with your organization.',
                    'icon'        => 'calendar',
                    'flush'       => true,
                ]) ?>
            <?php else: ?>
                <div class="stack">
                    <?php foreach ($bookings as $booking): ?>
                        <?= component('bookings/booking-card', [
                            'booking' => $booking,
                            'href'    => $base . '/bookings/' . rawurlencode((string) $booking['reference']),
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="booking-detail__aside">
        <div class="card card--padded">
            <h2 class="card__title" style="margin-bottom: var(--space-4);">Profile</h2>
            <dl class="detail-list detail-list--rows">
                <div class="detail-list__item">
                    <dt class="detail-list__label">Phone</dt>
                    <dd class="detail-list__value"><?= e($customer['phone'] ?? '—') ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Nationality</dt>
                    <dd class="detail-list__value"><?= e($customer['nationality'] ?? '—') ?></dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Date of birth</dt>
                    <dd class="detail-list__value">
                        <?= ($customer['date_of_birth'] ?? null) !== null
                            ? e(date_display((string) $customer['date_of_birth'] . ' 00:00:00'))
                            : '—' ?>
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt class="detail-list__label">Customer since</dt>
                    <dd class="detail-list__value"><?= e(date_display((string) $customer['created_at'])) ?></dd>
                </div>
            </dl>
        </div>

        <?php if ($context->can(Permissions::DOCUMENTS_VIEW)): ?>
            <div class="card card--padded">
                <h2 class="card__title" style="margin-bottom: var(--space-4);">Documents</h2>

                <?php if ($documents === []): ?>
                    <p class="text-sm text-muted">No documents uploaded.</p>
                <?php else: ?>
                    <div class="stack-sm stack">
                        <?php foreach ($documents as $document): ?>
                            <div class="row row-between" style="gap: var(--space-3);">
                                <div style="min-width: 0;">
                                    <p class="text-sm text-strong">
                                        <?= e(DocumentService::typeLabel((string) $document['document_type'])) ?>
                                    </p>
                                    <?= component('primitives/badge', [
                                        'label' => ucfirst((string) ($document['review_status'] ?? 'pending')),
                                        'tone'  => DocumentService::statusTone(
                                            (string) ($document['review_status'] ?? 'pending')
                                        ),
                                        'small' => true,
                                    ]) ?>
                                </div>
                                <a class="btn btn--secondary btn--sm"
                                   href="/documents/<?= (int) $document['id'] ?>/file"
                                   target="_blank" rel="noopener">View</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form class="card card--padded" method="post"
              action="<?= e($base) ?>/customers/<?= (int) $customer['id'] ?>/notes">
            <?= csrf_field() ?>
            <h2 class="card__title" style="margin-bottom: var(--space-2);">Internal notes</h2>
            <p class="text-xs text-muted" style="margin-bottom: var(--space-4);">
                Private to your organization. The customer never sees these.
            </p>

            <label class="visually-hidden" for="customer-notes">Internal notes</label>
            <textarea class="textarea" id="customer-notes" name="internal_notes" rows="5"
                      <?= $context->can(Permissions::CUSTOMERS_EDIT_NOTES) ? '' : 'readonly' ?>
                      placeholder="Notes for your team"><?= e($customer['internal_notes'] ?? '') ?></textarea>

            <?php if ($context->can(Permissions::CUSTOMERS_EDIT_NOTES)): ?>
                <div style="margin-top: var(--space-3);">
                    <?= component('primitives/button', [
                        'label'   => 'Save notes',
                        'variant' => 'secondary',
                        'size'    => 'sm',
                    ]) ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
