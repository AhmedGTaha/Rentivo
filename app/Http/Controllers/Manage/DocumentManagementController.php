<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Services\DocumentException;
use Rentivo\Services\DocumentService;
use Rentivo\Support\Flash;

/**
 * Organization-specific document review.
 *
 * The queue only ever contains documents belonging to this organization's own
 * customers, and a decision here has no effect on any other agency's view of
 * the same document.
 */
final class DocumentManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private DocumentRepository $documents,
        private DocumentService $documentService
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/documents */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::DOCUMENTS_VIEW);

        $status = $request->queryParam('status');
        $status = is_string($status) && in_array($status, DocumentService::STATUSES, true) ? $status : null;

        $search = $this->search($request);

        $total = $this->documents->countForOrganization($context->organizationId(), $status, $search);
        $pagination = $this->paginate($request, $total, 15);

        return $this->renderManage($context, 'manage/documents', [
            'title'         => 'Documents',
            'manageSection' => 'documents',
            'documents'     => $this->documents->listForOrganization(
                $context->organizationId(),
                $status,
                $search,
                $pagination
            ),
            'total'         => $total,
            'status'        => $status,
            'search'        => $search ?? '',
            'pagination'    => $pagination,
            'statusCounts'  => $this->documents->reviewStatusCounts($context->organizationId()),
            'statuses'      => DocumentService::STATUSES,
        ]);
    }

    /** POST /manage/{org}/documents/{id}/review */
    public function review(Request $request): Response
    {
        $context = $this->organization($request);

        $documentId = $request->routeInt('id');

        try {
            $this->documentService->review(
                $context,
                $documentId,
                (string) $request->input('decision'),
                $this->nullableString($request, 'rejection_reason', 500)
            );

            Flash::success('Document review recorded.');
        } catch (DocumentException $e) {
            Flash::error($e->getMessage());
        }

        return $this->back($request, $this->manageUrl($context, 'documents'));
    }
}
