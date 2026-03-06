<?php

declare(strict_types=1);

namespace NIH\Container;

use Psr\Container\ContainerInterface;
use Throwable;

final class Container implements ContainerInterface
{
    private array $instances = [];

    private readonly Instantiator $instantiator;

    public function __construct(
        private readonly ContainerConfig $config,
    )
    {
        $config->value(__CLASS__, $this);

        if (!$this->has(ContainerInterface::class)) {
            $config->alias(ContainerInterface::class, __CLASS__);
        }

        $config->value(
            Instantiator::class,
            $this->instantiator = new Instantiator($this, $config->cacheReflections, $config->mode, $config->maxDepth)
        );
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws ContainerNotFoundException  No entry was found for **this** identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function get(string $id): mixed
    {
        //fast path from cache (values and shared entries). Only for benchmark
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $config = $this->config;
        $id = $config->getRealId($id);

        //fast path from cache (aliased values and shared entries)
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $value = $config->getValue($id);

        if ($value !== null) {
            //set cache and return value
            return $this->instances[$id] = $value;
        }

        $definition = $config->getDefinition($id);

        if ($definition === null) {
            throw new ContainerNotFoundException(sprintf('No definition or class found or resolvable for "%s".', $id));
        }

        $instance = $this->newFromDefinition($definition);
        if ($definition->shared) {
            //set cache
            $this->instances[$id] = $instance;
        }
        return $instance;
    }

    /**
     * Finds an entry of the container by its identifier and returns a new instance
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws ContainerNotFoundException  No entry was found for **this** identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function new(string $id): mixed
    {
        $config = $this->config;
        $id = $config->getRealId($id);

        $value = $config->getValue($id);
        if ($value !== null && !is_object($value)) {
            //can return only simple types
            return $value;
        }

        $definition = $config->getDefinition($id);

        if ($definition === null) {
            throw new ContainerNotFoundException(sprintf('No definition or class found or resolvable for "%s".', $id));
        }

        return $this->newFromDefinition($definition);
    }

    /**
     * Instantiate an entry by definition and returns it.
     *
     * @param Definition $definition Definition of the entry to look for.
     * @return mixed Entry.
     * @throws ContainerException Error while retrieving the entry.
     */
    private function newFromDefinition(Definition $definition): mixed
    {
        try {
            $target = $definition->to ?: $definition->id;

            return (is_string($target))
                ? $this->instantiator->make(
                    class: $target,
                    arguments: $definition->args,
                    mode: $definition->mode,
                    auto: $definition->auto,
                ) : $this->instantiator->invoke(
                    callable: $target,
                    arguments: $definition->args,
                    mode: $definition->mode,
                    auto: $definition->auto,
                );

        } catch (Throwable $e) {
            throw new ContainerException(message: sprintf(
                'Error while resolving %s. %s',
                $definition->id,
                $e->getMessage()
            ), previous: $e);
        }
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool
    {
        $config = $this->config;
        $id = $config->getRealId($id);

        return isset($this->instances[$id]) || $config->getValue($id) !== null || $config->getDefinition($id);
    }

    /**
     * Returns true if the container has a shared instance for the given non aliased identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function hasInstance(string $id): bool
    {
        $config = $this->config;
        $id = $config->getRealId($id);
        $definition = $config->getDefinition($id);

        return ($definition !== null) && isset($this->instances[$definition->id]) && $definition->shared;
    }

}