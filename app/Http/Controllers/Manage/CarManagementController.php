<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers\Manage;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\CarImageRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Security\Authorization;
use Rentivo\Security\OrganizationContext;
use Rentivo\Security\Permissions;
use Rentivo\Services\AvailabilityService;
use Rentivo\Services\CarException;
use Rentivo\Services\CarService;
use Rentivo\Support\Flash;

/**
 * Fleet management.
 *
 * Every action asserts its permission through the organization context before
 * touching a service, and every car lookup is tenant-scoped, so changing the
 * id in the URL cannot reach another agency's vehicle.
 */
final class CarManagementController extends ManageController
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private CarService $cars,
        private CarRepository $carRepository,
        private CarImageRepository $images,
        private CategoryRepository $categories,
        private LocationRepository $locations,
        private AvailabilityService $availability
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /manage/{org}/cars */
    public function index(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CARS_VIEW);

        $filters = [
            'search'           => $this->search($request),
            'status'           => $this->statusFilter($request),
            'category_id'      => (int) $request->queryParam('category_id', 0),
            'include_archived' => $request->queryParam('archived') === '1',
        ];

        $total = $this->carRepository->countForOrganization($context->organizationId(), $filters);
        $pagination = $this->paginate($request, $total, 12);

        return $this->renderManage($context, 'manage/cars/index', [
            'title'         => 'Fleet',
            'manageSection' => 'cars',
            'cars'          => $this->carRepository->listForOrganization($context->organizationId(), $filters, $pagination),
            'total'         => $total,
            'pagination'    => $pagination,
            'filters'       => $filters,
            'statusCounts'  => $this->carRepository->statusCounts($context->organizationId()),
            'categories'    => $this->categories->listForOrganization($context->organizationId()),
            'statuses'      => CarService::statuses(),
        ]);
    }

    /** GET /manage/{org}/cars/create */
    public function create(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CARS_CREATE);

        return $this->renderManage($context, 'manage/cars/form', [
            'title'         => 'Add a car',
            'manageSection' => 'cars',
            'car'           => null,
            'categories'    => $this->categories->listForOrganization($context->organizationId()),
            'locations'     => $this->locations->listForOrganization($context->organizationId(), true),
            'transmissions' => CarService::transmissions(),
            'fuelTypes'     => CarService::fuelTypes(),
            'statuses'      => CarService::statuses(),
        ]);
    }

    /** POST /manage/{org}/cars */
    public function store(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CARS_CREATE);

        $validator = $this->validateCar($request, $context);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'cars/create'),
                $validator->errors(),
                $request->body()
            );
        }

        $car = $this->cars->create($context, $this->carData($validator));

        Flash::success($car['brand'] . ' ' . $car['model'] . ' added. Upload photos to publish it.');

        return $this->redirect($this->manageUrl($context, 'cars/' . $car['id'] . '/edit'));
    }

    /** GET /manage/{org}/cars/{id}/edit */
    public function edit(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CARS_VIEW);

        $car = $this->requireCar($context, $request->routeInt('id'));

        return $this->renderManage($context, 'manage/cars/form', [
            'title'         => $car['brand'] . ' ' . $car['model'],
            'manageSection' => 'cars',
            'car'           => $car,
            'images'        => $this->images->listForCar((int) $car['id']),
            'categories'    => $this->categories->listForOrganization($context->organizationId()),
            'locations'     => $this->locations->listForOrganization($context->organizationId(), true),
            'transmissions' => CarService::transmissions(),
            'fuelTypes'     => CarService::fuelTypes(),
            'statuses'      => CarService::statuses(),
            'maxImages'     => (int) config('uploads.max_car_images', 10),
            'upcomingBookings' => $this->availability->upcomingBlockingBookings((int) $car['id']),
        ]);
    }

    /** POST /manage/{org}/cars/{id} */
    public function update(Request $request): Response
    {
        $context = $this->organization($request);
        $context->authorize(Permissions::CARS_EDIT);

        $carId = $request->routeInt('id');
        $this->requireCar($context, $carId);

        $validator = $this->validateCar($request, $context, $carId);

        if ($validator->fails()) {
            return $this->redirectWithErrors(
                $this->manageUrl($context, 'cars/' . $carId . '/edit'),
                $validator->errors(),
                $request->body()
            );
        }

        try {
            $this->cars->update($context, $carId, $this->carData($validator));

            Flash::success('Car updated.');
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'cars/' . $carId . '/edit'));
    }

    /** POST /manage/{org}/cars/{id}/status */
    public function updateStatus(Request $request): Response
    {
        $context = $this->organization($request);

        $carId = $request->routeInt('id');

        try {
            $this->cars->changeStatus($context, $carId, (string) $request->input('status'));

            Flash::success('Car status updated.');
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->back($request, $this->manageUrl($context, 'cars/' . $carId . '/edit'));
    }

    /** POST /manage/{org}/cars/{id}/archive */
    public function archive(Request $request): Response
    {
        $context = $this->organization($request);

        $carId = $request->routeInt('id');

        try {
            // Archival, never deletion: historical bookings keep their car.
            $this->cars->archive($context, $carId);

            Flash::success('Car archived. Its booking history has been preserved.');
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'cars'));
    }

    /** POST /manage/{org}/cars/{id}/restore */
    public function restore(Request $request): Response
    {
        $context = $this->organization($request);

        $carId = $request->routeInt('id');

        try {
            $this->cars->restore($context, $carId);

            Flash::success('Car restored to the active fleet.');
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'cars/' . $carId . '/edit'));
    }

    // -----------------------------------------------------------------
    // Images
    // -----------------------------------------------------------------

    /** POST /manage/{org}/cars/{id}/images */
    public function uploadImages(Request $request): Response
    {
        $context = $this->organization($request);

        $carId = $request->routeInt('id');
        $redirect = $this->manageUrl($context, 'cars/' . $carId . '/edit');

        try {
            $result = $this->cars->addImages($context, $carId, $request->fileList('images'));

            if ($result['stored'] > 0) {
                Flash::success($result['stored'] . ' image(s) uploaded.');
            }

            foreach ($result['errors'] as $error) {
                Flash::warning($error);
            }

            if ($result['stored'] === 0 && $result['errors'] === []) {
                Flash::warning('No images were selected.');
            }
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($redirect);
    }

    /** POST /manage/{org}/cars/{id}/images/{imageId}/primary */
    public function makeImagePrimary(Request $request): Response
    {
        $context = $this->organization($request);
        $carId = $request->routeInt('id');

        try {
            $this->cars->makeImagePrimary($context, $carId, $request->routeInt('imageId'));

            Flash::success('Primary image updated.');
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'cars/' . $carId . '/edit'));
    }

    /** POST /manage/{org}/cars/{id}/images/{imageId}/delete */
    public function deleteImage(Request $request): Response
    {
        $context = $this->organization($request);
        $carId = $request->routeInt('id');

        try {
            $this->cars->deleteImage($context, $carId, $request->routeInt('imageId'));

            Flash::success('Image removed.');
        } catch (CarException $e) {
            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'cars/' . $carId . '/edit'));
    }

    /** POST /manage/{org}/cars/{id}/images/reorder */
    public function reorderImages(Request $request): Response
    {
        $context = $this->organization($request);
        $carId = $request->routeInt('id');

        try {
            $this->cars->reorderImages(
                $context,
                $carId,
                array_map('intval', $request->inputArray('order'))
            );

            if ($request->expectsJson()) {
                return Response::json(['ok' => true]);
            }

            Flash::success('Image order updated.');
        } catch (CarException $e) {
            if ($request->expectsJson()) {
                return Response::json(['error' => $e->getMessage()], 422);
            }

            Flash::error($e->getMessage());
        }

        return $this->redirect($this->manageUrl($context, 'cars/' . $carId . '/edit'));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private function requireCar(OrganizationContext $context, int $carId): array
    {
        $car = $this->carRepository->findInOrganization($carId, $context->organizationId());

        if ($car === null) {
            throw HttpException::notFound('Car not found in this organization.');
        }

        return $car;
    }

    private function statusFilter(Request $request): ?string
    {
        $status = $request->queryParam('status');

        return is_string($status) && in_array($status, CarService::statuses(), true) ? $status : null;
    }

    private function validateCar(Request $request, OrganizationContext $context, ?int $exceptId = null): \Rentivo\Validation\Validator
    {
        $validator = $this->validate($request, [
            'brand'        => 'required|max:80',
            'model'        => 'required|max:80',
            'year'         => 'required|int|min_value:1950|max_value:' . ((int) date('Y') + 2),
            'daily_rate'   => 'required|money|min_fils:1',
            'transmission' => 'required|in:' . implode(',', array_keys(CarService::transmissions())),
            'fuel_type'    => 'required|in:' . implode(',', array_keys(CarService::fuelTypes())),
            'seats'        => 'required|int|min_value:1|max_value:20',
            'doors'        => 'required|int|min_value:1|max_value:8',
            'status'       => 'required|in:' . implode(',', CarService::statuses()),
            'category_id'  => 'nullable|int',
            'location_id'  => 'nullable|int',
            'plate_number' => 'nullable|max:32',
            'vin'          => 'nullable|max:32',
            'mileage'      => 'nullable|int|min_value:0',
            'color'        => 'nullable|max:40',
            'description'  => 'nullable|max:5000',
        ], [
            'daily_rate'  => 'Daily rate',
            'category_id' => 'Category',
            'location_id' => 'Location',
            'vin'         => 'VIN',
        ]);

        // Category and location must belong to this organization.
        $categoryId = $validator->value('category_id');

        if ($categoryId !== null
            && (int) $categoryId > 0
            && !$this->categories->belongsToOrganization((int) $categoryId, $context->organizationId())
        ) {
            $validator->addError('category_id', 'Select a category from your organization.');
        }

        $locationId = $validator->value('location_id');

        if ($locationId !== null
            && (int) $locationId > 0
            && $this->locations->findInOrganization((int) $locationId, $context->organizationId()) === null
        ) {
            $validator->addError('location_id', 'Select a location from your organization.');
        }

        return $validator;
    }

    /** @return array<string,mixed> */
    private function carData(\Rentivo\Validation\Validator $validator): array
    {
        $categoryId = (int) ($validator->value('category_id') ?? 0);
        $locationId = (int) ($validator->value('location_id') ?? 0);
        $mileage = $validator->value('mileage');

        return [
            'brand'           => (string) $validator->value('brand'),
            'model'           => (string) $validator->value('model'),
            'year'            => (int) $validator->value('year'),
            'daily_rate_fils' => (int) $validator->value('daily_rate'),
            'transmission'    => (string) $validator->value('transmission'),
            'fuel_type'       => (string) $validator->value('fuel_type'),
            'seats'           => (int) $validator->value('seats'),
            'doors'           => (int) $validator->value('doors'),
            'status'          => (string) $validator->value('status'),
            'category_id'     => $categoryId > 0 ? $categoryId : null,
            'location_id'     => $locationId > 0 ? $locationId : null,
            'plate_number'    => $validator->value('plate_number'),
            'vin'             => $validator->value('vin'),
            'mileage'         => $mileage === null ? null : (int) $mileage,
            'color'           => $validator->value('color'),
            'description'     => $validator->value('description'),
        ];
    }
}
