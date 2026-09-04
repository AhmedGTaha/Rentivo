<?php

declare(strict_types=1);

namespace Rentivo\Repositories;

use Rentivo\Support\Pagination;

/**
 * Customer identity documents and per-organization reviews of them.
 */
final class DocumentRepository extends Repository
{
    // -----------------------------------------------------------------
    // Documents (owned by the customer)
    // -----------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM `user_documents` WHERE `id` = ?', [$id]);
    }

    /** Owner-scoped lookup used by the customer's own document pages. */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `user_documents` WHERE `id` = ? AND `user_id` = ?',
            [$id, $userId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT * FROM `user_documents` WHERE `user_id` = ? ORDER BY `document_type`, `created_at` DESC',
            [$userId]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('user_documents', $data + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function delete(int $id, int $userId): int
    {
        return $this->db->affectingStatement(
            'DELETE FROM `user_documents` WHERE `id` = ? AND `user_id` = ?',
            [$id, $userId]
        );
    }

    // -----------------------------------------------------------------
    // Reviews (owned by an organization)
    // -----------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function findReview(int $organizationId, int $documentId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM `document_reviews` WHERE `organization_id` = ? AND `document_id` = ?',
            [$organizationId, $documentId]
        );
    }

    /**
     * A document plus this organization's review of it, restricted to
     * customers who actually have a relationship with the organization.
     *
     * @return array<string,mixed>|null
     */
    public function findDocumentForOrganization(int $documentId, int $organizationId): ?array
    {
        return $this->db->selectOne(
            'SELECT d.*, u.`name` AS `customer_name`, u.`email` AS `customer_email`,
                    dr.`id` AS `review_id`, dr.`status` AS `review_status`,
                    dr.`reviewed_at`, dr.`rejection_reason`, dr.`reviewed_by_user_id`
             FROM `user_documents` d
             INNER JOIN `users` u ON u.`id` = d.`user_id`
             INNER JOIN `organization_customers` oc
                    ON oc.`user_id` = d.`user_id` AND oc.`organization_id` = ?
             LEFT JOIN `document_reviews` dr
                    ON dr.`document_id` = d.`id` AND dr.`organization_id` = ?
             WHERE d.`id` = ?',
            [$organizationId, $organizationId, $documentId]
        );
    }

    /**
     * Documents belonging to one customer, as seen by one organization.
     *
     * @return list<array<string,mixed>>
     */
    public function listForCustomer(int $organizationId, int $userId): array
    {
        return $this->db->select(
            'SELECT d.*, dr.`status` AS `review_status`, dr.`reviewed_at`, dr.`rejection_reason`
             FROM `user_documents` d
             LEFT JOIN `document_reviews` dr
                    ON dr.`document_id` = d.`id` AND dr.`organization_id` = ?
             WHERE d.`user_id` = ?
             ORDER BY d.`document_type`',
            [$organizationId, $userId]
        );
    }

    /**
     * The organization's document review queue.
     *
     * @return list<array<string,mixed>>
     */
    public function listForOrganization(
        int $organizationId,
        ?string $status,
        ?string $search,
        Pagination $pagination
    ): array {
        [$where, $bindings] = $this->reviewFilter($organizationId, $status, $search);

        return $this->db->select(
            'SELECT d.*, u.`name` AS `customer_name`, u.`email` AS `customer_email`,
                    u.`google_avatar_url` AS `customer_avatar`,
                    dr.`id` AS `review_id`,
                    COALESCE(dr.`status`, \'pending\') AS `review_status`,
                    dr.`reviewed_at`, dr.`rejection_reason`
             FROM `user_documents` d
             INNER JOIN `users` u ON u.`id` = d.`user_id`
             INNER JOIN `organization_customers` oc
                    ON oc.`user_id` = d.`user_id` AND oc.`organization_id` = ?
             LEFT JOIN `document_reviews` dr
                    ON dr.`document_id` = d.`id` AND dr.`organization_id` = ?
             WHERE ' . $where . '
             ORDER BY d.`created_at` DESC
             LIMIT ' . $pagination->limit() . ' OFFSET ' . $pagination->offset(),
            $bindings
        );
    }

    public function countForOrganization(int $organizationId, ?string $status, ?string $search): int
    {
        [$where, $bindings] = $this->reviewFilter($organizationId, $status, $search);

        return (int) $this->db->scalar(
            'SELECT COUNT(*)
             FROM `user_documents` d
             INNER JOIN `users` u ON u.`id` = d.`user_id`
             INNER JOIN `organization_customers` oc
                    ON oc.`user_id` = d.`user_id` AND oc.`organization_id` = ?
             LEFT JOIN `document_reviews` dr
                    ON dr.`document_id` = d.`id` AND dr.`organization_id` = ?
             WHERE ' . $where,
            $bindings
        );
    }

    /** @return array{0:string,1:list<mixed>} */
    private function reviewFilter(int $organizationId, ?string $status, ?string $search): array
    {
        // The first two bindings feed the joins above.
        $bindings = [$organizationId, $organizationId];
        $where = '1 = 1';

        if ($status !== null && $status !== '') {
            $where .= ' AND COALESCE(dr.`status`, \'pending\') = ?';
            $bindings[] = $status;
        }

        if ($search !== null && $search !== '') {
            $where .= ' AND (u.`name` LIKE ? OR u.`email` LIKE ?)';
            $term = '%' . $search . '%';
            array_push($bindings, $term, $term);
        }

        return [$where, $bindings];
    }

    /**
     * Records this organization's decision on a document.
     *
     * Unique (organization_id, document_id) makes the upsert the natural
     * expression of "one review per organization per document".
     */
    public function upsertReview(
        int $organizationId,
        int $documentId,
        string $status,
        ?int $reviewerId,
        ?string $rejectionReason
    ): void {
        $now = $this->now();

        $this->db->statement(
            'INSERT INTO `document_reviews`
                (`organization_id`, `document_id`, `status`, `reviewed_by_user_id`, `reviewed_at`,
                 `rejection_reason`, `created_at`, `updated_at`)
             VALUES (:org, :doc, :status, :reviewer, :reviewed_at, :reason, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                `status` = :status2,
                `reviewed_by_user_id` = :reviewer2,
                `reviewed_at` = :reviewed_at2,
                `rejection_reason` = :reason2,
                `updated_at` = :updated_at2',
            [
                'org'          => $organizationId,
                'doc'          => $documentId,
                'status'       => $status,
                'reviewer'     => $reviewerId,
                'reviewed_at'  => $now,
                'reason'       => $rejectionReason,
                'created_at'   => $now,
                'updated_at'   => $now,
                'status2'      => $status,
                'reviewer2'    => $reviewerId,
                'reviewed_at2' => $now,
                'reason2'      => $rejectionReason,
                'updated_at2'  => $now,
            ]
        );
    }

    /**
     * Marks verified reviews expired once the document's expiry date passes.
     * Called by the scheduler.
     */
    public function expireOutdatedReviews(): int
    {
        return $this->db->affectingStatement(
            'UPDATE `document_reviews` dr
             INNER JOIN `user_documents` d ON d.`id` = dr.`document_id`
             SET dr.`status` = \'expired\', dr.`updated_at` = ?
             WHERE dr.`status` = \'verified\'
               AND d.`expires_at` IS NOT NULL
               AND d.`expires_at` < CURDATE()',
            [$this->now()]
        );
    }

    /** @return array<string,int> */
    public function reviewStatusCounts(int $organizationId): array
    {
        $rows = $this->db->select(
            'SELECT COALESCE(dr.`status`, \'pending\') AS `status`, COUNT(*) AS `total`
             FROM `user_documents` d
             INNER JOIN `organization_customers` oc
                    ON oc.`user_id` = d.`user_id` AND oc.`organization_id` = ?
             LEFT JOIN `document_reviews` dr
                    ON dr.`document_id` = d.`id` AND dr.`organization_id` = ?
             GROUP BY COALESCE(dr.`status`, \'pending\')',
            [$organizationId, $organizationId]
        );

        $counts = array_fill_keys(['pending', 'verified', 'rejected', 'expired'], 0);

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Every organization review of a document, shown to the owning customer
     * so they understand where their document stands.
     *
     * @return list<array<string,mixed>>
     */
    public function reviewsForDocument(int $documentId): array
    {
        return $this->db->select(
            'SELECT dr.*, o.`name` AS `organization_name`, o.`slug` AS `organization_slug`
             FROM `document_reviews` dr
             INNER JOIN `organizations` o ON o.`id` = dr.`organization_id`
             WHERE dr.`document_id` = ?
             ORDER BY o.`name`',
            [$documentId]
        );
    }
}
