<?php
declare(strict_types=1);

namespace NIH\Container;

abstract class Arg
{
    public static function resolveArguments(
        array     $arguments,
        Container $container,
        string    $id = '',
    ): array
    {
        foreach ($arguments as &$argument) {
            $argument = static::resolveArgument($argument, $container, $id);
        }

        return $arguments;
    }

    public static function resolveArgument(
        mixed     $argument,
        Container $container,
        string    $id = '',
    ): mixed
    {
        if ($argument instanceof self) {
            return $argument($container, $id);
        }

        return $argument;
    }

    abstract public function __invoke(Container $container, string $id = ''): mixed;

    public static function get(string|Arg $id, ?Mode $mode = null): Arg
    {
        return new class($id, $mode) extends Arg {
            public function __construct(
                protected string|Arg $id,
                protected ?Mode      $mode = null,
            )
            {
            }

            public function __invoke(Container $container, string $id = ''): mixed
            {
                $class = static::resolveArgument($this->id, $container, $id);
                if (!$class || !is_string($class)) {
                    return null;
                }
                if ($this->mode === null) {
                    return $container->get($class);
                }
                return $container->get(Instantiator::class)?->moddedRun($this->mode, static fn() => $container->get($class));
            }
        };
    }

    public static function new(string|Arg $id, ?Mode $mode = null): Arg
    {
        return new class($id, $mode) extends Arg {
            public function __construct(
                protected string|Arg $id,
                protected ?Mode      $mode = null,
            )
            {
            }

            public function __invoke(Container $container, string $id = ''): mixed
            {
                $class = static::resolveArgument($this->id, $container, $id);
                if (!$class || !is_string($class)) {
                    return null;
                }
                if ($this->mode === null) {
                    return $container->new($class);
                }
                return $container->get(Instantiator::class)?->moddedRun($this->mode, static fn() => $container->new($class));
            }
        };
    }

    public static function id(): Arg
    {
        return new class() extends Arg {
            public function __invoke(Container $container, string $id = ''): string
            {
                return $id;
            }
        };
    }

}
