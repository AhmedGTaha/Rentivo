<?php

declare(strict_types=1);

namespace Rentivo\Http;

use Closure;

/**
 * Small regex-backed router.
 *
 * Routes are registered as literal paths with optional {parameter} segments.
 * Handlers are either closures or [ControllerClass::class, 'method'] pairs
 * resolved lazily by the container.
 */
final class Router
{
    /** @var array<string, list<array{pattern:string,parameters:list<string>,handler:mixed,name:?string}>> */
    private array $routes = [];

    /** @var array<string,string> */
    private array $namedRoutes = [];

    private ?string $groupPrefix = null;

    public function get(string $path, mixed $handler, ?string $name = null): self
    {
        return $this->add('GET', $path, $handler, $name);
    }

    public function post(string $path, mixed $handler, ?string $name = null): self
    {
        return $this->add('POST', $path, $handler, $name);
    }

    public function put(string $path, mixed $handler, ?string $name = null): self
    {
        return $this->add('PUT', $path, $handler, $name);
    }

    public function delete(string $path, mixed $handler, ?string $name = null): self
    {
        return $this->add('DELETE', $path, $handler, $name);
    }

    /** Registers routes under a shared path prefix. */
    public function group(string $prefix, Closure $callback): self
    {
        $previous = $this->groupPrefix;
        $this->groupPrefix = ($previous ?? '') . '/' . trim($prefix, '/');

        $callback($this);

        $this->groupPrefix = $previous;

        return $this;
    }

    public function add(string $method, string $path, mixed $handler, ?string $name = null): self
    {
        $path = $this->prefixed($path);

        [$pattern, $parameters] = $this->compile($path);

        $this->routes[strtoupper($method)][] = [
            'pattern'    => $pattern,
            'parameters' => $parameters,
            'handler'    => $handler,
            'name'       => $name,
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = $path;
        }

        return $this;
    }

    private function prefixed(string $path): string
    {
        $path = '/' . trim($path, '/');

        if ($this->groupPrefix !== null) {
            $path = rtrim($this->groupPrefix, '/') . ($path === '/' ? '' : $path);
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * @return array{0:string,1:list<string>}
     */
    private function compile(string $path): array
    {
        $parameters = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(int|slug|any))?\}/',
            static function (array $matches) use (&$parameters): string {
                $parameters[] = $matches[1];

                return match ($matches[2] ?? 'any') {
                    'int'  => '(\d+)',
                    'slug' => '([a-z0-9][a-z0-9-]*)',
                    default => '([^/]+)',
                };
            },
            preg_quote($path, '#')
        ) ?? '';

        // preg_quote escapes the braces we just replaced; undo that safely by
        // quoting first and then substituting on the quoted form.
        $regex = str_replace(['\{', '\}', '\-'], ['{', '}', '-'], $regex);

        return ['#^' . $regex . '$#', $parameters];
    }

    /**
     * Matches a request.
     *
     * @return array{handler:mixed,parameters:array<string,string>}
     *
     * @throws HttpException 404 when no path matches, 405 when the path
     *                       exists under a different method.
     */
    public function match(Request $request): array
    {
        $path = $request->path();
        $method = $request->method();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) === 1) {
                array_shift($matches);

                return [
                    'handler'    => $route['handler'],
                    'parameters' => array_combine($route['parameters'], $matches) ?: [],
                ];
            }
        }

        // Distinguish "wrong method" from "no such route".
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }

            foreach ($routes as $route) {
                if (preg_match($route['pattern'], $path) === 1) {
                    throw HttpException::methodNotAllowed();
                }
            }
        }

        throw HttpException::notFound();
    }

    /**
     * Builds a URL from a named route.
     *
     * @param array<string,string|int> $parameters
     */
    public function route(string $name, array $parameters = []): string
    {
        $path = $this->namedRoutes[$name] ?? '/';

        foreach ($parameters as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '(?::[a-z]+)?\}/', rawurlencode((string) $value), $path) ?? $path;
        }

        return $path;
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /** @return array<string, list<string>> Method => list of registered paths. */
    public function debugList(): array
    {
        $list = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $list[$method][] = $route['pattern'];
            }
        }

        return $list;
    }
}
