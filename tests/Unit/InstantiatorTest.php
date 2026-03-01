<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use InvalidArgumentException;
use NIH\Container\Arg;
use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\Instantiator;
use NIH\Container\Tests\Fixtures\InstantiatorAutoTarget;
use NIH\Container\Tests\Fixtures\InstantiatorManualTarget;
use NIH\Container\Tests\Fixtures\Some;
use NIH\Container\Tests\Fixtures\SomeInterface;
use PHPUnit\Framework\TestCase;

final class InstantiatorTest extends TestCase
{
    public function testMakeInjectsDependenciesAndArguments(): void
    {
        $container = new Container(new ContainerConfig());
        $instantiator = $container->get(Instantiator::class);

        $object = $instantiator->make(InstantiatorAutoTarget::class, ['value' => 'ok']);

        self::assertInstanceOf(InstantiatorAutoTarget::class, $object);
        self::assertInstanceOf(Some::class, $object->some);
        self::assertSame('ok', $object->value);
    }

    public function testMakeSupportsManualArgumentResolutionModes(): void
    {
        $container = new Container(new ContainerConfig(shared: true));
        $instantiator = $container->get(Instantiator::class);

        $resolved = $instantiator->make(
            InstantiatorManualTarget::class,
            [Arg::new(Some::class), Arg::get(Some::class)],
            auto: false,
            dynamicArguments: true,
        );

        self::assertInstanceOf(Some::class, $resolved->first);
        self::assertInstanceOf(Some::class, $resolved->second);
        self::assertNotSame($resolved->first, $resolved->second);

        $raw = $instantiator->make(
            InstantiatorManualTarget::class,
            [Arg::id(), 'plain'],
            auto: false,
            dynamicArguments: true,
        );

        self::assertSame(InstantiatorManualTarget::class, $raw->first);
        self::assertSame('plain', $raw->second);

        $rawArg1 = Arg::id();
        $rawArg2 = Arg::get(Some::class);
        $raw = $instantiator->make(
            InstantiatorManualTarget::class,
            [$rawArg1, $rawArg2],
            auto: false,
            dynamicArguments: false,
        );

        self::assertSame($rawArg1, $raw->first);
        self::assertSame($rawArg2, $raw->second);
        self::assertInstanceOf(Arg::class, $raw->first);
        self::assertInstanceOf(Arg::class, $raw->second);
    }

    public function testInvokeSupportsAllResolverBranches(): void
    {
        $container = new Container(new ContainerConfig());
        $instantiator = $container->get(Instantiator::class);

        $autoDynamic = $instantiator->invoke(
            static fn(Some $some, string $label): string => $some::class . ':' . $label,
            ['label' => 'x'],
            auto: true,
            dynamicArguments: true,
        );
        self::assertSame(Some::class . ':x', $autoDynamic);

        $autoStatic = $instantiator->invoke(
            static fn(string $value): string => $value,
            ['value' => 'v'],
            auto: true,
            dynamicArguments: false,
        );
        self::assertSame('v', $autoStatic);

        $manualDynamic = $instantiator->invoke(
            static fn(mixed $value): mixed => $value,
            [Arg::new(Some::class)],
            auto: false,
            dynamicArguments: true,
        );
        self::assertInstanceOf(Some::class, $manualDynamic);

        $manualDynamic = $instantiator->invoke(
            static fn(mixed $value): mixed => $value,
            [Arg::id()],
            auto: false,
            dynamicArguments: true,
        );
        self::assertSame('', $manualDynamic);

        $rawArg = Arg::id();
        $manualStatic = $instantiator->invoke(
            static fn(mixed $value): mixed => $value,
            [$rawArg],
            auto: false,
            dynamicArguments: false,
        );
        self::assertSame($rawArg, $manualStatic);
    }

    public function testGetClassReflectionCachingBehaviour(): void
    {
        $cachedInstantiator = (new Container(new ContainerConfig(cacheReflections: true)))->get(Instantiator::class);
        $cachedA = $cachedInstantiator->getClassReflection(Some::class);
        $cachedB = $cachedInstantiator->getClassReflection(Some::class);
        self::assertSame($cachedA, $cachedB);

        $plainInstantiator = (new Container(new ContainerConfig(cacheReflections: false)))->get(Instantiator::class);
        $plainA = $plainInstantiator->getClassReflection(Some::class);
        $plainB = $plainInstantiator->getClassReflection(Some::class);
        self::assertNotSame($plainA, $plainB);
    }

    public function testMakeThrowsForNonInstantiableType(): void
    {
        $instantiator = (new Container(new ContainerConfig()))->get(Instantiator::class);

        $this->expectException(InvalidArgumentException::class);
        $instantiator->make(SomeInterface::class);
    }
}
