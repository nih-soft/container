# NIH Container

A lightweight PSR-11 dependency injection container for PHP `8.5+` with autowiring, lazy objects, and circular-reference support.

## Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
  - [Single Service Definitions: `auto()` and `manual()`](#single-service-definitions-auto-and-manual)
  - [Argument Configuration: `argument()` and `args()`](#argument-configuration-argument-and-args)
  - [Batch Definitions via `add()` and `replace()`](#batch-definitions-via-add-and-replace)
  - [Group Definitions: `inherit()`, `namespace()`, `regex()`](#group-definitions-inherit-namespace-regex)
- [Modes](#modes)
- [Argument Helpers](#argument-helpers)
- [Errors and Exceptions](#errors-and-exceptions)
- [Testing](#testing)
- [Acknowledgements](#acknowledgements)
- [License](#license)

## Features

- PSR-11 compatible container (`Psr\Container\ContainerInterface`).
- Autowiring via constructor type hints.
- Configurable object lifecycle:
  - non-shared (default): new instance on each `get()`;
  - shared: singleton-like instances.
- Lazy initialization modes (`Ghost`, `Proxy`, nested modes).
- Circular reference resolution.
- Argument helpers for dynamic wiring (`Arg::get()`, `Arg::new()`, `Arg::id()`).

## Installation

```bash
composer require nih/container
```

## Quick Start

```php
<?php

use NIH\Container\Container;
use NIH\Container\ContainerConfig;

$config = new ContainerConfig(shared: false);
$container = new Container($config);

$service = $container->get(App\Service::class);
```

By default, the container resolves classes and their dependencies via autowiring.

## Configuration

```php
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;

$config = new ContainerConfig(
    shared: true,
    mode: Mode::Ghost,
    cacheReflections: true,
    maxDepth: 5
);

$config->manual(App\Contracts\LoggerInterface::class)->to(App\Logger\FileLogger::class);
$config->value('api.base_url', 'https://api.example.com');
$config->alias('logger', App\Contracts\LoggerInterface::class);
```

`alias()` can reference both service IDs and plain value keys. Aliases are applied in both definition and value resolution paths.

Use `add()` and `replace()` for fast batch definition of multiple services, and `auto()` / `manual()` for detailed configuration of individual services.

### Single Service Definitions: `auto()` and `manual()`

Use these methods when you need explicit per-service configuration:

- `auto(ClassName::class)`: create/update a definition with autowiring enabled.
- `manual(ClassName::class)`: create/update a definition with autowiring disabled.

```php
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;

$config = new ContainerConfig();

$config->auto(App\Service::class)
    ->shared()
    ->mode(Mode::Proxy);

$config->manual(App\Contracts\LoggerInterface::class)
    ->to(App\Logger\FileLogger::class)
    ->argument('channel', 'app');
```

### Argument Configuration: `argument()` and `args()`

Both methods configure constructor/factory arguments for the current definition:

- `argument($nameOrIndex, $value)` sets one argument.
- `args(array $arguments)` sets the full argument map/list for the definition.

Override rules:

- Repeated `argument()` calls for the same argument overwrite the previous value.
- `args()` replaces all previously configured arguments for that definition.

Autowiring interaction:

- You can pass arguments for both manual bindings and auto-wired definitions.
- In auto-wiring mode, only arguments not explicitly provided via `argument()` / `args()` are resolved automatically by the container.

```php
$config->auto(App\Service::class)
    ->argument('dsn', 'sqlite::memory:')
    ->argument('dsn', 'pgsql:host=db;dbname=app'); // overwrites previous dsn

$config->manual(App\Formatter::class)
    ->argument('prefix', '[api]')
    ->args(['prefix' => '[worker]', 'suffix' => '!']);
// `args()` replaces previously configured arguments for this definition
```

### Batch Definitions via `add()` and `replace()`

Use `add()` to register entries only if they are not already defined. If an entry already exists, the new value is ignored.

Value handling rules in the input array:

- `string` value: treated as a target class name for service definition (`to` binding).
- `Closure` value: treated as a factory callback for service definition.
- `Definition` value: stored as-is.
- any other value: stored as a plain container value (`value()`-like entry).

If you need to store raw string data in the container, use `value()` explicitly.
String targets are not validated at configuration time; they are resolved and validated when `get()` / `new()` is called.

```php
use NIH\Container\ContainerConfig;

$config = new ContainerConfig();

$config->value('api.base_url', 'https://api.example.com');

$config->add([
    App\Contracts\LoggerInterface::class => App\Logger\FileLogger::class,
    App\Contracts\CacheInterface::class => App\Cache\RedisCache::class,
    App\Contracts\MailerInterface::class => App\Mailer\SmtpMailer::class,
]);
```

Use `replace()` to overwrite existing entries. If an entry already exists, the old value is replaced with the new one.
It uses the same value handling rules as `add()`.

```php
// initial definitions, added only to demonstrate replace() behavior on existing entries
$config->add([
    App\Contracts\LoggerInterface::class => App\Logger\FileLogger::class,
    App\Contracts\CacheInterface::class => App\Cache\RedisCache::class,
    App\Contracts\MailerInterface::class => App\Mailer\SmtpMailer::class,
]);

$config->value('api.base_url', 'https://api.dev.local');

$config->replace([
    App\Contracts\LoggerInterface::class => App\Logger\ConsoleLogger::class,
    App\Contracts\CacheInterface::class => App\Cache\ArrayCache::class,
    App\Contracts\MailerInterface::class => App\Mailer\NullMailer::class,
]);
```

For exact branching logic, see `ContainerConfig::add()` and `ContainerConfig::replace()`.

Resolution priority note:

| Input state for `$id` | `get($id)` | `new($id)` |
|---|---|---|
| scalar/array value exists | returns `value()` | returns `value()` |
| object value exists + definition/class exists | returns `value()` object | ignores object `value()`, instantiates via definition/class |
| object value exists + no definition/class | returns `value()` object | throws `ContainerNotFoundException` |
| only definition/class exists | instantiates via definition/class | instantiates via definition/class |

### Group Definitions: `inherit()`, `namespace()`, `regex()`

These methods let you define shared rules for multiple class entries at once:

- `inherit(BaseClass::class)`: applies to classes that extend/implement the given type.
- `namespace('App\\Service')`: applies to classes in the given namespace prefix.
- `regex('/^App\\\\Report\\\\.+$/')`: applies to classes matching the regex pattern.

```php
use NIH\Container\Arg;
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;


$config = new ContainerConfig();

$config->inherit(App\Contracts\RepositoryInterface::class)
    ->shared()
    ->mode(Mode::Proxy);

$config->namespace('App\\Handler')
    ->manual()
    ->argument('logger', Arg::get(App\Logger\FileLogger::class));

$config->regex('/^App\\\\Report\\\\.+$/')
    ->callback(
        static fn(App\Factory\ReportFactory $factory, string $id): object => $factory->create($id)
    )
    ->argument('id', Arg::id());
```

Group rules are evaluated when a class entry is resolved by id, and the matched rule is used as a template for its definition.

Group-specific note about `to()` and `callback()`:

- For group definitions, binding directly to a concrete target class is not meaningful.
- `to()` is used to reference an abstract factory via a string callable (for example, `App\\Factory\\ReportFactory::create`).
- `callback()` is used to provide an anonymous factory function.
- Edge case: if string `to()` is not callable, group definition resolution returns `null`, and container `get()`/`new()` will end with a NotFound error for that id.

### Binding Override Behavior

`to()` and `callback()` configure the same target binding (`Definition::$to`), so each subsequent call overrides the previous one.

```php
$config->manual(App\Contracts\LoggerInterface::class)
    ->to(App\Logger\FileLogger::class)
    ->callback(static fn(): App\Contracts\LoggerInterface => new App\Logger\ConsoleLogger());
// effective binding: callback, `to()` value is overwritten

$config->manual(App\Contracts\CacheInterface::class)
    ->callback(static fn(): App\Contracts\CacheInterface => new App\Cache\ArrayCache())
    ->to(App\Cache\RedisCache::class);
// effective binding: `to()`, callback is overwritten
```

### Factories via `callback()`

Factory callbacks are invoked through the internal instantiator. Parameters can be resolved automatically from type hints, and also passed explicitly via `argument()` and `args()`.

```php
use NIH\Container\ContainerConfig;
use NIH\Container\Mode;

$config = new ContainerConfig(shared: true);

$config->manual(App\Service::class)
    ->callback(
        static function (App\Contracts\LoggerInterface $logger): App\Service {
            return new App\Service($logger);
        }
    )
    ->mode(Mode::Proxy);
```

Abstract factory configuration with `Arg::id()`:

```php
use NIH\Container\Arg;
use NIH\Container\ContainerConfig;

$config = new ContainerConfig();

$config->namespace('App\\Report')
    ->callback(
        static function (App\Factory\ReportFactory $factory, string $id): object {
            return $factory->create($id);
        }
    )
    ->argument('id', Arg::id());
```

In this setup, `Arg::id()` resolves to the current requested entry id (for example, `App\Report\DailyReport::class`), and the abstract factory creates that class.

## Modes

- `Mode::Default`: uses container default mode; if both container mode and definition mode are `Mode::Default`, it effectively falls back to `Mode::Instance`.
- `Mode::Instance`: eager instantiation.
- `Mode::Ghost`: lazy ghost object.
- `Mode::Proxy`: lazy proxy object.
- `Mode::NestedGhost`: laziness for nested dependencies only.
- `Mode::NestedProxy`: proxy laziness for nested dependencies only.

Notes:

- `maxDepth` affects deep dependency graphs in `Mode::Instance`: when current depth is greater than `maxDepth`, container switches nested creation to `Mode::Ghost`.

## Argument Helpers

Argument helpers are runtime placeholders for definition arguments. They are resolved through the container when the target entry is instantiated.

- `Arg::get(string|Arg $id, ?Mode $mode = null)`:
  resolves the id and returns `$container->get($id)`.
- `Arg::new(string|Arg $id, ?Mode $mode = null)`:
  resolves the id and returns `$container->new($id)`.
- `Arg::id()`:
  returns the current resolving entry id (the id of the service being instantiated).

Basic usage:

```php
use NIH\Container\Arg;

$config->manual(App\Handler::class)
    ->argument('service', Arg::get(App\Service::class))
    ->argument('freshService', Arg::new(App\Service::class));
```

Dynamic id usage:

```php
$config->manual(App\ContextAwareHandler::class)
    ->argument('entryId', Arg::id());
```

In this example, `entryId` receives the id of the currently resolved entry (here: `App\ContextAwareHandler::class`).

Mode-aware usage:

```php
use NIH\Container\Mode;

$config->manual(App\LazyHandler::class)
    ->argument('service', Arg::get(App\Service::class, Mode::Ghost))
    ->argument('freshService', Arg::new(App\Service::class, Mode::Proxy));
```

## Errors and Exceptions

- `ContainerNotFoundException`: thrown by `get()` / `new()` when no definition/class can be resolved for the requested id.
- `ContainerException`: thrown when instantiation/invocation fails; original exception is wrapped as previous.
- Group edge case: if group `to()` contains a non-callable string factory reference, group resolution returns `null`, which leads to `ContainerNotFoundException` for that id.

## Testing

```bash
composer test
```

## Acknowledgements

Autowiring and dependency resolution are powered by the excellent `yiisoft/injector` library.

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
