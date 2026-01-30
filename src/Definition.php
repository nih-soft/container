<?php

declare(strict_types=1);

namespace NIH\Container;

use Closure;

class Definition
{
    /** @noinspection PhpPropertyCanBeReadonlyInspection */
    public function __construct(
        private(set) string $id = '',
        private(set) bool $auto = false,
        private(set) bool $shared = false,
        private(set) Mode $mode = Mode::Default,
        private(set) string|Closure $to = '',
        private(set) array $args = [],
    )
    {
    }

}