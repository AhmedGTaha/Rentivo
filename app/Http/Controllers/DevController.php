<?php

declare(strict_types=1);

namespace Rentivo\Http\Controllers;

use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Response;
use Rentivo\Support\Config;

/**
 * The development component gallery.
 *
 * Returns 404 outside local/development environments so it can never be
 * reached in production.
 */
final class DevController extends Controller
{
    /** GET /dev/components */
    public function components(Request $request): Response
    {
        if (!Config::isLocal()) {
            throw HttpException::notFound();
        }

        return $this->render('dev/components', [
            'title' => 'Component gallery',
        ], 'public');
    }
}
