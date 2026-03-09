<?php

namespace NIH\Container;

abstract class ContainerData
{
    protected array $aliases = [];

    /** @var Definition[]  */
    protected array $definitions = [];

    protected array $groups = [];

    protected array $services = [];

    protected bool $shared;

}