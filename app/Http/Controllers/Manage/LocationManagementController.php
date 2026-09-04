<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\Permissions;
use Rentivo\Services\LocationException;
use Rentivo\Services\LocationService;
use Rentivo\Support\Flash;

/**
 * Organization location CRUD.
 *
 * Viewing needs locations.view; any change needs locations.manage.
 */
final class LocationManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private LocationService $locations,
        private LocationRepository $repository
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/locations */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorizeAny([Permissions::LOCATIONS_VIEW, Permissions::LOCATIONS_MANAGE]);

        $editId = (int) $request->queryParam('edit', 0);

        return $this->renderManage($context, 'manage/locations', [
            'title'         => 'Locations',
            'manageSection' => 'locations',
            'locations'     => $this->repository->listForOrganization($context->organizationId()),
            'editing'       => $editId > 0
                ? $this->repository->findInOrganization($editId, $context->organizationId())
                : null,
        ]);
    }

    /** POST /manage/{org}/locations */
    public function store(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::LOCATIONS_MANAGE);

        $validator = $this->validateLocation($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'locations'),
                $validator->errors(),
                $request->body()
            );
        }

        $this->locations->create($context, $this->locationData($validator, $request));

        Flash::success('Location added.');

        return $this->redirect($this->manageUrl($context, 'locations'));
    }

    /** POST /manage/{org}/locations/{id} */
    public function update(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::LOCATIONS_MANAGE);

        $locationId = $request->routeInt('id');

        if ($this->repository->findInOrganization($locationId, $context->organizationId()) === null) {
            throw HttpException::notFound('Location not found in this organization.');
        }

        $validator = $this->validateLocation($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'locations?edit=' . $locationId),
                $validator->errors(),
                $request->body()
            );
        }

        try {
            $this->locations->update($context, $locationId, $this->locationData($validator, $request));

            Flash::success('Location updated.');
        } catch (LocationException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'locations'));
    }

    private function validateLocation(Request $request): \Rentivo\Validation\Validator
    {
        return $this->validate($request, [
            'name'          => 'required|max:150',
            'address'       => 'required|max:255',
            'phone'         => 'nullable|phone|max:32',
            'opening_hours' => 'nullable|max:255',
            'latitude'      => 'nullable|max:20',
            'longitude'     => 'nullable|max:20',
        ], [
            'opening_hours' => 'Opening hours',
        ]);
    }

    /** @return array<string,mixed> */
    private function locationData(\Rentivo\Validation\Validator $validator, Request $request): array
    {
        return [
            'name'          => (string) $validator->value('name'),
            'address'       => (string) $validator->value('address'),
            'phone'         => $validator->value('phone'),
            'opening_hours' => $validator->value('opening_hours'),
            'latitude'      => $this->coordinate($validator->value('latitude'), 90),
            'longitude'     => $this->coordinate($validator->value('longitude'), 180),
            'is_active'     => $request->boolean('is_active') ? 1 : 0,
        ];
    }

    private function coordinate(mixed $value, float $limit): ?string
    {
        if (!is_string($value) || trim($value) === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return abs($number) <= $limit ? number_format($number, 7, '.', '') : null;
    }
}
