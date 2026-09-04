<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Auth\SessionAuth;
use Rentivo\Components\View;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Repositories\CarRepository;
use Rentivo\Repositories\FavoriteRepository;
use Rentivo\Security\Authorization;
use Rentivo\Support\Flash;

/**
 * Favorite (saved car) toggling.
 *
 * Responds with JSON to the fetch-based FavoriteButton and with a redirect to
 * plain form submissions, so the feature works without JavaScript.
 */
final class FavoriteController extends Controller
{
    public function __construct(
        View $view,
        SessionAuth $auth,
        Authorization $authorization,
        private FavoriteRepository $favorites,
        private CarRepository $cars
    ) {
        parent::__construct($view, $auth, $authorization);
    }

    /** POST /favorites/{slug}/toggle */
    public function toggle(Request $request): Response
    {
        $userId = $this->requireUserId();

        // Resolved through the public predicate so unlisted cars stay unlisted.
        $car = $this->cars->findPublicBySlug((string) $request->route('slug'));

        if ($car === null) {
            throw HttpException::notFound('That car is no longer listed.');
        }

        $carId = (int) $car['id'];

        if ($this->favorites->exists($userId, $carId)) {
            $this->favorites->remove($userId, $carId);
            $isFavorite = false;
            $message = 'Removed from your saved cars.';
        } else {
            $this->favorites->add($userId, $carId);
            $isFavorite = true;
            $message = 'Saved to your favorites.';
        }

        if ($request->expectsJson()) {
            return Response::json([
                'favorite' => $isFavorite,
                'message'  => $message,
                'count'    => $this->favorites->countForUser($userId),
            ]);
        }

        Flash::success($message);

        return $this->back($request, '/cars/' . rawurlencode((string) $car['slug']));
    }

    /** POST /account/favorites/{slug}/remove */
    public function remove(Request $request): Response
    {
        $userId = $this->requireUserId();

        $car = $this->cars->findPublicBySlug((string) $request->route('slug'));

        if ($car !== null) {
            $this->favorites->remove($userId, (int) $car['id']);
        }

        Flash::success('Removed from your saved cars.');

        return $this->redirect('/account/favorites');
    }
}
