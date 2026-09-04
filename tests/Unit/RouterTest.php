<?php

declare(strict_types=1);

namespace Rentivo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rentivo\Http\HttpException;
use Rentivo\Http\Request;
use Rentivo\Http\Router;

/**
 * Router behaviour: parameter matching, method restriction and 404/405.
 */
final class RouterTest extends TestCase
{
    private function request(string $method, string $path): Request
    {
        return new Request($method, Request::normalisePath($path));
    }

    public function testMatchesALiteralRoute(): void
    {
        $router = new Router();
        $router->get('/cars', 'cars.index');

        $match = $router->match($this->request('GET', '/cars'));

        self::assertSame('cars.index', $match['handler']);
        self::assertSame([], $match['parameters']);
    }

    public function testMatchesASingleParameter(): void
    {
        $router = new Router();
        $router->get('/cars/{slug}', 'cars.show');

        $match = $router->match($this->request('GET', '/cars/toyota-corolla-2024'));

        self::assertSame('cars.show', $match['handler']);
        self::assertSame(['slug' => 'toyota-corolla-2024'], $match['parameters']);
    }

    public function testMatchesMultipleParameters(): void
    {
        $router = new Router();
        $router->get('/agency/{slug}/cars/{carSlug}', 'agency.car');

        $match = $router->match($this->request('GET', '/agency/manama-motors/cars/honda-accord-2023'));

        self::assertSame([
            'slug'    => 'manama-motors',
            'carSlug' => 'honda-accord-2023',
        ], $match['parameters']);
    }

    public function testIntegerConstraintOnlyMatchesDigits(): void
    {
        $router = new Router();
        $router->get('/manage/{org}/cars/{id:int}/edit', 'cars.edit');

        $match = $router->match($this->request('GET', '/manage/speedy/cars/72/edit'));
        self::assertSame(['org' => 'speedy', 'id' => '72'], $match['parameters']);

        $this->expectException(HttpException::class);
        $router->match($this->request('GET', '/manage/speedy/cars/abc/edit'));
    }

    public function testParameterDoesNotMatchAcrossASlash(): void
    {
        $router = new Router();
        $router->get('/cars/{slug}', 'cars.show');

        $this->expectException(HttpException::class);

        $router->match($this->request('GET', '/cars/one/two'));
    }

    public function testGroupPrefixesRoutes(): void
    {
        $router = new Router();
        $router->group('/account', static function (Router $router): void {
            $router->get('/bookings', 'account.bookings');
            $router->get('/bookings/{reference}', 'account.booking');
        });

        self::assertSame('account.bookings', $router->match($this->request('GET', '/account/bookings'))['handler']);

        $match = $router->match($this->request('GET', '/account/bookings/BK-2026-000001'));
        self::assertSame(['reference' => 'BK-2026-000001'], $match['parameters']);
    }

    public function testUnknownPathThrows404(): void
    {
        $router = new Router();
        $router->get('/cars', 'cars.index');

        try {
            $router->match($this->request('GET', '/nope'));
            self::fail('Expected a 404 to be thrown.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status());
        }
    }

    /** A path that exists under another verb is 405, not 404. */
    public function testWrongMethodThrows405(): void
    {
        $router = new Router();
        $router->post('/logout', 'logout');

        try {
            $router->match($this->request('GET', '/logout'));
            self::fail('Expected a 405 to be thrown.');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status());
        }
    }

    public function testTrailingSlashesAreNormalised(): void
    {
        $router = new Router();
        $router->get('/cars', 'cars.index');

        self::assertSame('cars.index', $router->match($this->request('GET', '/cars/'))['handler']);
    }

    public function testRootPathStillMatches(): void
    {
        $router = new Router();
        $router->get('/', 'home');

        self::assertSame('home', $router->match($this->request('GET', '/'))['handler']);
    }

    public function testNamedRouteGeneration(): void
    {
        $router = new Router();
        $router->get('/manage/{org}/cars/{id:int}/edit', 'handler', 'manage.cars.edit');

        self::assertSame(
            '/manage/speedy-rentals/cars/72/edit',
            $router->route('manage.cars.edit', ['org' => 'speedy-rentals', 'id' => 72])
        );
    }

    /**
     * Regex metacharacters in a literal segment must be treated literally.
     */
    public function testLiteralSegmentsAreQuoted(): void
    {
        $router = new Router();
        $router->get('/auth/google/callback', 'callback');

        self::assertSame('callback', $router->match($this->request('GET', '/auth/google/callback'))['handler']);

        $this->expectException(HttpException::class);
        $router->match($this->request('GET', '/authXgoogle/callback'));
    }
}
