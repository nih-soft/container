<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;
use NIH\Container\Tests\Fixtures\CircularA;
use NIH\Container\Tests\Fixtures\CircularB;
use NIH\Container\Tests\Fixtures\CircularSelf;
use PHPUnit\Framework\TestCase;

final class CircularReferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CircularA::reset();
        CircularB::reset();
        CircularSelf::reset();
    }

    public function testCircularReferenceInProxyModesSharedContainer(): void
    {
        foreach([Mode::Proxy, Mode::Ghost, Mode::NestedProxy, Mode::NestedGhost] as $mode) {
            $container = new Container(new ContainerConfig(shared: true, mode: $mode));

            $a = $container->get(CircularA::class);
            $b = $container->get(CircularB::class);

            self::assertSame($a, $a->b->a);
            self::assertSame($a, $b->a);
            self::assertSame($b, $b->a->b);
            self::assertSame($b, $a->b);

            self::assertSame(1, CircularA::$constructorCalls);
            self::assertSame(1, CircularB::$constructorCalls);

            CircularA::reset();
            CircularB::reset();
        }
    }

    public function testCircularReferenceInContainer(): void
    {
        foreach([true, false] as $shared) {
            foreach (Mode::cases() as $mode) {
                $container = new Container(new ContainerConfig(shared: $shared, mode: $mode));

                $a = $container->get(CircularA::class);
                $b = $container->get(CircularB::class);

                self::assertInstanceOf(CircularA::class, $a);
                self::assertInstanceOf(CircularB::class, $b);

                self::assertInstanceOf(CircularA::class, $b->a);
                self::assertInstanceOf(CircularB::class, $a->b);

                self::assertInstanceOf(CircularA::class, $a->b->a);
                self::assertInstanceOf(CircularB::class, $b->a->b);

                self::assertSame('A', $a->b->a->id());
                self::assertSame('B', $b->a->b->id());

                self::assertGreaterThanOrEqual(1, CircularA::$constructorCalls);
                self::assertGreaterThanOrEqual(1, CircularB::$constructorCalls);

                CircularA::reset();
                CircularB::reset();
            }
        }
    }

    public function testSelfCircularReferenceInProxyModesSharedContainer(): void
    {
        foreach ([Mode::Proxy, Mode::Ghost] as $mode) {
            $container = new Container(new ContainerConfig(shared: true, mode: $mode));

            $self = $container->get(CircularSelf::class);

            self::assertSame($self, $self->self);
            self::assertInstanceOf(CircularSelf::class, $self->self);

            self::assertSame(1, CircularSelf::$constructorCalls);

            CircularSelf::reset();
        }
    }

    public function testSelfCircularReferenceInContainer(): void
    {
        foreach([true, false] as $shared) {
            foreach (Mode::cases() as $mode) {
                $container = new Container(new ContainerConfig(shared: $shared, mode: $mode));

                $self = $container->get(CircularSelf::class);

                self::assertInstanceOf(CircularSelf::class, $self);
                self::assertInstanceOf(CircularSelf::class, $self->self);

                self::assertGreaterThanOrEqual(1, CircularSelf::$constructorCalls);

                CircularSelf::reset();
            }
        }
    }
}
