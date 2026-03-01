<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

final class InstantiatorAutoTarget
{
    public function __construct(
        public Some $some,
        public string $value,
    )
    {
    }
}
