<?php

namespace NIH\Container\Tests\Unit;

use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\ContainerNotFoundException;
use NIH\Container\Instantiator;
use NIH\Container\Tests\Fixtures\Some;
use NIH\Container\Tests\Fixtures\SomeInterface;
use Psr\Container\ContainerInterface;
use stdClass;
use Testo\Application\Attribute\Test;
use Testo\Assert;
use Testo\Expect;

final class ContainerTest
{
    #[Test]
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
        Assert::false($container->has(SomeInterface::class), 'Interface without definition');
        Assert::false($container->has('class_alias'), 'Wrong class alias');
        Assert::true($container->has(Some::class), 'Class exists');
        Assert::true($container->has('false'), 'Boolean value');
        Assert::true($container->has('value'), 'String value');
        Assert::true($container->has('value_alias'), 'Value alias');
        Assert::true($container->has(ContainerInterface::class), ContainerInterface::class . ' registered');
        Assert::false($container->has('invalid_id'), 'Invalid key');

        $container = new Container($config1);
        Assert::true($container->has(SomeInterface::class), 'Interface with definition');
        Assert::true($container->has('class_alias'), 'Class alias');
    }

    #[Test]
    public function testHasInstance(): void
    {
        $config = new ContainerConfig(shared: false);
        $config->manual(SomeInterface::class)->to(Some::class)->shared();
        $config->alias('class_alias', SomeInterface::class);
        $container = new Container($config);

        Assert::false($container->hasInstance(SomeInterface::class), 'Interface with definition before get');
        Assert::false($container->hasInstance('class_alias'), 'Interface alias before get');
        $container->get('class_alias');
        Assert::true($container->hasInstance(SomeInterface::class), 'Interface with definition after get');
        Assert::true($container->hasInstance('class_alias'), 'Interface alias after get');

        Assert::false($container->hasInstance(Instantiator::class), 'Values definitions always false');
    }

    #[Test]
    public function testNew(): void
    {
        $container = new Container(new ContainerConfig(shared: true));

        $expect = $container->get(stdClass::class);
        $actual = $container->new(stdClass::class);
        Assert::instanceOf(stdClass::class, $expect);
        Assert::instanceOf(stdClass::class, $actual);
        Assert::notSame($expect, $actual, 'get() and new() - different objects');

        Expect::exception(ContainerNotFoundException::class);
        $container->get('invalid_id');
    }

    #[Test]
    public function testGetShared(): void
    {
        $container = new Container(new ContainerConfig(shared: true));
        $expect = $container->get(stdClass::class);
        $actual = $container->get(stdClass::class);
        Assert::instanceOf(stdClass::class, $expect);
        Assert::instanceOf(stdClass::class, $actual);
        Assert::same($expect, $actual, 'Second get() - the same object');

        $expect = $container->get(Instantiator::class);
        Assert::instanceOf(Instantiator::class, $expect);
        Assert::same($expect, $container->get(Instantiator::class), Instantiator::class . ' registered as value');

        $expect = $container->get(ContainerInterface::class);
        Assert::instanceOf(Container::class, $expect);
        Assert::same($expect, $container->get(ContainerInterface::class), ContainerInterface::class . ' registered as value');
    }

    #[Test]
    public function testGetNonShared(): void
    {
        $container = new Container(new ContainerConfig(shared: false));
        $expect = $container->get(stdClass::class);
        $actual = $container->get(stdClass::class);
        Assert::instanceOf(stdClass::class, $expect);
        Assert::instanceOf(stdClass::class, $actual);
        Assert::notSame($expect, $actual, 'Second get() - different object');

        $expect = $container->get(Instantiator::class);
        Assert::instanceOf(Instantiator::class, $expect);
        Assert::same($expect, $container->get(Instantiator::class), Instantiator::class . ' registered as value in nonshared container');

        $expect = $container->get(ContainerInterface::class);
        Assert::instanceOf(Container::class, $expect);
        Assert::same($expect, $container->get(ContainerInterface::class), ContainerInterface::class . ' registered as value in nonshared container');
    }

}
