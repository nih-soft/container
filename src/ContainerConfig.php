<?php

declare(strict_types=1);

namespace NIH\Container;

use Closure;

final class ContainerConfig
{
    private array $groups = [];

    private readonly array $groupCallbacks;

    /** @var Definition[]  */
    private array $definitions = [];

    private array $aliases = [];

    private array $values = [];

    private array $cache = [];

    private readonly DefinitionBuilder $builder;

    private readonly Closure $definitionAttach;

    public function __construct(
        public readonly bool $shared = false,
        public readonly Mode $mode = Mode::Default,
        public readonly bool $cacheReflections = true,
        public readonly int  $maxDepth = 5,
    )
    {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->builder = new DefinitionBuilder();
        $this->groupCallbacks = [
            'inherit' => static fn(string $id, string $group) => is_a($id, $group, true),
            'namespace' => static fn(string $id, string $group) => str_starts_with($id, $group . '\\'),
            'regex' => static fn(string $id, string $group) => preg_match($group, $id),
        ];
        $this->definitionAttach = (function (Definition $definition): DefinitionBuilder {
            $this->definition = $definition;
            return $this;
        })->bindTo($this->builder, $this->builder);
    }

    /**
     * @param class-string $id
     * */
    public function auto(string $id): DefinitionBuilder
    {
        $this->definitions[$id] ??= new Definition(
            id: $id,
            auto: true,
            shared: $this->shared,
            mode: Mode::Default,
        );
        return ($this->definitionAttach)($this->definitions[$id]);
    }

    /**
     * @param class-string $id
     * */
    public function manual(string $id): DefinitionBuilder
    {
        $this->definitions[$id] ??= new Definition(
            id: $id,
            auto: false,
            shared: $this->shared,
            mode: Mode::Default,
        );
        return ($this->definitionAttach)($this->definitions[$id]);
    }

    /**
     * @param string $alias
     * @param class-string $id
     * */
    public function alias(string $alias, string $id): void
    {
        $this->aliases[$alias] = $id;
    }

    public function value(string $id, mixed $value): void
    {
        $this->values[$id] = $value;
    }


    /**
     * @param array<string, mixed> $pairs
     */
    public function add(array $pairs): void
    {
        foreach ($pairs as $id => $value) {
            if ($id && is_string($id)) {
                if (is_string($value) || $value instanceof Closure) {
                    $this->definitions[$id] ??= new Definition(
                        id: $id,
                        auto: true,
                        shared: $this->shared,
                        mode: Mode::Default,
                        to: $value,
                    );
                }
                elseif ($value instanceof Definition) {
                    $this->definitions[$id] ??= $value;
                }
                else {
                    $this->values[$id] ??= $value;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $pairs
     */
    public function replace(array $pairs): void
    {
        foreach ($pairs as $id => $value) {
            if ($id && is_string($id)) {
                if (is_string($value) || $value instanceof Closure) {
                    $this->definitions[$id] = new Definition(
                        id: $id,
                        auto: true,
                        shared: $this->shared,
                        mode: Mode::Default,
                        to: $value,
                    );
                }
                elseif ($value instanceof Definition) {
                    $this->definitions[$id] = $value;
                }
                else {
                    $this->values[$id] = $value;
                }
            }
        }
    }

    /**
     * @param class-string $className
     * */
    public function inherit(string $className): DefinitionBuilder
    {
        return $this->groupDefinition('inherit', $className);
    }

    public function namespace(string $classNS): DefinitionBuilder
    {
        return $this->groupDefinition('namespace', trim($classNS, '\\'));
    }

    public function regex(string $classRegex): DefinitionBuilder
    {
        return $this->groupDefinition('regex', $classRegex);
    }

    private function groupDefinition(string $groupName, string $group): DefinitionBuilder
    {
        $this->groups[$groupName][$group] ??= new Definition(
            shared: $this->shared,
            mode: Mode::Default,
        );
        return ($this->definitionAttach)($this->groups[$groupName][$group]);
    }

    public function getValue(string $id): mixed
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        return $this->values[$id] ?? null;
    }

    /**
     * @param class-string $id
     * */
    public function getDefinition(string $id): ?Definition
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        if (isset($this->definitions[$id])) {
            return $this->definitions[$id];
        }
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }
        if (!class_exists($id)) {
            return null;
        }

        /**
         * @param string $groupName
         * @param Closure $callback
         */
        foreach ($this->groupCallbacks as $groupName => $callback) {
            if (isset($this->groups[$groupName]) && is_array($this->groups[$groupName])) {
                foreach ($this->groups[$groupName] as $group => $definition) {
                    if ($callback($id, $group)) {
                        return $this->cache[$id] = $this->fromGroupDefinition($id, $definition);
                    }
                }
            }
        }

        return $this->cache[$id] = new Definition(
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
