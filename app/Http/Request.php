<?php

declare(strict_types=1);

namespace Rentivo\Http;

/**
 * Immutable view of the incoming HTTP request.
 *
 * Controllers read input exclusively through this object so that superglobals
 * are never touched deeper in the application.
 */
final class Request
{
    /** @var array<string,string> */
    private array $routeParameters = [];

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @param array<string,mixed> $files
     * @param array<string,mixed> $server
     * @param array<string,string> $cookies
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private array $files = [],
        private array $server = [],
        private array $cookies = []
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Support method spoofing for HTML forms (PUT/PATCH/DELETE).
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper((string) $_POST['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoofed;
            }
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        return new self(
            $method,
            self::normalisePath($path),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            $_COOKIE
        );
    }

    public static function normalisePath(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /** True for anything that can change server state. */
    public function isStateChanging(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Full path including the original query string. */
    public function fullPath(): string
    {
        $query = $this->query();

        return $this->path . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /** @return array<string,mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string,mixed> */
    public function body(): array
    {
        return $this->body;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function boolean(string $key): bool
    {
        return in_array((string) $this->input($key, ''), ['1', 'on', 'true', 'yes'], true);
    }

    /** @return list<string> */
    public function inputArray(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v) => (string) $v, $value));
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * Normalises PHP's awkward multi-file upload shape into a list of
     * individual file arrays.
     *
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public function fileList(string $key): array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || !isset($file['name'])) {
            return [];
        }

        if (!is_array($file['name'])) {
            return $file['error'] === UPLOAD_ERR_NO_FILE ? [] : [$file];
        }

        $files = [];
        foreach (array_keys($file['name']) as $index) {
            if (($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[] = [
                'name'     => (string) $file['name'][$index],
                'type'     => (string) ($file['type'][$index] ?? ''),
                'tmp_name' => (string) $file['tmp_name'][$index],
                'error'    => (int) $file['error'][$index],
                'size'     => (int) ($file['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    public function server(string $key, ?string $default = null): ?string
    {
        $value = $this->server[$key] ?? $default;

        return $value === null ? null : (string) $value;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server($key);
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        return strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function expectsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json')
            || strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** @param array<string,string> $parameters */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    /** @return array<string,string> */
    public function routeParameters(): array
    {
        return $this->routeParameters;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParameters[$key] ?? $default;
    }

    public function routeInt(string $key): int
    {
        return (int) ($this->routeParameters[$key] ?? 0);
    }
}
