<?php


declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Application\Config\FinderConfig;

return new ApplicationConfig(
    suites: [
        new SuiteConfig(
            name: 'Container Unit Tests',
            location: new FinderConfig(
                include: [__DIR__ . '/Unit'],
            ),
        ),
    ],
);