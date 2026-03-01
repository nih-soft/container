<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Unit;

use NIH\Container\Arg;
use NIH\Container\Container;
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;
use NIH\Container\Tests\Fixtures\ModeSimple;
use PHPUnit\Framework\TestCase;

final class ArgTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ModeSimple::reset();
    }

    public function testResolveArgumentAndArguments(): void
    {
        $config = new ContainerConfig();
        $config->value('resolved', 'ok');
        $container = new Container($config);

        $customArg = new class() extends Arg {
            public function __invoke(Container $container, string $id = ''): mixed
            {
                return $container->get('resolved') . $id;
            }
        };

        self::assertSame('value', Arg::resolveArgument('value', $container));
        self::assertSame('ok:id', Arg::resolveArgument($customArg, $container, ':id'));
        self::assertSame(
            ['value', 'ok:id'],
            Arg::resolveArguments(['value', $customArg], $container, ':id')
        );
    }

    public function testIdArgumentReturnsRequestedIdentifier(): void
    {
        $container = new Container(new ContainerConfig());

        $idArg = Arg::id();

        self::assertSame(ModeSimple::class, $idArg($container, ModeSimple::class));
    }

    public function testGetAndNewFollowContainerSemantics(): void
    {
        $container = new Container(new ContainerConfig(shared: true));

        $getArg = Arg::get(ModeSimple::class);
        $newArg = Arg::new(ModeSimple::class);

        $firstGet = $getArg($container);
        $secondGet = $getArg($container);
        $firstNew = $newArg($container);
        $secondNew = $newArg($container);

        self::assertSame($firstGet, $secondGet);
        self::assertNotSame($firstGet, $firstNew);
        self::assertNotSame($firstGet, $secondNew);
        self::assertNotSame($firstNew, $secondNew);
        self::assertSame(3, ModeSimple::$constructorCalls);
    }

    public function testModeSpecificGetAndNewAreLazyForGhostMode(): void
    {
        $container = new Container(new ContainerConfig(shared: true));

        $ghostGet = Arg::get(ModeSimple::class, Mode::Ghost)($container);
        $ghostNew = Arg::new(ModeSimple::class, Mode::Ghost)($container);

        self::assertSame(0, ModeSimple::$constructorCalls);

        self::assertSame(ModeSimple::VALUE, $ghostGet->touch());
        self::assertSame(ModeSimple::VALUE, $ghostNew->touch());
        self::assertSame(2, ModeSimple::$constructorCalls);
    }

    public function testDynamicIdentifierAndInvalidIdentifierHandling(): void
    {
        $container = new Container(new ContainerConfig());

        $dynamic = Arg::get(Arg::id());
        $resolved = $dynamic($container, ModeSimple::class);

        self::assertInstanceOf(ModeSimple::class, $resolved);

        $invalid = Arg::get(new class() extends Arg {
            public function __invoke(Container $container, string $id = ''): mixed
            {
                return 123;
            }
        });

        self::assertNull($invalid($container));
    }
}
