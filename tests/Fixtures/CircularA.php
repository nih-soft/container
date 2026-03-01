<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

class CircularA
{
    public static int $constructorCalls = 0;

    private string $id;

    public function __construct(public CircularB $b)
    {
        self::$constructorCalls++;
        $this->id = 'A';
    }

    public static function reset(): void
    {
        self::$constructorCalls = 0;
    }

    public function id(): string
    {
        return $this->id;
    }
}
