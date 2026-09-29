# LiteQueue

A lightweight queue core for Laravel, compatible with **Laravel Queue** and **Laravel Horizon**.
It reimplements the queue function (dispatcher, connections, worker, job execution, failed jobs)
and ships a standalone `lq` CLI so it can run **with or without a full Laravel application**.

Horizon is optional: when installed (in the same or another project) LiteQueue can appear in its
dashboard as a supervisor by emulating Horizon's Redis supervisor state.

## Requirements

- PHP `^8.2`
- Laravel components `^12 || ^13` (`illuminate/*`) — a full Laravel app is **not** required
- Redis is required only for the `litequeue_redis` driver

## Installation

```bash
composer require arifnd/litequeue
```

Publish the configuration (optional):

```bash
lq install
```

## Environment variables

For a simple single-Redis setup almost everything has a default — only the queue backend and (if
Redis is not on localhost) the host are needed:

```dotenv
QUEUE_CONNECTION=litequeue
APP_NAME=Laravel
# REDIS_HOST=127.0.0.1   # defaults to 127.0.0.1
```

`REDIS_PORT` (6379), `REDIS_PASSWORD` (none), `REDIS_DB` (0) and the queue name
(`REDIS_QUEUE` / `LITEQUEUE_QUEUE`, default `default`) all fall back automatically.

`APP_NAME` matters only for Horizon visibility: Horizon derives its Redis prefix from the app name
as `{app_name}_horizon:` (e.g. `laravel_horizon:`), and LiteQueue matches it.

Inside a Laravel app, `.env` is loaded automatically. The standalone `lq` binary does **not** load
a `.env` file — export these as real process environment variables (shell, Supervisor, Docker). See
[Configuration](docs/configuration.md#connecting-to-redis) for the full list, including
`REDIS_PREFIX`.

## Quick start (standalone, no Laravel app)

```bash
# Generate a job
lq make:job SendEmail

# Process jobs (single process)
lq work redis

# Or run as a Horizon-visible supervisor
lq supervisor redis
```

Dispatch from PHP:

```php
use App\Jobs\SendEmail;
use function Arifnd\LiteQueue\dispatch; // or use the Bus facade

SendEmail::dispatch($user);
```

## Quick start (inside Laravel)

The package auto-registers `Arifnd\LiteQueue\LiteQueueServiceProvider`. Configure a connection
and dispatch as usual:

```php
// config/queue.php (or config/litequeue.php)
'connections' => [
    'litequeue' => [
        'driver' => 'litequeue_redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

```php
SendEmail::dispatch($user)->onConnection('litequeue');
```

## Drivers

| Driver | Status | Notes |
| --- | --- | --- |
| `litequeue_redis` | Supported | Storage schema identical to Laravel's `RedisQueue`. |
| `sync` | Supported | Executes inline. |
| `null` | Supported | Drops jobs (testing). |

## Compatibility

| LiteQueue | PHP | Laravel | Horizon |
| --- | --- | --- | --- |
| 0.1.x | 8.2–8.5 | 12–13 | optional (n/a, 5.x, 6.x) |

The Redis queue schema, job payload, and `Illuminate\Queue\Events\*` event surface are frozen to
match Laravel, so Horizon metrics, `failed_jobs`, and external tooling interoperate.

## Horizon as a supervisor

LiteQueue uses its **own** configuration (it never edits `config/horizon.php`) and writes
Horizon-compatible supervisor state to the shared Redis. See [docs/supervisor.md](docs/supervisor.md).

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Jobs](docs/jobs.md)
- [Worker](docs/worker.md)
- [Supervisor & Horizon](docs/supervisor.md)
- [Deployment (Supervisor & Docker)](docs/deployment.md)
- [Commands](docs/commands.md)
- [Testing](docs/testing.md)
- [Upgrade guide](docs/upgrade.md)
- [Release/versioning plan](docs/versioning.md)
- [ADR 0001 — Horizon compatibility](docs/adr/0001-horizon-compatibility.md)

## License

MIT. See [LICENSE](LICENSE).
