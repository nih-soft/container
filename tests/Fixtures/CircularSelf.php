<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

class CircularSelf
{
    public static int $constructorCalls = 0;

    public function __construct(public CircularSelf $self)
    {
        self::$constructorCalls++;
    }

    public static function reset(): void
    {
        self::$constructorCalls = 0;
    }
}
