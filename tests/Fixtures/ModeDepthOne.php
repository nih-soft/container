<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

class ModeDepthOne
{
    public const string VALUE = 'One';

    public static int $constructorCalls = 0;

    private string $value;

    public function __construct(public ModeDepthTwo $dependency)
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
        return $this->value . $this->dependency->touch();
    }
}
