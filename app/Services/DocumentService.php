<?php

declare(strict_types=1);

namespace Rentivo\Services;

use Rentivo\Database\Connection;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Repositories\OrganizationUserRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;

/**
 * Private customer documents and per-organization review of them.
 *
 * Documents live under storage/private and are never reachable by URL. The
 * only way to read one is canView(), which encodes the three allowed viewer
 * cases from the SRS:
 *
 *   1. the document's owner
 *   2. an admin of an organization the owner is a customer of
 *   3. an employee with documents.view in such an organization
 */
final class DocumentService
{
    public const TYPES = [
        'driving_license' => 'Driving licence',
        'national_id'     => 'National ID',
    ];

    public const STATUSES = ['pending', 'verified', 'rejected', 'expired'];

    public function __construct(
        private Connection $db,
        private DocumentRepository $documents,
        private OrganizationCustomerRepository $organizationCustomers,
        private OrganizationUserRepository $members,
        private FileStorageService $storage,
        private AuditService $audit,
        private NotificationService $notifications
    ) {
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'verified' => 'success',
            'rejected' => 'danger',
            'expired'  => 'warning',
            default    => 'neutral',
        };
    }

    // -----------------------------------------------------------------
    // Customer uploads
    // -----------------------------------------------------------------

    /**
     * Stores a document for its owner, replacing any previous document of the
     * same type.
     *
     * @param array{path:string,mime:string,size:int} $stored Result of ImageService::storePrivateDocument
     */
    public function store(
        int $userId,
        string $type,
        array $stored,
        ?string $originalName,
        ?string $expiresAt
    ): int {
        if (!array_key_exists($type, self::TYPES)) {
            throw new DocumentException('That document type is not supported.');
        }

        return $this->db->transaction(function () use ($userId, $type, $stored, $originalName, $expiresAt): int {
            // Replacing a document invalidates every organization's review of
            // the old file, which is why the old row is removed outright.
            foreach ($this->documents->listForUser($userId) as $existing) {
                if ((string) $existing['document_type'] !== $type) {
                    continue;
                }

                $this->documents->delete((int) $existing['id'], $userId);
                $this->storage->delete(FileStorageService::DISK_PRIVATE, (string) $existing['file_path']);
            }

            return $this->documents->create([
                'user_id'       => $userId,
                'document_type' => $type,
                'file_path'     => $stored['path'],
                'original_name' => $originalName === null ? null : mb_substr($originalName, 0, 191),
                'mime_type'     => $stored['mime'],
                'file_size'     => $stored['size'],
                'expires_at'    => $expiresAt,
            ]);
        });
    }

    /** The private directory a user's documents belong in. */
    public function directoryFor(int $userId): string
    {
        return 'documents/' . $userId;
    }

    /** @throws DocumentException */
    public function deleteOwn(int $userId, int $documentId): void
    {
        $document = $this->documents->findForUser($documentId, $userId);

        if ($document === null) {
            throw new DocumentException('Document not found.');
        }

        $this->documents->delete($documentId, $userId);
        $this->storage->delete(FileStorageService::DISK_PRIVATE, (string) $document['file_path']);
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    /**
     * Decides whether $viewerUserId may read $document.
     *
     * @param array<string,mixed> $document
     */
    public function canView(array $document, int $viewerUserId): bool
    {
        $ownerId = (int) $document['user_id'];

        // 1. The owner.
        if ($ownerId === $viewerUserId) {
            return true;
        }

        // 2 and 3. Staff of an organization the owner actually deals with.
        foreach ($this->members->organizationsForUser($viewerUserId) as $membership) {
            $organizationId = (int) $membership['id'];

            if (!$this->organizationCustomers->relationshipExists($organizationId, $ownerId)) {
                continue;
            }

            if ((string) $membership['role'] === OrganizationContext::ROLE_ADMIN) {
                return true;
            }

            $memberRow = $this->members->findMembership($organizationId, $viewerUserId);

            if ($memberRow === null) {
                continue;
            }

            $permissions = $this->members->permissionKeysFor((int) $memberRow['id']);

            if (in_array(Permissions::DOCUMENTS_VIEW, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Absolute path for streaming, resolved from the trusted database value.
     *
     * @param array<string,mixed> $document
     */
    public function absolutePath(array $document): string
    {
        return $this->storage->absolutePath(
            FileStorageService::DISK_PRIVATE,
            (string) $document['file_path']
        );
    }

    // -----------------------------------------------------------------
    // Organization review
    // -----------------------------------------------------------------

    /**
     * Records this organization's verification decision.
     *
     * Verification is scoped to (organization, document): one agency's
     * decision never affects another agency's view of the same document.
     *
     * @throws DocumentException
     */
    public function review(
        OrganizationContext $context,
        int $documentId,
        string $status,
        ?string $rejectionReason = null
    ): void {
        $context->authorize(Permissions::DOCUMENTS_VERIFY);

        if (!in_array($status, ['verified', 'rejected'], true)) {
            throw new DocumentException('That review decision is not valid.');
        }

        // The join in this query is what confines the organization to
        // documents of its own customers.
        $document = $this->documents->findDocumentForOrganization($documentId, $context->organizationId());

        if ($document === null) {
            throw new DocumentException('Document not found.');
        }

        if ($status === 'rejected' && ($rejectionReason === null || trim($rejectionReason) === '')) {
            throw new DocumentException('A reason is required when rejecting a document.');
        }

        $this->documents->upsertReview(
            $context->organizationId(),
            $documentId,
            $status,
            $context->userId(),
            $status === 'rejected' ? $rejectionReason : null
        );

        $this->audit->record(
            $context->organizationId(),
            $context->userId(),
            $status === 'verified' ? AuditService::DOCUMENT_VERIFIED : AuditService::DOCUMENT_REJECTED,
            'document',
            $documentId,
            ['type' => $document['document_type'], 'customer_user_id' => (int) $document['user_id']]
        );

        if ($status === 'verified') {
            $this->notifications->documentVerified(
                (int) $document['user_id'],
                $context->organizationId(),
                $context->name(),
                (string) $document['document_type']
            );
        } else {
            $this->notifications->documentRejected(
                (int) $document['user_id'],
                $context->organizationId(),
                $context->name(),
                (string) $document['document_type'],
                $rejectionReason
            );
        }
    }

    public function documents(): DocumentRepository
    {
        return $this->documents;
    }
}
