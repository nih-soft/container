<?php

declare(strict_types=1);

namespace NIH\Container;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\InvalidArgumentException;
use Yiisoft\Injector\MissingRequiredArgumentException;

class Instantiator
{
    /**
     * @var ReflectionClass[]
     * @psalm-var array<class-string,ReflectionClass>
     */
    private array $reflectionsCache = [];

    /**
     * @var Closure(ReflectionFunctionAbstract $reflection, array $arguments = []): array
     */
    private Closure $injectorResolver {
        get {
            return $this->injectorResolver ??= ((function (): Closure {
                return $this->resolveDependencies(...);
            })->bindTo($this->injector, $this->injector))();
        }
        set {
        }
    }

    private Injector $injector {
        get {
            return $this->injector ??= new Injector($this->container);
        }
        set {
        }
    }

    private int $depth = 0;

    private readonly int $maxDepth;

    public function __construct(
        private readonly Container $container,
        private readonly ?bool     $cacheReflections = false,
        private Mode               $mode = Mode::Default,
        int $maxDepth = 5,
    )
    {
        $this->maxDepth = min(50, max(1, $maxDepth));
    }

    /**
     * Invoke a callback with resolving dependencies based on parameter types.
     *
     * This method allows invoking a callback and let type hinted parameter names to be
     * resolved as objects of the Container. It additionally allows calling function passing named arguments.
     *
     * For example, the following callback may be invoked using the Container to resolve the formatter dependency:
     *
     * ```php
     * $formatString = function($string, \Yiisoft\I18n\MessageFormatterInterface $formatter) {
     *    ...
     * }
     *
     * $injector = new Yiisoft\Injector\Injector($container);
     * $injector->invoke($formatString, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first argument, and a formatter instance created
     * by the DI container as the second argument.
     *
     * @param callable $callable callable to be invoked.
     * @param array $arguments The array of the function arguments.
     * This can be either a list of arguments, or an associative array where keys are argument names.
     *
     * @return mixed The callable return value.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
     *
     * @throws MissingRequiredArgumentException if required argument is missing.
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function invoke(callable $callable, array $arguments = [], Mode $mode = Mode::Default, bool $auto = true, bool $dynamicArguments = true): mixed
    {
        $callable = $callable(...);
        if ($mode === Mode::Default) {
            $mode = $this->mode;
        }
        $mode = $this->getActualMode($mode, $this->depth);
        $funcReflection = new ReflectionFunction($callable);
        $resolver = match (true) {
            $auto && $dynamicArguments => fn() => ($this->injectorResolver)($funcReflection, Arg::resolveArguments($arguments, $this->container)),
            $auto && !$dynamicArguments => fn() => ($this->injectorResolver)($funcReflection, $arguments),
            !$auto && $dynamicArguments => fn() => Arg::resolveArguments($arguments, $this->container),
            default => static fn() => $arguments,
        };
        return $callable(...$this->moddedRun($mode, $resolver));
    }

    /**
     * Creates an object of a given class with resolving constructor dependencies based on parameter types.
     *
     * This method allows invoking a constructor and let type hinted parameter names to be
     * resolved as objects of the Container. It additionally allows calling constructor passing named arguments.
     *
     * For example, the following constructor may be invoked using the Container to resolve the formatter dependency:
     *
     * ```php
     * class StringFormatter
     * {
     *     public function __construct($string, \Yiisoft\I18n\MessageFormatterInterface $formatter)
     *     {
     *         // ...
     *     }
     * }
     *
     * $injector = new Yiisoft\Injector\Injector($container);
     * $stringFormatter = $injector->make(StringFormatter::class, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first argument, and a formatter instance created
     * by the DI container as the second argument.
     *
     * @param string $class name of the class to be created.
     * @param array $arguments The array of the function arguments.
     * This can be either a list of arguments, or an associative array where keys are argument names.
     *
     * @return object The object of the given class.
     *
     * @psalm-suppress MixedMethodCall
     *
     * @psalm-template T
     * @psalm-param class-string<T> $class
     * @psalm-return T
     * @throws InvalidArgumentException|MissingRequiredArgumentException
     * @throws ReflectionException
     *
     * @throws ContainerExceptionInterface
     */
    public function make(string $class, array $arguments = [], Mode $mode = Mode::Default, bool $auto = true, bool $dynamicArguments = true): object
    {
        if ($mode === Mode::Default) {
            $mode = $this->mode;
        }
        $mode = $this->getActualMode($mode, $this->depth);

        $classReflection = $this->getClassReflection($class);
        if (!$classReflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class $class is not instantiable.");
        }
        $funcReflection = $classReflection->getConstructor();

        if ($funcReflection) {
            $resolver = match (true) {
                $auto && $dynamicArguments => fn() => ($this->injectorResolver)($funcReflection, Arg::resolveArguments($arguments, $this->container, $class)),
                $auto && !$dynamicArguments => fn() => ($this->injectorResolver)($funcReflection, $arguments),
                !$auto && $dynamicArguments => fn() => Arg::resolveArguments($arguments, $this->container, $class),
                default => static fn() => $arguments,
            };
            $wrapper = $this->moddedRun(...);

            return match ($mode) {
                Mode::Ghost => $classReflection->newLazyGhost(static function (object $object) use ($resolver, $mode, $wrapper): void {
                    /** @psalm-suppress DirectConstructorCall For lazy ghosts we have to call the constructor directly */
                    $object->__construct(...$wrapper($mode, $resolver));
                }),
                Mode::Proxy => $classReflection->newLazyProxy(static function (object $object) use ($resolver, $mode, $wrapper): object {
                    return new ($object::class)(...$wrapper($mode, $resolver));
                }),
                default => new $class(...$wrapper($mode, $resolver)),
            };
        }

        return match ($mode) {
            Mode::Ghost => $classReflection->newLazyGhost(static function (object $object): void {
            }),
            Mode::Proxy => $classReflection->newLazyProxy(static function (object $object): object {
                return new ($object::class)();
            }),
            default => new $class(),
        };
    }

    public function moddedRun(Mode $mode, Closure $closure): mixed
    {
        $curMode = $this->mode;
        $this->mode = $mode;
        $this->depth++;
        $result = $closure();
        $this->depth--;
        $this->mode = $curMode;
        return $result;
    }

    /**
     * @psalm-param class-string $class
     *
     * @throws ReflectionException
     */
    public function getClassReflection(string $class): ReflectionClass
    {
        if ($this->cacheReflections) {
            return $this->reflectionsCache[$class] ??= new ReflectionClass($class);
        }

        return new ReflectionClass($class);
    }

    private function getActualMode(Mode $mode, int $depth): Mode
    {
        $mode = match ($mode) {
            Mode::NestedGhost => ($depth > 0) ? Mode::Ghost : Mode::Default,
            Mode::NestedProxy => ($depth > 0) ? Mode::Proxy : Mode::Default,
            default => $mode,
        };
        if ($mode === Mode::Default) {
            $mode = $this->mode !== Mode::Default ? $this->mode : Mode::Instance;
        }
        if ($mode === Mode::Instance && $depth > $this->maxDepth) {
           $mode = Mode::Ghost;
        }
        return $mode;
    }
}