# Installation

## Composer

```bash
composer require arifnd/litequeue
```

LiteQueue requires individual `illuminate/*` components, not the full `laravel/framework`.

## Standalone

The `lq` binary works without a Laravel application:

```bash
vendor/bin/lq list
vendor/bin/lq make:job SendEmail
vendor/bin/lq work redis
```

LiteQueue reads `config/litequeue.php` from the current working directory if present; otherwise it
falls back to the package defaults.

## In a Laravel app

The service provider is auto-discovered. Merged configuration is available under the `litequeue`
key. Publish it with:

```bash
php artisan vendor:publish --tag=litequeue-config
```

Commands are **not** registered with Artisan by design — use the `lq` binary.

## Requirements

- PHP `^8.2`
- `ext-redis` (phpredis) **or** `predis/predis` for the Redis driver
- `ext-pcntl` (optional) for signal handling in the worker/supervisor
