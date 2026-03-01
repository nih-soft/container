<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use NIH\Container\ContainerConfig;
use NIH\Container\Definition;
use NIH\Container\Mode;
use NIH\Container\Tests\Fixtures\Some;
use NIH\Container\Tests\Fixtures\SomeInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerConfigTest extends TestCase
{
    public function testDefinitionBuilderOptions(): void
    {
        $config = new ContainerConfig();

        $builder = $config->auto(Some::class);
        $definition = $config->getDefinition(Some::class);
        self::assertNotNull($definition);
        self::assertSame(Some::class, $definition->id);
        self::assertSame(Mode::Default, $definition->mode);
        self::assertTrue($definition->auto);
        self::assertFalse($definition->shared);

        $builder = $config->manual(SomeInterface::class);

        $builder->ghost();
        self::assertSame(Mode::Ghost, $config->getDefinition(SomeInterface::class)?->mode);

        $builder->nestedGhost();
        self::assertSame(Mode::NestedGhost, $config->getDefinition(SomeInterface::class)?->mode);

        $builder->auto();
        self::assertTrue($config->getDefinition(SomeInterface::class)?->auto);

        $builder->manual();
        self::assertFalse($config->getDefinition(SomeInterface::class)?->auto);

        $builder->shared();
        self::assertTrue($config->getDefinition(SomeInterface::class)?->shared);

        $builder->shared(false);
        self::assertFalse($config->getDefinition(SomeInterface::class)?->shared);

        $builder->to(Some::class);
        self::assertSame(Some::class, $config->getDefinition(SomeInterface::class)?->to);

        $cb = static fn(): Some => new Some();
        $builder->callback($cb);
        $builder->args(['a' => 1]);
        $builder->argument('b', 2);
        $builder->mode(Mode::Proxy);

        $definition = $config->getDefinition(SomeInterface::class);
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
        $config->value('answer', 42);
        $config->alias('answer_alias', 'answer');

        $config->manual(SomeInterface::class)->to(Some::class);
        $config->alias('service_alias', SomeInterface::class);

        self::assertSame(42, $config->getValue('answer_alias'));
        self::assertSame(
            SomeInterface::class,
            $config->getDefinition('service_alias')?->id
        );
    }

    public function testAddAndReplace(): void
    {
        $config = new ContainerConfig();
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

        self::assertSame(10, $config->getValue('value'));
        self::assertSame(Some::class, $config->getDefinition('string_def')?->to);
        self::assertIsCallable($config->getDefinition('closure_def')?->to);
        self::assertSame('custom_id', $config->getDefinition('def_obj')?->id);

        $config->replace([
            'string_def' => stdClass::class,
            'value' => 99,
        ]);

        self::assertSame(99, $config->getValue('value'));
        self::assertSame(stdClass::class, $config->getDefinition('string_def')?->to);
    }

    public function testDefinitionResolutionForKnownAndUnknownClasses(): void
    {
        $config = new ContainerConfig(shared: true);

        $known = $config->getDefinition(stdClass::class);
        $unknown = $config->getDefinition('Unknown\\MissingClass');

        self::assertNotNull($known);
        self::assertSame(stdClass::class, $known->id);
        self::assertTrue($known->auto);
        self::assertTrue($known->shared);
        self::assertSame(Mode::Default, $known->mode);
        self::assertNull($unknown);
    }
}
