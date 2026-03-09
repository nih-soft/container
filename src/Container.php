<?php

declare(strict_types=1);

namespace NIH\Container;

use Psr\Container\ContainerInterface;
use Throwable;

final class Container extends ContainerData implements ContainerInterface
{
    private static array $groupCallbacks;
    private readonly Instantiator $instantiator;

    public function __construct(
        ContainerConfig $config,
    )
    {
        $config->value(__CLASS__, $this);
        $config->alias(ContainerInterface::class, __CLASS__);

        $config->value(
            Instantiator::class,
            $this->instantiator = new Instantiator($this, $config->cacheReflections, $config->mode, $config->maxDepth)
        );

        $this->aliases = &$config->aliases;
        $this->definitions = &$config->definitions;
        $this->groups = &$config->groups;
        $this->services = &$config->services;
        $this->shared = $config->shared;

        self::$groupCallbacks ??= [
            'inherit' => static fn(string $id, string $group) => is_a($id, $group, true),
            'namespace' => static fn(string $id, string $group) => str_starts_with($id, $group . '\\'),
            'regex' => static fn(string $id, string $group) => preg_match($group, $id),
        ];
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
        return $this->services[$id]
            ?? $this->services[$id = $this->aliases[$id] ?? $id]
            ?? $this->getInternal($id);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws ContainerNotFoundException  No entry was found for **this** identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    private function getInternal(string $id): mixed
    {
        $definition = $this->getDefinition($id);

        if ($definition === null) {
            throw new ContainerNotFoundException(sprintf('No definition or class found or resolvable for "%s".', $id));
        }

        $instance = $this->newFromDefinition($definition);
        if ($definition->shared) {
            //set cache
            $this->services[$id] = $instance;
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
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        //can return only simple types
        if (isset($this->services[$id]) && !isset($this->definitions[$id]) && !is_object($this->services[$id])) {
            return $this->services[$id];
        }

        $definition = $this->getDefinition($id);

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
        return isset($this->services[$id]) || isset($this->services[$id = $this->aliases[$id] ?? $id]) || $this->getDefinition($id);
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
        $definition = $this->getDefinition($id = $this->aliases[$id] ?? $id);

        return ($definition !== null) && isset($this->services[$definition->id]) && $definition->shared;
    }

    private function getDefinition(string $id): ?Definition
    {
        if (isset($this->definitions[$id])) {
            return $this->definitions[$id];
        }
        if (!class_exists($id)) {
            return null;
        }

        /**
         * @param string $groupName
         * @param Closure $callback
         */
        foreach (self::$groupCallbacks as $groupName => $callback) {
            if (isset($this->groups[$groupName]) && is_array($this->groups[$groupName])) {
                foreach ($this->groups[$groupName] as $group => $definition) {
                    if ($callback($id, $group)) {
                        return $this->definitions[$id] = $this->fromGroupDefinition($id, $definition);
                    }
                }
            }
        }

        return $this->definitions[$id] = new Definition(
            id: $id,
            auto: true,
            shared: $this->shared,
            mode: Mode::Default,
        );
    }

    private function fromGroupDefinition(string $id, Definition $definition): ?Definition
    {
        $target = $definition->to;

        if ($definition->to && is_string($definition->to)) {
            if (!is_callable($definition->to)) {
                return null;
            }
            $target = ($definition->to)(...);
        }

        return new Definition(
            id: $id,
            auto: $definition->auto,
            shared: $definition->shared,
            mode: $definition->mode,
            to: $target,
            args: $definition->args,
        );
    }

}