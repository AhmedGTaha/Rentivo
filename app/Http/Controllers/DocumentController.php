<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\RateLimiter;
use Rentivo\Services\DocumentException;
use Rentivo\Services\DocumentService;
use Rentivo\Services\FileStorageService;
use Rentivo\Services\ImageService;
use Rentivo\Services\UploadException;
use Rentivo\Support\Flash;
use Rentivo\Support\Logger;

/**
 * Customer document upload and the authorized private-file delivery route.
 *
 * Documents are stored under storage/private and are never addressable by
 * URL; the only way to read one is through stream(), which asks
 * DocumentService::canView() first.
 */
final class DocumentController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private DocumentRepository $documents,
        private DocumentService $documentService,
        private ImageService $images,
        private FileStorageService $storage,
        private RateLimiter $rateLimiter
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /account/documents */
    public function index(Request $request): Response
    {
        $userId = $this->requireUserId();

        $documents = $this->documents->listForUser($userId);

        // Show the customer how each agency has reviewed each document.
        $reviews = [];
        foreach ($documents as $document) {
            $reviews[(int) $document['id']] = $this->documents->reviewsForDocument((int) $document['id']);
        }

        return $this->render('account/documents', [
            'title'          => 'Documents',
            'documents'      => $documents,
            'reviews'        => $reviews,
            'types'          => DocumentService::TYPES,
            'accountSection' => 'documents',
        ], 'account');
    }

    /** POST /account/documents */
    public function store(Request $request): Response
    {
        $userId = $this->requireUserId();

        $this->rateLimiter->enforce(
            'document_upload:' . $userId,
            20,
            3600,
            'Too many document uploads. Please try again later.'
        );

        $validator = $this->validate($request, [
            'document_type' => 'required|in:' . implode(',', array_keys(DocumentService::TYPES)),
            'expires_at'    => 'nullable|date',
        ], [
            'document_type' => 'Document type',
            'expires_at'    => 'Expiry date',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/account/documents', $validator->errors(), $request->body());
        }

        $file = $request->file('document');

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->redirectWithErrors(
                '/account/documents',
                ['document' => 'Please choose a file to upload.'],
                $request->body()
            );
        }

        try {
            $stored = $this->images->storePrivateDocument(
                $file,
                $this->documentService->directoryFor($userId)
            );

            $expiresAt = $validator->value('expires_at');

            $this->documentService->store(
                $userId,
                (string) $validator->value('document_type'),
                $stored,
                is_string($file['name'] ?? null) ? basename($file['name']) : null,
                $expiresAt instanceof \DateTimeInterface ? $expiresAt->format('Y-m-d') : null
            );

            Flash::success('Your document has been uploaded securely.');
        } catch (UploadException | DocumentException $e) {
            return $this->redirectWithErrors('/account/documents', ['document' => $e->getMessage()], $request->body());
        }

        return $this->redirect('/account/documents');
    }

    /** POST /account/documents/{id}/delete */
    public function destroy(Request $request): Response
    {
        $userId = $this->requireUserId();

        try {
            $this->documentService->deleteOwn($userId, $request->routeInt('id'));

            Flash::success('Document removed.');
        } catch (DocumentException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect('/account/documents');
    }

    /**
     * GET /documents/{id}/file
     *
     * The authorized delivery route for private documents.
     */
    public function stream(Request $request): Response
    {
        $viewerId = $this->requireUserId();

        $document = $this->documents->find($request->routeInt('id'));

        if ($document === null) {
            throw HttpException::notFound();
        }

        // The single authorization gate for private files.
        if (!$this->documentService->canView($document, $viewerId)) {
            throw HttpException::forbidden('You are not allowed to view this document.');
        }

        try {
            $path = $this->documentService->absolutePath($document);
        } catch (\RuntimeException $e) {
            Logger::exception($e, ['context' => 'document_stream', 'document_id' => $document['id']]);

            throw HttpException::notFound();
        }

        if (!is_file($path)) {
            throw HttpException::notFound();
        }

        $mime = (string) $document['mime_type'];
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw HttpException::notFound();
        }

        // Inline for viewable types, attachment otherwise; never sniffed.
        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
        $filename = 'rentivo-' . $document['document_type'] . '-' . $document['id'];

        return (new Response($contents, 200, [
            'Content-Type'        => $mime,
            'Content-Length'      => (string) strlen($contents),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                . '; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
