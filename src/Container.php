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
        $this->instances[__CLASS__] = $this;

        if (!$this->has(ContainerInterface::class)) {
            $config->alias(ContainerInterface::class, __CLASS__);
        }

        $this->instantiator = new Instantiator($this, $config->cacheReflections, $config->mode, $config->maxDepth);
        $config->value(Instantiator::class, $this->instantiator);
        $this->instances[Instantiator::class] = $this->instantiator;
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
        $definition = $this->config->getDefinition($id);

        if ($definition === null) {
            throw new ContainerNotFoundException();
        }

        $id = $definition->id;

        if ($definition->shared && isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        $instance = $this->newFromDefinition($definition);
        if ($definition->shared) {
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
        $definition = $this->config->getDefinition($id);

        if ($definition === null) {
            throw new ContainerNotFoundException();
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
            throw new ContainerException(message: "Error for key $definition->id. Invalid definition?", previous: $e);
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
        return isset($this->instances[$id]) || $this->config->getDefinition($id);
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
        $definition = $this->config->getDefinition($id);

        return ($definition !== null) && isset($this->instances[$definition->id]) && $definition->shared;
    }

}