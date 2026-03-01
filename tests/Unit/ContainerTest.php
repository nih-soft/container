<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\ContainerNotFoundException;
use NIH\Container\Instantiator;
use NIH\Container\Tests\Fixtures\Some;
use NIH\Container\Tests\Fixtures\SomeInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testHas(): void
    {
        $config = new ContainerConfig();
        $config->value('false', false);
        $config->value('value', 'value');
        $config->alias('value_alias', 'value');
        $config->alias('class_alias', SomeInterface::class);
        $config1 = clone $config;
        $config1->manual(SomeInterface::class)->to(Some::class);

        $container = new Container($config);
        self::assertFalse($container->has(SomeInterface::class), 'Interface without definition');
        self::assertFalse($container->has('class_alias'), 'Wrong class alias');
        self::assertTrue($container->has(Some::class), 'Class exists');
        self::assertTrue($container->has('false'), 'Boolean value');
        self::assertTrue($container->has('value'), 'String value');
        self::assertTrue($container->has('value_alias'), 'Value alias');
        self::assertTrue($container->has(ContainerInterface::class), ContainerInterface::class . ' registered');
        self::assertFalse($container->has('invalid_id'), 'Invalid key');

        $container = new Container($config1);
        self::assertTrue($container->has(SomeInterface::class), 'Interface with definition');
        self::assertTrue($container->has('class_alias'), 'Class alias');
    }

    public function testHasInstance(): void
    {
        $config = new ContainerConfig(shared: false);
        $config->manual(SomeInterface::class)->to(Some::class)->shared();
        $config->alias('class_alias', SomeInterface::class);
        $container = new Container($config);

        self::assertFalse($container->hasInstance(SomeInterface::class), 'Interface with definition before get');
        self::assertFalse($container->hasInstance('class_alias'), 'Interface alias before get');

        $container->get('class_alias');

        self::assertTrue($container->hasInstance(SomeInterface::class), 'Interface with definition after get');
        self::assertTrue($container->hasInstance('class_alias'), 'Interface alias after get');
        self::assertFalse($container->hasInstance(Instantiator::class), 'Values definitions always false');
    }

    public function testNew(): void
    {
        $container = new Container(new ContainerConfig(shared: true));

        $fromGet = $container->get(stdClass::class);
        $fromNew = $container->new(stdClass::class);

        self::assertInstanceOf(stdClass::class, $fromGet);
        self::assertInstanceOf(stdClass::class, $fromNew);
        self::assertNotSame($fromGet, $fromNew, 'get() and new() should return different objects');

        $this->expectException(ContainerNotFoundException::class);
        $container->get('invalid_id');
    }

    public function testGetShared(): void
    {
        $container = new Container(new ContainerConfig(shared: true));

        $first = $container->get(stdClass::class);
        $second = $container->get(stdClass::class);
        self::assertInstanceOf(stdClass::class, $first);
        self::assertInstanceOf(stdClass::class, $second);
        self::assertSame($first, $second, 'Second get() should return the same object');

        $instantiator = $container->get(Instantiator::class);
        self::assertInstanceOf(Instantiator::class, $instantiator);
        self::assertSame($instantiator, $container->get(Instantiator::class), Instantiator::class . ' registered as value');

        $interface = $container->get(ContainerInterface::class);
        self::assertInstanceOf(Container::class, $interface);
        self::assertSame($interface, $container->get(ContainerInterface::class), ContainerInterface::class . ' registered as value');
    }

    public function testGetNonShared(): void
    {
        $container = new Container(new ContainerConfig(shared: false));

        $first = $container->get(stdClass::class);
        $second = $container->get(stdClass::class);
        self::assertInstanceOf(stdClass::class, $first);
        self::assertInstanceOf(stdClass::class, $second);
        self::assertNotSame($first, $second, 'Second get() should return different objects');

        $instantiator = $container->get(Instantiator::class);
        self::assertInstanceOf(Instantiator::class, $instantiator);
        self::assertSame(
            $instantiator,
            $container->get(Instantiator::class),
            Instantiator::class . ' registered as value in nonshared container'
        );

        $interface = $container->get(ContainerInterface::class);
        self::assertInstanceOf(Container::class, $interface);
        self::assertSame(
            $interface,
            $container->get(ContainerInterface::class),
            ContainerInterface::class . ' registered as value in nonshared container'
        );
    }
}
