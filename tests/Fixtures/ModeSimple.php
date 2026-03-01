<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

class ModeSimple
{
    public const string VALUE = 'Simple';

    public static int $constructorCalls = 0;

    private string $value;

    public function __construct()
    {
        self::$constructorCalls++;
        $this->value = self::VALUE;
    }

    public static function reset(): void
    {
        self::$constructorCalls = 0;
    }

    public function touch(): string
    {
        return $this->value;
    }
}
