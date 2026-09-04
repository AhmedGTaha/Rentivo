<?php

declare(strict_types=1);

/**
 * The single entry point for every web request.
 *
 * Point your web server's document root at this directory; everything outside
 * public/ (including storage/private) is then unreachable over HTTP.
 */

use Rentivo\Bootstrap;
use Rentivo\Http\Kernel;
use Rentivo\Http\Request;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$app = Bootstrap::boot($basePath);

$kernel = new Kernel($app);

$kernel->handle(Request::capture())->send();
