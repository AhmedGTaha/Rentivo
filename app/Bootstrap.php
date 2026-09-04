<?php

declare(strict_types=1);

namespace Rentivo;

use Rentivo\Components\View;
use Rentivo\Support\Config;
use Rentivo\Support\Env;
use Rentivo\Support\Logger;

/**
 * Shared bootstrap for every entry point (web, CLI scripts, tests).
 *
 * Loads the environment, builds configuration, sets the process timezone, and
 * prepares the logger and view renderer. Nothing here touches the database, so
 * CLI tooling can boot even when MySQL is unavailable.
 */
final class Bootstrap
{
    private static ?Application $application = null;

    public static function boot(string $basePath, bool $fresh = false): Application
    {
        if (self::$application !== null && !$fresh) {
            return self::$application;
        }

        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');

        Env::load($basePath);
        Config::setInstance(require $basePath . '/config/app.php');

        date_default_timezone_set('UTC');

        Logger::setDirectory($basePath . '/storage/logs');

        $view = new View($basePath . '/views');
        $view->shareMany([
            'appName'    => (string) Config::get('name', 'Rentivo'),
            'publicPath' => $basePath . '/public',
        ]);
        View::setInstance($view);

        return self::$application = new Application($basePath, $view);
    }

    public static function reset(): void
    {
        self::$application = null;
    }
}
