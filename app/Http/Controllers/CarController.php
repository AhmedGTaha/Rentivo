<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\CarFilters;
use Rentivo\Repositories\CarImageRepository;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\FavoriteRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Security\Authorization;
use Rentivo\Services\AvailabilityService;
use Rentivo\Services\CarService;
use Rentivo\Services\PricingService;
use Rentivo\Support\Config;
use Rentivo\Support\Pagination;

/**
 * Public car browsing and car detail pages.
 *
 * All filtering, sorting and pagination happen server-side and live entirely
 * in the query string, so refresh, back/forward and link sharing all behave.
 */
final class CarController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private CarRepository $cars,
        private CarImageRepository $images,
        private OrganizationRepository $organizations,
        private CategoryRepository $categories,
        private LocationRepository $locations,
        private FavoriteRepository $favorites,
        private AvailabilityService $availability,
        private PricingService $pricing
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /cars */
    public function index(Request $request): Response
    {
        $filters = CarFilters::fromQuery($request->query());

        return $this->renderBrowse($request, $filters, null);
    }

    /** GET /cars/{slug} */
    public function show(Request $request): Response
    {
        $car = $this->cars->findPublicBySlug((string) $request->route('slug'));

        if ($car === null) {
            throw HttpException::notFound('That car is no longer listed.');
        }

        return $this->renderDetail($request, $car);
    }

    /** GET /agency/{slug}/cars/{carSlug} */
    public function showForAgency(Request $request): Response
    {
        $organization = $this->organizations->findActiveBySlug((string) $request->route('slug'));

        if ($organization === null) {
            throw HttpException::notFound('That agency is no longer listed.');
        }

        $car = $this->cars->findPublicBySlug(
            (string) $request->route('carSlug'),
            (int) $organization['id']
        );

        if ($car === null) {
            throw HttpException::notFound('That car is no longer listed.');
        }

        return $this->renderDetail($request, $car, $organization);
    }

    /**
     * Shared browse rendering used by /cars and /agency/{slug}/cars.
     *
     * @param array<string,mixed>|null $organization
     */
    public function renderBrowse(Request $request, CarFilters $filters, ?array $organization): Response
    {
        $total = $this->cars->countPublic($filters);

        $pagination = new Pagination(
            $filters->page,
            (int) Config::get('pagination.cars_per_page', 12),
            $total
        );

        $cars = $this->cars->searchPublic($filters, $pagination);
        $userId = $this->auth->id();

        $basePath = $organization === null
            ? '/cars'
            : '/agency/' . $organization['slug'] . '/cars';

        return $this->render('public/browse', [
            'title'        => $organization === null
                ? 'Browse cars'
                : 'Cars from ' . $organization['name'],
            'metaDescription' => 'Search, filter and compare rental cars across Bahrain.',
            'cars'         => $cars,
            'total'        => $total,
            'filters'      => $filters,
            'pagination'   => $pagination,
            'basePath'     => $basePath,
            'organization' => $organization,
            'agencies'     => $organization === null ? $this->organizations->selectableForFilters() : [],
            'brands'       => $this->cars->distinctPublicBrands(),
            'categories'   => $this->categories->distinctPublicNames(),
            'locationNames' => $this->locations->distinctPublicNames(),
            'favoriteIds'  => $userId === null ? [] : $this->favorites->carIdsFor($userId),
            'sortLabels'   => CarFilters::sortLabels(),
            'transmissions' => CarService::transmissions(),
            'fuelTypes'    => CarService::fuelTypes(),
            // The browse page manages its own header band and layout grid.
            'fullWidth'    => true,
            'accentColor'  => $organization['primary_color'] ?? null,
        ]);
    }

    /**
     * Shared detail rendering.
     *
     * @param array<string,mixed>      $car
     * @param array<string,mixed>|null $organization
     */
    private function renderDetail(Request $request, array $car, ?array $organization = null): Response
    {
        $userId = $this->auth->id();
        $filters = CarFilters::fromQuery($request->query());

        $availability = null;

        // If the visitor supplied dates, answer the availability question and
        // quote the price before they ever sign in.
        if ($filters->hasDateWindow()) {
            $check = $this->availability->check($car, $filters->pickupAt, $filters->returnAt);

            $availability = [
                'checked'   => true,
                'available' => $check['available'],
                'reason'    => $check['reason'],
                'quote'     => $check['available']
                    ? $this->pricing->quote($filters->pickupAt, $filters->returnAt, (int) $car['daily_rate_fils'])
                    : null,
            ];
        }

        $organizationId = (int) $car['organization_id'];

        return $this->render('public/car-detail', [
            'title'           => $car['brand'] . ' ' . $car['model'] . ' ' . $car['year'],
            'metaDescription' => 'Rent a ' . $car['year'] . ' ' . $car['brand'] . ' ' . $car['model']
                . ' from ' . $car['organization_name'] . ' on Rentivo.',
            'car'             => $car,
            'images'          => $this->images->listForCar((int) $car['id']),
            'organization'    => $organization,
            'locations'       => $this->locations->publicForOrganization($organizationId),
            'relatedCars'     => $this->cars->relatedPublic(
                (int) $car['id'],
                $organizationId,
                $car['category_id'] === null ? null : (int) $car['category_id']
            ),
            'isFavorite'      => $userId !== null && $this->favorites->exists($userId, (int) $car['id']),
            'filters'         => $filters,
            'availability'    => $availability,
            // The detail page owns its container and sticky mobile bar.
            'fullWidth'       => true,
            'accentColor'     => $car['organization_color'] ?? null,
        ]);
    }
}
