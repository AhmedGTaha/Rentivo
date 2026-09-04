<?php
/**
 * Customer documents.
 *
 * Files are stored outside the public web root; the links here point at the
 * authorizing delivery route, never at a file path.
 *
 * @var array $documents
 * @var array $reviews   document id => list of organization reviews
 * @var array $types
 * @var array $errors
 * @var array $old
 */

use Rentivo\Services\DocumentService;

$documents = $documents ?? [];
$reviews = $reviews ?? [];
$types = $types ?? DocumentService::TYPES;
$errors = $errors ?? [];
$old = $old ?? [];

$typeOptions = [];
foreach ($types as $value => $label) {
    $typeOptions[(string) $value] = (string) $label;
}
?>

<div class="page-header">
    <div class="page-header__heading">
        <p class="eyebrow">Account</p>
        <h1 class="page-header__title">Documents</h1>
        <p class="page-header__description">
            Upload your driving licence and national ID once. Each agency you book
            with reviews them separately — verifying with one agency never shares
            that decision with another.
        </p>
    </div>
</div>

<div class="grid grid-2">
    <div class="stack">
        <?php if ($documents === []): ?>
            <?= component('feedback/empty-state', [
                'title'       => 'No documents uploaded',
                'description' => 'Agencies usually ask for a driving licence before releasing a car.',
                'icon'        => 'file-text',
            ]) ?>
        <?php else: ?>
            <?php foreach ($documents as $document): ?>
                <?php $documentId = (int) $document['id']; ?>
                <article class="document-card">
                    <span class="document-card__icon">
                        <?= component('primitives/icon', ['name' => 'file-text', 'size' => 20]) ?>
                    </span>

                    <div class="document-card__body">
                        <p class="document-card__title">
                            <?= e(DocumentService::typeLabel((string) $document['document_type'])) ?>
                        </p>
                        <p class="document-card__meta">
                            Uploaded <?= e(date_display((string) $document['created_at'])) ?>
                            <?php if (($document['expires_at'] ?? null) !== null): ?>
                                · Expires <?= e(date_display((string) $document['expires_at'] . ' 00:00:00')) ?>
                            <?php endif; ?>
                        </p>

                        <div class="document-card__reviews">
                            <?php $documentReviews = $reviews[$documentId] ?? []; ?>
                            <?php if ($documentReviews === []): ?>
                                <span class="text-xs text-muted">
                                    No agency has reviewed this yet.
                                </span>
                            <?php else: ?>
                                <?php foreach ($documentReviews as $review): ?>
                                    <?= component('primitives/badge', [
                                        'label' => $review['organization_name'] . ': '
                                            . ucfirst((string) $review['status']),
                                        'tone'  => DocumentService::statusTone((string) $review['status']),
                                        'small' => true,
                                    ]) ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php foreach (($reviews[$documentId] ?? []) as $review): ?>
                            <?php if ((string) $review['status'] === 'rejected'
                                && ($review['rejection_reason'] ?? null) !== null): ?>
                                <p class="text-xs" style="margin-top: var(--space-2); color: var(--color-danger);">
                                    <?= e($review['organization_name']) ?>:
                                    <?= e($review['rejection_reason']) ?>
                                </p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="document-card__actions">
                        <a class="icon-btn icon-btn--bordered icon-btn--sm"
                           href="/documents/<?= (int) $documentId ?>/file"
                           target="_blank" rel="noopener"
                           aria-label="View this document">
                            <?= component('primitives/icon', ['name' => 'external', 'size' => 16]) ?>
                        </a>

                        <form method="post" action="/account/documents/<?= (int) $documentId ?>/delete"
                              data-confirm="Removing this document also removes every agency review of it."
                              data-confirm-title="Remove this document?"
                              data-confirm-label="Remove"
                              data-confirm-destructive="1">
                            <?= csrf_field() ?>
                            <button type="submit" class="icon-btn icon-btn--bordered icon-btn--sm icon-btn--danger"
                                    aria-label="Remove this document">
                                <?= component('primitives/icon', ['name' => 'trash', 'size' => 16]) ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>
        <form class="card card--padded" method="post" action="/account/documents"
              enctype="multipart/form-data" data-guard-submit>
            <?= csrf_field() ?>

            <h2 class="card__title" style="margin-bottom: var(--space-5);">Upload a document</h2>

            <div class="stack">
                <?= component('forms/field', [
                    'name'     => 'document_type',
                    'label'    => 'Document type',
                    'type'     => 'select',
                    'value'    => $old['document_type'] ?? '',
                    'options'  => ['' => 'Choose a type'] + $typeOptions,
                    'required' => true,
                    'errors'   => $errors,
                    'hint'     => 'Uploading a new file replaces any previous document of the same type.',
                ]) ?>

                <?= component('forms/file-upload', [
                    'name'   => 'document',
                    'title'  => 'Choose a file or drag it here',
                    'hint'   => 'JPEG, PNG, WebP or PDF, up to 10 MB',
                    'accept' => 'image/jpeg,image/png,image/webp,application/pdf',
                ]) ?>

                <?php if (isset($errors['document'])): ?>
                    <p class="field__error">
                        <?= component('primitives/icon', ['name' => 'alert', 'size' => 14]) ?>
                        <span><?= e($errors['document']) ?></span>
                    </p>
                <?php endif; ?>

                <?= component('forms/field', [
                    'name'   => 'expires_at',
                    'label'  => 'Expiry date',
                    'type'   => 'date',
                    'value'  => $old['expires_at'] ?? '',
                    'hint'   => 'We mark verifications as expired once this date passes.',
                    'errors' => $errors,
                ]) ?>

                <?= component('primitives/button', ['label' => 'Upload document', 'block' => true]) ?>
            </div>
        </form>

        <div class="card card--padded stack-sm stack" style="margin-top: var(--space-4);">
            <p class="row text-strong text-sm" style="gap: var(--space-2);">
                <?= component('primitives/icon', ['name' => 'shield', 'size' => 16]) ?>
                How your documents are stored
            </p>
            <p class="text-xs text-muted">
                Files are kept outside the public web directory and have no public
                URL. Only you, and staff at agencies you have booked with who hold
                the document permission, can open them.
            </p>
        </div>
    </div>
</div>
