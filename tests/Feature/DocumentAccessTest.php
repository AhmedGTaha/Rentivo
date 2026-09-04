<?php

declare(strict_types=1);

namespace Rentivo\Tests\Feature;

use Rentivo\Http\HttpException;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Repositories\OrganizationCustomerRepository;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\DocumentException;
use Rentivo\Services\DocumentService;
use Rentivo\Services\FileStorageService;
use Rentivo\Tests\TestCase;
use RuntimeException;

/**
 * SRS Critical Acceptance Test 8: private documents.
 *
 * Documents live outside the public web root, are only readable through the
 * authorizing route, and are verified per organization.
 */
final class DocumentAccessTest extends TestCase
{
    private array $orgA;
    private array $orgB;
    private int $adminA;
    private int $adminB;
    private int $customerId;
    private int $documentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresDatabase();

        $this->adminA = $this->createUser('admin.a@example.test', 'Admin A');
        $this->adminB = $this->createUser('admin.b@example.test', 'Admin B');
        $this->customerId = $this->createUserWithPhone('customer@example.test', 'Customer');

        $this->orgA = $this->createOrganization('Doc Alpha', $this->adminA);
        $this->orgB = $this->createOrganization('Doc Beta', $this->adminB);

        // The customer has a relationship with A only.
        $this->app->get(OrganizationCustomerRepository::class)
            ->ensure((int) $this->orgA['id'], $this->customerId);

