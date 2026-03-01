<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

class CircularB
{
    public static int $constructorCalls = 0;

    private string $id;

    public function __construct(public CircularA $a)
    {
        self::$constructorCalls++;
        $this->id = 'B';
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
