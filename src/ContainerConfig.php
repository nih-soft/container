<?php

declare(strict_types=1);

namespace NIH\Container;

use Closure;

final class ContainerConfig extends ContainerData
{
    private readonly DefinitionBuilder $builder;

    private readonly Closure $definitionAttach;

    public function __construct(
        bool $shared = false,
        public readonly Mode $mode = Mode::Default,
        public readonly bool $cacheReflections = true,
        public readonly int  $maxDepth = 5,
    )
    {
        $this->shared = $shared;
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->builder = new DefinitionBuilder();
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
        $this->services[$id] = $value;
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
                    $this->services[$id] ??= $value;
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
                    $this->services[$id] = $value;
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
}