        $this->documentId = $this->app->get(DocumentRepository::class)->create([
            'user_id'       => $this->customerId,
            'document_type' => 'driving_license',
            'file_path'     => 'documents/' . $this->customerId . '/licence.webp',
            'mime_type'     => 'image/webp',
            'file_size'     => 2048,
        ]);
    }

    private function documents(): DocumentService
    {
        return $this->app->get(DocumentService::class);
    }

    private function document(): array
    {
        return $this->app->get(DocumentRepository::class)->find($this->documentId);
    }

    // -----------------------------------------------------------------
    // Storage location
    // -----------------------------------------------------------------

    /** Documents must never be written under the public web root. */
    public function testDocumentsAreStoredOutsideThePublicDirectory(): void
    {
        /** @var FileStorageService $storage */
        $storage = $this->app->get(FileStorageService::class);

        $privateRoot = str_replace('\\', '/', $storage->privateRoot());
        $publicRoot = str_replace('\\', '/', $storage->publicRoot());

        self::assertStringContainsString('/storage/private', $privateRoot);
        self::assertStringNotContainsString('/public/', $privateRoot);
        self::assertNotSame($publicRoot, $privateRoot);

        // The stored path resolves inside the private root and nowhere else.
        $resolved = str_replace('\\', '/', $this->documents()->absolutePath($this->document()));
        self::assertStringStartsWith($privateRoot, $resolved);
    }

    /** A traversal attempt in a stored path is refused outright. */
    public function testPathTraversalIsRefused(): void
    {
        /** @var FileStorageService $storage */
        $storage = $this->app->get(FileStorageService::class);

        $candidates = [
            '../../public/uploads/evil.webp',
            'documents/../../.env',
            '/etc/passwd',
            'C:/Windows/win.ini',
            "documents/\0/evil",
            '',
        ];

        $refused = 0;

        foreach ($candidates as $candidate) {
            try {
                $storage->absolutePath(FileStorageService::DISK_PRIVATE, $candidate);
                self::fail('Traversal path was accepted: ' . $candidate);
            } catch (RuntimeException) {
                $refused++;
            }
        }

        self::assertCount($refused, $candidates, 'Every traversal attempt must be refused.');
    }

    // -----------------------------------------------------------------
    // Who may read a document
    // -----------------------------------------------------------------

    public function testOwnerCanAlwaysViewTheirOwnDocument(): void
    {
        self::assertTrue($this->documents()->canView($this->document(), $this->customerId));
    }

    public function testUnrelatedUserCannotView(): void
    {
        $stranger = $this->createUser('stranger@example.test', 'Stranger');

        self::assertFalse($this->documents()->canView($this->document(), $stranger));
    }

    /** An admin of an organization the customer deals with may view. */
    public function testAdminOfARelatedOrganizationCanView(): void
    {
        self::assertTrue($this->documents()->canView($this->document(), $this->adminA));
    }

    /** An admin of an unrelated organization may not. */
    public function testAdminOfAnUnrelatedOrganizationCannotView(): void
    {
        self::assertFalse(
            $this->documents()->canView($this->document(), $this->adminB),
            'Organization B has no relationship with this customer.'
        );
    }

    public function testEmployeeWithPermissionAndRelationshipCanView(): void
    {
        $employee = $this->createUser('employee.a@example.test', 'Employee A');
        $this->addEmployee((int) $this->orgA['id'], $employee, [Permissions::DOCUMENTS_VIEW]);

        self::assertTrue($this->documents()->canView($this->document(), $employee));
    }

    /** The permission alone is not enough without a relationship. */
    public function testEmployeeWithPermissionButNoRelationshipCannotView(): void
    {
        $employee = $this->createUser('employee.b@example.test', 'Employee B');
        $this->addEmployee((int) $this->orgB['id'], $employee, [Permissions::DOCUMENTS_VIEW]);

        self::assertFalse($this->documents()->canView($this->document(), $employee));
    }

    /** The relationship alone is not enough without the permission. */
    public function testEmployeeWithRelationshipButNoPermissionCannotView(): void
    {
        $employee = $this->createUser('employee.c@example.test', 'Employee C');
        $this->addEmployee((int) $this->orgA['id'], $employee, [Permissions::CARS_VIEW]);

        self::assertFalse($this->documents()->canView($this->document(), $employee));
    }

    /** Losing the permission revokes access immediately. */
    public function testRevokingThePermissionRevokesAccess(): void
    {
        $employee = $this->createUser('employee.d@example.test', 'Employee D');
        $membershipId = $this->addEmployee((int) $this->orgA['id'], $employee, [Permissions::DOCUMENTS_VIEW]);

        self::assertTrue($this->documents()->canView($this->document(), $employee));

        $this->app->get(\Rentivo\Repositories\OrganizationUserRepository::class)
            ->syncPermissions($membershipId, []);

        self::assertFalse($this->documents()->canView($this->document(), $employee));
    }

    // -----------------------------------------------------------------
    // Organization-specific review (SRS §51)
    // -----------------------------------------------------------------

    /**
     * One agency verifying a document must not make it verified for another.
     */
    public function testVerificationIsScopedToOneOrganization(): void
    {
        // Give B a relationship too, so both can see the document.
        $this->app->get(OrganizationCustomerRepository::class)
            ->ensure((int) $this->orgB['id'], $this->customerId);

        $this->actingAs($this->adminA);
        $contextA = $this->contextFor((string) $this->orgA['slug']);

        $this->documents()->review($contextA, $this->documentId, 'verified');

        /** @var DocumentRepository $documents */
        $documents = $this->app->get(DocumentRepository::class);

        $reviewA = $documents->findReview((int) $this->orgA['id'], $this->documentId);
        self::assertSame('verified', $reviewA['status']);
        self::assertSame($this->adminA, (int) $reviewA['reviewed_by_user_id']);
        self::assertNotNull($reviewA['reviewed_at']);

        // B still sees it as pending.
        self::assertNull($documents->findReview((int) $this->orgB['id'], $this->documentId));

        $asSeenByB = $documents->findDocumentForOrganization($this->documentId, (int) $this->orgB['id']);
        self::assertNull($asSeenByB['review_status'], 'B must not inherit A\'s verification.');
    }

    public function testRejectionRequiresAReason(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);

        $this->expectException(DocumentException::class);

        $this->documents()->review($context, $this->documentId, 'rejected', null);
    }

    public function testRejectionStoresTheReason(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);

        $this->documents()->review($context, $this->documentId, 'rejected', 'The image is unreadable.');

        $review = $this->app->get(DocumentRepository::class)
            ->findReview((int) $this->orgA['id'], $this->documentId);

        self::assertSame('rejected', $review['status']);
        self::assertSame('The image is unreadable.', $review['rejection_reason']);
    }

    /** An organization cannot review a document of someone else's customer. */
    public function testCannotReviewADocumentOfANonCustomer(): void
    {
        $this->actingAs($this->adminB);
        $context = $this->contextFor((string) $this->orgB['slug']);

        $this->expectException(DocumentException::class);

        $this->documents()->review($context, $this->documentId, 'verified');
    }

    public function testReviewRequiresTheVerifyPermission(): void
    {
        $employee = $this->createUser('employee.e@example.test', 'Employee E');
        $this->addEmployee((int) $this->orgA['id'], $employee, [Permissions::DOCUMENTS_VIEW]);

        $this->actingAs($employee);
        $context = $this->contextFor((string) $this->orgA['slug']);

        $this->expectException(HttpException::class);

        $this->documents()->review($context, $this->documentId, 'verified');
    }

    public function testOnlyVerifiedOrRejectedAreValidDecisions(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);

        $this->expectException(DocumentException::class);

        $this->documents()->review($context, $this->documentId, 'approved');
    }

    /** Re-reviewing replaces the decision rather than creating a second row. */
    public function testReviewIsUniquePerOrganizationAndDocument(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);

        $this->documents()->review($context, $this->documentId, 'verified');
        $this->documents()->review($context, $this->documentId, 'rejected', 'Expired licence.');

        $count = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM document_reviews WHERE organization_id = ? AND document_id = ?',
            [(int) $this->orgA['id'], $this->documentId]
        );

        self::assertSame(1, $count);

        $review = $this->app->get(DocumentRepository::class)
            ->findReview((int) $this->orgA['id'], $this->documentId);

        self::assertSame('rejected', $review['status']);
    }

    /** Deleting the document removes every organization's review of it. */
    public function testDeletingADocumentRemovesItsReviews(): void
    {
        $this->actingAs($this->adminA);
        $context = $this->contextFor((string) $this->orgA['slug']);
        $this->documents()->review($context, $this->documentId, 'verified');

        $this->documents()->deleteOwn($this->customerId, $this->documentId);

        $count = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM document_reviews WHERE document_id = ?',
            [$this->documentId]
        );

        self::assertSame(0, $count);
    }

    public function testCustomerCannotDeleteSomeoneElsesDocument(): void
    {
        $stranger = $this->createUser('stranger2@example.test', 'Stranger');

        $this->expectException(DocumentException::class);

        $this->documents()->deleteOwn($stranger, $this->documentId);
    }
}
