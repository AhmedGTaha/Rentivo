<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\CategoryRepository;
use Rentivo\Repositories\FavoriteRepository;
use Rentivo\Repositories\OrganizationRepository;
use Rentivo\Security\Authorization;

/**
 * The public homepage. No authentication of any kind is required.
 */
final class HomeController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private CarRepository $cars,
        private OrganizationRepository $organizations,
        private CategoryRepository $categories,
        private FavoriteRepository $favorites
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** GET / */
    public function index(Request $request): Response
    {
        $userId = $this->auth->id();

        return $this->render('public/home', [
            'title'           => 'Premium car rental across Bahrain',
            'metaDescription' => 'Browse and book cars from trusted rental agencies across Bahrain '
                . 'in one premium marketplace.',
            'featuredCars'    => $this->cars->featuredPublic(6),
            'featuredAgencies' => $this->organizations->featured(4),
            'categories'      => array_slice($this->categories->distinctPublicNames(), 0, 8),
            'brands'          => array_slice($this->cars->distinctPublicBrands(), 0, 10),
            'favoriteIds'     => $userId === null ? [] : $this->favorites->carIdsFor($userId),
            // The homepage lays out its own full-bleed sections.
            'fullWidth'       => true,
        ]);
    }
}
