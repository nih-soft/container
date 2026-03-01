<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

class ModeRoot
{
    public static int $constructorCalls = 0;

    public function __construct(public ModeDepthOne $dependency)
    {
        self::$constructorCalls++;
    }

    public static function reset(): void
    {
        self::$constructorCalls = 0;
    }
}
