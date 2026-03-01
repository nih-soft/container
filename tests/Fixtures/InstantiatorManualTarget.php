<?php

declare(strict_types=1);

namespace NIH\Container\Tests\Fixtures;

final class InstantiatorManualTarget
{
    public function __construct(
        public mixed $first,
        public mixed $second,
    )
    {
    }
}
