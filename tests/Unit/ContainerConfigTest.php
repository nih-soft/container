<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use Closure;
use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\Definition;
use NIH\Container\Mode;
use NIH\Container\Tests\Fixtures\ModeDepthOne;
use NIH\Container\Tests\Fixtures\ModeSimple;
use NIH\Container\Tests\Fixtures\Some;
use NIH\Container\Tests\Fixtures\SomeInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerConfigTest extends TestCase
{
    private Closure $getValue;
    private Closure $getDefinition;
    private Closure $getRealId;

    private function definitionAccessor(ContainerConfig $config): Closure
    {
        $container = new Container($config);
        
        return (function (string $id): ?Definition {
            return $this->getDefinition($id);
        })->bindTo($container, $container);
    }
    
    private function bindGetters(ContainerConfig $config): void
    {
        $container = new Container($config);

        $this->getDefinition = (function (string $id): ?Definition {
            return $this->getDefinition($id);
        })->bindTo($container, $container);
        $this->getValue = (function (string $id): mixed {
            return $this->services[$id] ?? null;
        })->bindTo($container, $container);
        $this->getRealId = (function (string $id): string {
            return $this->aliases[$id] ?? $id;
        })->bindTo($container, $container);
    }
    
    public function testDefinitionBuilderOptions(): void
    {
        $config = new ContainerConfig();
        $this->bindGetters($config);

        $builder = $config->auto(Some::class);
        $definition = ($this->getDefinition)(Some::class);
        self::assertNotNull($definition);
        self::assertSame(Some::class, $definition->id);
        self::assertSame(Mode::Default, $definition->mode);
        self::assertTrue($definition->auto);
        self::assertFalse($definition->shared);

        $builder = $config->manual(SomeInterface::class);

        $builder->ghost();
        self::assertSame(Mode::Ghost, ($this->getDefinition)(SomeInterface::class)?->mode);

        $builder->nestedGhost();
        self::assertSame(Mode::NestedGhost, ($this->getDefinition)(SomeInterface::class)?->mode);

        $builder->auto();
        self::assertTrue(($this->getDefinition)(SomeInterface::class)?->auto);

        $builder->manual();
        self::assertFalse(($this->getDefinition)(SomeInterface::class)?->auto);

        $builder->shared();
        self::assertTrue(($this->getDefinition)(SomeInterface::class)?->shared);

        $builder->shared(false);
        self::assertFalse(($this->getDefinition)(SomeInterface::class)?->shared);

        $builder->to(Some::class);
        self::assertSame(Some::class, ($this->getDefinition)(SomeInterface::class)?->to);

        $cb = static fn(): Some => new Some();
        $builder->callback($cb);
        $builder->args(['a' => 1]);
        $builder->argument('b', 2);
        $builder->mode(Mode::Proxy);

        $definition = ($this->getDefinition)(SomeInterface::class);
        self::assertNotNull($definition);
        self::assertSame(SomeInterface::class, $definition->id);

        self::assertSame(Mode::Proxy, $definition->mode);
        self::assertSame(['a' => 1, 'b' => 2], $definition->args);
        self::assertIsCallable($definition->to);
        self::assertSame($cb, $definition->to);
    }

    public function testValueAndAliasResolution(): void
    {
        $config = new ContainerConfig();
        $this->bindGetters($config);

        $config->value('answer', 42);
        $config->alias('answer_alias', 'answer');

        $config->manual(SomeInterface::class)->to(Some::class);
        $config->alias('service_alias', SomeInterface::class);

        self::assertSame(42, ($this->getValue)(($this->getRealId)('answer_alias')));
        self::assertSame(
            SomeInterface::class,
            ($this->getDefinition)(($this->getRealId)('service_alias'))?->id
        );
    }

    public function testAddAndReplace(): void
    {
        $config = new ContainerConfig();
        $this->bindGetters($config);
        $custom = new Definition(
            id: 'custom_id',
            auto: false,
            shared: true,
            mode: Mode::Ghost,
            to: Some::class,
            args: ['x' => 1],
        );

        $config->add([
            'string_def' => Some::class,
            'closure_def' => static fn(): Some => new Some(),
            'value' => 10,
            'def_obj' => $custom,
        ]);

        self::assertSame(10, ($this->getValue)('value'));
        self::assertSame(Some::class, ($this->getDefinition)('string_def')?->to);
        self::assertIsCallable(($this->getDefinition)('closure_def')?->to);
        self::assertSame('custom_id', ($this->getDefinition)('def_obj')?->id);

        $config->replace([
            'string_def' => stdClass::class,
            'value' => 99,
        ]);

        self::assertSame(99, ($this->getValue)('value'));
        self::assertSame(stdClass::class, ($this->getDefinition)('string_def')?->to);
    }

    public function testDefinitionResolutionForKnownAndUnknownClasses(): void
    {
        $config = new ContainerConfig(shared: true);
        $this->bindGetters($config);

        $known = ($this->getDefinition)(stdClass::class);
        $unknown = ($this->getDefinition)('Unknown\\MissingClass');

        self::assertNotNull($known);
        self::assertSame(stdClass::class, $known->id);
        self::assertTrue($known->auto);
        self::assertTrue($known->shared);
        self::assertSame(Mode::Default, $known->mode);
        self::assertNull($unknown);
    }

    public function testInheritGroupDefinitionAppliesToImplementations(): void
    {
        $config = new ContainerConfig();
        $this->bindGetters($config);

        $config->inherit(SomeInterface::class)
            ->manual()
            ->shared()
            ->mode(Mode::Proxy)
            ->argument('source', 'inherit');

        $definition = ($this->getDefinition)(Some::class);
        self::assertNotNull($definition);
        self::assertSame(Some::class, $definition->id);
        self::assertFalse($definition->auto);
        self::assertTrue($definition->shared);
        self::assertSame(Mode::Proxy, $definition->mode);
        self::assertSame(['source' => 'inherit'], $definition->args);
    }

    public function testNamespaceGroupDefinitionAppliesWithTrimmedPrefix(): void
    {
        $config = new ContainerConfig();
        $this->bindGetters($config);

        $config->namespace('NIH\\Container\\Tests\\Fixtures\\')
            ->manual()
            ->mode(Mode::Ghost)
            ->argument('source', 'namespace');

        $definition = ($this->getDefinition)(ModeSimple::class);
        self::assertNotNull($definition);
        self::assertSame(ModeSimple::class, $definition->id);
        self::assertFalse($definition->auto);
        self::assertSame(Mode::Ghost, $definition->mode);
        self::assertSame(['source' => 'namespace'], $definition->args);
    }

    public function testRegexGroupDefinitionAppliesToMatchingClasses(): void
    {
        $config = new ContainerConfig();
        $this->bindGetters($config);
        $config->regex('/^NIH\\\\Container\\\\Tests\\\\Fixtures\\\\ModeDepth.+$/')
            ->shared()
            ->mode(Mode::NestedGhost)
            ->argument('source', 'regex');

        $definition = ($this->getDefinition)(ModeDepthOne::class);
        self::assertNotNull($definition);
        self::assertSame(ModeDepthOne::class, $definition->id);
        self::assertFalse($definition->auto);
        self::assertTrue($definition->shared);
        self::assertSame(Mode::NestedGhost, $definition->mode);
        self::assertSame(['source' => 'regex'], $definition->args);
    }
}
