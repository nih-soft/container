<?php

declare(strict_types=1);

namespace NIH\Container;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;

class DefinitionBuilder
{
    /**
     * @var Closure(Definition $definition, string $key, mixed $value): void
     */
    private readonly Closure $setter;

    public function __construct(
        /** @noinspection PhpPropertyCanBeReadonlyInspection */
        private Definition $definition = new Definition()
    )
    {
        $this->setter = (static function (Definition $definition, string $key, mixed $value) {
            $definition->{$key} = $value;
        })->bindTo(null, Definition::class);
    }

    private function set(
        #[ExpectedValues(values: ['id', 'auto', 'shared', 'mode', 'to', 'args'])]
        string $key,
        mixed  $value
    ): void
    {
        ($this->setter)($this->definition, $key, $value);
    }

    public function shared(bool $bool = true): static
    {
        $this->set('shared', $bool);
        return $this;
    }

    public function auto(bool $bool = true): static
    {
        $this->set('auto', $bool);
        return $this;
    }

    public function manual(bool $bool = true): static
    {
        $this->set('auto', !$bool);
        return $this;
    }

    public function ghost(): static
    {
        $this->set('mode', Mode::Ghost);
        return $this;
    }

    public function nestedGhost(): static
    {
        $this->set('mode', Mode::NestedGhost);
        return $this;
    }

    public function mode(Mode $mode = Mode::Default): static
    {
        $this->set('mode', $mode);
        return $this;
    }

    public function to(string $id): static
    {
        $this->set('to', $id);
        return $this;
    }

    public function callback(Closure $callback): static
    {
        $this->set('to', $callback);
        return $this;
    }

    public function args(array $arguments): static
    {
        $this->set('args', $arguments);
        return $this;
    }

    public function argument(int|string $parameter, mixed $argument): static
    {
        $args = $this->definition->args;
        $args[$parameter] = $argument;
        return $this->args($args);
    }
}