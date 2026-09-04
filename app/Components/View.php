<?php

declare(strict_types=1);

namespace Rentivo\Components;

use RuntimeException;
use Throwable;

/**
 * Server-side template renderer.
 *
 * Templates are plain PHP files under views/. A page template is rendered to
 * a string and then, optionally, wrapped in a layout that receives it as
 * $content. Templates never query the database; they only render the data
 * handed to them.
 */
final class View
{
    private static ?self $instance = null;

    /** @var array<string,mixed> Data shared with every template. */
    private array $shared = [];

    public function __construct(private string $viewPath)
    {
    }

    public static function setInstance(self $view): void
    {
        self::$instance = $view;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('View renderer has not been initialised.');
        }

        return self::$instance;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public function shareMany(array $data): void
    {
        $this->shared = $data + $this->shared;
    }

    /** @return array<string,mixed> */
    public function shared(): array
    {
        return $this->shared;
    }

    public function sharedValue(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    public function exists(string $template): bool
    {
        return is_file($this->resolve($template));
    }

    /**
     * Renders a page template, optionally wrapped in a layout.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($this->resolve($template), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile(
            $this->resolve('layouts/' . $layout),
            $data + ['content' => $content]
        );
    }

    /**
     * Renders a reusable component and returns its markup.
     *
     * @param array<string,mixed> $props
     */
    public function component(string $name, array $props = []): string
    {
        return $this->renderFile($this->resolve('components/' . $name), $props);
    }

    private function resolve(string $template): string
    {
        $template = str_replace(['\\', '..'], ['/', ''], $template);

        return rtrim($this->viewPath, '/\\') . '/' . trim($template, '/') . '.php';
    }

    /** @param array<string,mixed> $data */
    private function renderFile(string $path, array $data): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . basename($path, '.php'));
        }

        $level = ob_get_level();
        ob_start();

        try {
            // Shared data is available everywhere; per-render data wins.
            (function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, $data + $this->shared);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }
}
