# LiteQueue

A lightweight queue core for Laravel, compatible with **Laravel Queue** and **Laravel Horizon**.
It reimplements the queue function (dispatcher, connections, worker, job execution, failed jobs)
and ships a standalone `lq` CLI so it can run **with or without a full Laravel application**.

Horizon is optional: when installed (in the same or another project) LiteQueue can appear in its
dashboard as a supervisor by emulating Horizon's Redis supervisor state.

## Requirements

- PHP `^8.2`
- Laravel components `^11 || ^12 || ^13` (`illuminate/*`) — a full Laravel app is **not** required
- Redis is required only for the `litequeue_redis` driver

## Installation

```bash
composer require arifnd/litequeue
```

Publish the configuration (optional):

```bash
lq install
```

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
| 0.1.x | 8.2–8.5 | 11–13 | optional (n/a, 5.x, 6.x) |

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
- [Commands](docs/commands.md)
- [Testing](docs/testing.md)
- [Upgrade guide](docs/upgrade.md)
- [Release/versioning plan](docs/versioning.md)
- [ADR 0001 — Horizon compatibility](docs/adr/0001-horizon-compatibility.md)

## License

MIT. See [LICENSE](LICENSE).
