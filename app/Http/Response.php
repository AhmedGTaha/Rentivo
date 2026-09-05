<?php

declare(strict_types=1);

namespace Rentivo\Http;

use InvalidArgumentException;
use Rentivo\Support\Config;

/**
 * Response value object. Controllers return one of these; the kernel is the
 * only place that actually writes headers and body to the client.
 */
final class Response
{
    /**
     * The only origins the application is ever allowed to redirect out to.
     * Google's OAuth 2.0 authorization endpoint lives on this host.
     *
     * @var list<string>
     */
    private const TRUSTED_EXTERNAL_HOSTS = ['accounts.google.com'];

    /** @param array<string,string> $headers */
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Redirects to an application path.
     *
     * Only relative in-app paths are accepted so a caller can never turn this
     * into an open redirect; anything else falls back to the homepage.
     */
    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => self::safeLocation($location)]);
    }

    public static function safeLocation(string $location): string
    {
        $location = trim($location);

        // Reject absolute URLs, protocol-relative URLs, and control characters.
        if ($location === ''
            || !str_starts_with($location, '/')
            || str_starts_with($location, '//')
            || preg_match('/[\r\n\x00]/', $location) === 1
        ) {
            $appUrl = (string) Config::get('url', '');
            if ($location !== '' && $appUrl !== '' && str_starts_with($location, $appUrl . '/')) {
                return substr($location, strlen($appUrl));
            }

            return '/';
        }

        return $location;
    }

    /**
     * Redirects to an explicitly trusted external origin.
     *
     * This is the only way out of the application, and it exists solely for
     * the Google OAuth hand-off. The destination is validated against a fixed
     * host allow-list rather than anything caller supplied, so it can never
     * become a general purpose open redirect.
     *
     * @throws InvalidArgumentException When the destination is not trusted.
     */
    public static function externalRedirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => self::trustedExternalLocation($url)]);
    }

    /**
     * Validates an absolute URL against the trusted external host list.
     *
     * @throws InvalidArgumentException When the destination is not trusted.
     */
    public static function trustedExternalLocation(string $url): string
    {
        // Checked before trimming so a trailing CRLF cannot be laundered away.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new InvalidArgumentException('External redirect contains control characters.');
        }

        $candidate = trim($url);

        if ($candidate === '') {
            throw new InvalidArgumentException('External redirect target is empty.');
        }

        $parts = parse_url($candidate);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('External redirect target is not a valid absolute URL.');
        }

        // Embedded credentials make the real origin hard to read; refuse them.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('External redirect target must not carry credentials.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new InvalidArgumentException('External redirects must use HTTPS.');
        }

        if (!in_array(strtolower($parts['host']), self::TRUSTED_EXTERNAL_HOSTS, true)) {
            throw new InvalidArgumentException('External redirect host is not trusted.');
        }

        return $candidate;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            // Defensive headers applied to every response.
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        echo $this->content;
    }
}
