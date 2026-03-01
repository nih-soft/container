<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;
use NIH\Container\Tests\Fixtures\ModeSimple;
use NIH\Container\Tests\Fixtures\ModeDepthTwo;
use NIH\Container\Tests\Fixtures\ModeDepthOne;
use NIH\Container\Tests\Fixtures\ModeRoot;
use PHPUnit\Framework\TestCase;

final class ModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ModeSimple::reset();
        ModeRoot::reset();
        ModeDepthOne::reset();
        ModeDepthTwo::reset();
    }

    public function testAllModes(): void
    {
        foreach (Mode::cases() as $mode) {
            $container = new Container(new ContainerConfig(mode: $mode));

            $object = $container->get(ModeSimple::class);

            self::assertSame(ModeSimple::VALUE, $object->touch());
            self::assertSame(1, ModeSimple::$constructorCalls);

            ModeSimple::reset();
        }
    }

    public function testProxyModesLazyObject(): void
    {
        foreach ([Mode::Proxy, Mode::Ghost] as $mode) {
            $container = new Container(new ContainerConfig(mode: $mode));

            $object = $container->get(ModeSimple::class);

            self::assertSame(0, ModeSimple::$constructorCalls);
            self::assertSame(ModeSimple::VALUE, $object->touch());
            self::assertSame(1, ModeSimple::$constructorCalls);

            ModeSimple::reset();
        }
    }

    public function testProxyModesCreatesNestedDependenciesLazily(): void
    {
        foreach ([Mode::NestedProxy, Mode::NestedGhost] as $mode) {
            $container = new Container(new ContainerConfig(mode: $mode));

            $root = $container->get(ModeRoot::class);

            self::assertSame(1, ModeRoot::$constructorCalls);
            self::assertSame(0, ModeDepthOne::$constructorCalls);
            self::assertSame(0, ModeDepthTwo::$constructorCalls);

            self::assertSame(ModeDepthOne::VALUE . ModeDepthTwo::VALUE, $root->dependency->touch());
            self::assertSame(1, ModeDepthOne::$constructorCalls);
            self::assertSame(1, ModeDepthTwo::$constructorCalls);

            ModeRoot::reset();
            ModeDepthOne::reset();
            ModeDepthTwo::reset();
        }
    }

    public function testInstanceModesFallsBackToGhostAfterMaxDepth(): void
    {
        foreach ([Mode::Default, Mode::Instance] as $mode) {
            $container = new Container(new ContainerConfig(mode: $mode, maxDepth: 1));

            $root = $container->get(ModeRoot::class);

            self::assertSame(1, ModeRoot::$constructorCalls);
            self::assertSame(1, ModeDepthOne::$constructorCalls);
            self::assertSame(0, ModeDepthTwo::$constructorCalls);

            self::assertSame(ModeDepthOne::VALUE . ModeDepthTwo::VALUE, $root->dependency->touch());
            self::assertSame(1, ModeDepthTwo::$constructorCalls);

            ModeRoot::reset();
            ModeDepthOne::reset();
            ModeDepthTwo::reset();
        }
    }
}
