<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\CarFilters;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\FavoriteRepository;
use Rentivo\Repositories\LocationRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Security\Authorization;
use Rentivo\Support\Pagination;

/**
 * Public agency directory and storefronts.
 *
 * Organization branding is applied as an accent only; agencies can never
 * inject markup, script or arbitrary CSS.
 */
final class AgencyController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private OrganizationRepository $organizations,
        private CarRepository $cars,
        private LocationRepository $locations,
        private FavoriteRepository $favorites,
        private CarController $carController
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET /agencies */
    public function index(Request $request): Response
    {
        $search = $request->queryParam('q');
        $search = is_string($search) && $search !== '' ? mb_substr($search, 0, 120) : null;

        $total = $this->organizations->countActive($search);
        $pagination = $this->paginate($request, $total, 12);

        return $this->render('public/agencies', [
            'title'           => 'Rental agencies',
            'metaDescription' => 'Browse trusted car rental agencies operating across Bahrain.',
            'agencies'        => $this->organizations->listActive($pagination, $search),
            'total'           => $total,
            'search'          => $search ?? '',
            'pagination'      => $pagination,
        ]);
    }

    /** GET /agency/{slug} */
    public function show(Request $request): Response
    {
        $organization = $this->requireAgency($request);
        $userId = $this->auth->id();

        $filters = CarFilters::fromQuery([])->forOrganization((int) $organization['id']);
        $pagination = new Pagination(1, 6, $this->cars->countPublic($filters));

        return $this->render('public/agency', [
            'title'           => $organization['name'],
            'metaDescription' => 'Rent cars from ' . $organization['name'] . ' on Rentivo.',
            'organization'    => $organization,
            'cars'            => $this->cars->searchPublic($filters, $pagination),
            'fleetCount'      => $this->cars->countPublicForOrganization((int) $organization['id']),
            'locations'       => $this->locations->publicForOrganization((int) $organization['id']),
            'favoriteIds'     => $userId === null ? [] : $this->favorites->carIdsFor($userId),
            // The storefront renders its own brand header band.
            'fullWidth'       => true,
            'accentColor'     => $organization['primary_color'] ?? null,
        ]);
    }

    /** GET /agency/{slug}/cars */
    public function cars(Request $request): Response
    {
        $organization = $this->requireAgency($request);

        $filters = CarFilters::fromQuery($request->query())
            ->forOrganization((int) $organization['id']);

        return $this->carController->renderBrowse($request, $filters, $organization);
    }

    /** @return array<string,mixed> */
    private function requireAgency(Request $request): array
    {
        $organization = $this->organizations->findActiveBySlug((string) $request->route('slug'));

        if ($organization === null) {
            throw HttpException::notFound('That agency is no longer listed.');
        }

        return $organization;
    }
}
