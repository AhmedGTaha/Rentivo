<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\RentalRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Services\FileStorageService;
use Rentivo\Support\Logger;
use RuntimeException;

/**
 * Authorized delivery of private rental inspection photographs.
 *
 * Inspection images live under storage/private and are scoped to the owning
 * organization, so no URL guessing can surface another agency's photos.
 */
final class InspectionImageController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private RentalRepository $rentals,
        private FileStorageService $storage
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/inspections/{id}/file */
    public function stream(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::BOOKINGS_VIEW);

        $image = $this->rentals->findInspectionImage(
            $request->routeInt('id'),
            $context->organizationId()
        );

        if ($image === null) {
            throw HttpException::notFound();
        }

        try {
            $path = $this->storage->absolutePath(
                FileStorageService::DISK_PRIVATE,
                (string) $image['file_path']
            );
        } catch (RuntimeException $e) {
            Logger::exception($e, ['context' => 'inspection_stream']);

            throw HttpException::notFound();
        }

        if (!is_file($path)) {
            throw HttpException::notFound();
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw HttpException::notFound();
        }

        $mime = str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg';

        return new Response($contents, 200, [
            'Content-Type'           => $mime,
            'Content-Length'         => (string) strlen($contents),
            'Content-Disposition'    => 'inline; filename="inspection-' . $image['id'] . '"',
            'Cache-Control'          => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
