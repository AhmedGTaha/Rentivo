<?php

declare(strict_types=1);

namespace Rentivo;

use Closure;
use RuntimeException;

/**
 * Very small service container.
 *
 * Services are registered as factories and resolved once per request. There is
 * no autowiring magic: every binding is explicit and lives in Application, so
 * the object graph stays readable.
 */
final class Container
{
    /** @var array<string, Closure(self):mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @param Closure(self):mixed $factory */
    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException('Service not registered: ' . $id);
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
