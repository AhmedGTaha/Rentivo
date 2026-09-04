<?php

declare(strict_types=1);

namespace Rentivo;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
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
            return $this->instances[$id] = $this->autowire($id);
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    /**
     * Constructor injection for classes that are pure composition of already
     * registered services — controllers, in practice.
     *
     * Services themselves are always bound explicitly in Application so their
     * wiring stays visible; this only removes controller boilerplate.
     */
    private function autowire(string $id): object
    {
        if (!class_exists($id)) {
            throw new RuntimeException('Service not registered: ' . $id);
        }

        $reflection = new ReflectionClass($id);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException('Cannot instantiate: ' . $id);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $id();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Cannot resolve parameter $%s of %s.',
                    $parameter->getName(),
                    $id
                ));
            }

            $arguments[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
