<?php

declare(strict_types=1);

namespace NIH\Container;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerNotFoundException extends Exception implements NotFoundExceptionInterface
{
}