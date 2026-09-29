# Configuration

LiteQueue reads `config/litequeue.php`. The full defaults:

```php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => ['driver' => 'sync'],
        'null' => ['driver' => 'null'],
        'redis' => [
            'driver' => 'litequeue_redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
            'migration_batch_size' => -1,
        ],
        'litequeue' => [ /* same shape as redis */ ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

    'security' => [
        'sign_payloads' => env('LITEQUEUE_SIGN_PAYLOADS', false),
        'signing_key' => env('LITEQUEUE_SIGNING_KEY', env('APP_KEY')),
        'allowed_classes' => null,
    ],

    'supervisor' => [
        'enabled' => env('LITEQUEUE_SUPERVISOR', true),
        'name' => env('LITEQUEUE_SUPERVISOR_NAME', 'litequeue'),
        'environment' => env('APP_ENV', 'production'),
        'connection' => env('LITEQUEUE_SUPERVISOR_CONNECTION', 'redis'),
        'queue' => ['default'],
        'balance' => 'off',
        'maxProcesses' => 1,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 1,
        'timeout' => 60,
        'sleep' => 3,
        'rest' => 0,
        'autoScalingStrategy' => 'time',
        'horizon' => [
            'redis_connection' => env('LITEQUEUE_HORIZON_REDIS_CONNECTION', 'horizon'),
            'prefix' => env('HORIZON_PREFIX') ?: Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:',
            'ttl' => 30,
            'master_ttl' => 15,
            'heartbeat_interval' => 1,
        ],
    ],
];
```

## Connecting to Redis

LiteQueue uses `Illuminate\Redis\RedisManager`, so it speaks to Redis through either the
`ext-redis` (phpredis) extension or the pure-PHP `predis/predis` package. Install one of them:

```bash
pecl install redis          # phpredis (preferred)
# or
composer require predis/predis
```

The client is auto-detected: phpredis when the extension is loaded, otherwise Predis.

### Standalone (`lq`, no Laravel app)

Standalone runs read plain **process environment variables** — a `.env` file is only loaded when a
Laravel app bootstraps. Every variable has a sensible default, so a simple local Redis needs almost
no configuration:

| Variable | Default | Required? |
| --- | --- | --- |
| `REDIS_HOST` | `127.0.0.1` | only if Redis is not local |
| `REDIS_PORT` | `6379` | no |
| `REDIS_PASSWORD` | *(none)* | no |
| `REDIS_DB` | `0` | no |
| `REDIS_PREFIX` | *(empty)* | no |
| `REDIS_QUEUE` / `LITEQUEUE_QUEUE` | `default` | no |
| `APP_NAME` | `laravel` | only for Horizon visibility |

A minimal `.env` for a simple single-Redis connection:

```dotenv
QUEUE_CONNECTION=litequeue
APP_NAME=Laravel
# REDIS_HOST=127.0.0.1   # defaults to 127.0.0.1
```

Then select the Redis connection, either by default:

```bash
QUEUE_CONNECTION=redis lq work
```

or explicitly per command:

```bash
REDIS_HOST=10.0.0.5 lq work redis --queue=default
```

If none of these are set, LiteQueue builds this block automatically (adding a dedicated `horizon`
connection, see below):

```php
'redis' => [
    'client' => extension_loaded('redis') ? 'phpredis' : 'predis',
    'default' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'password' => getenv('REDIS_PASSWORD') ?: null,
        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        'database' => (int) (getenv('REDIS_DB') ?: 0),
    ],
    'options' => ['prefix' => getenv('REDIS_PREFIX') ?: ''],
    'horizon' => [ // supervisor state only; prefix from HORIZON_PREFIX / APP_NAME
        // ... same host/port/database as above, but
        'options' => ['prefix' => 'laravel_horizon:'],
    ],
],
```

The queue connection prefix (`REDIS_PREFIX`, default empty) and the Horizon supervisor prefix are
independent: LiteQueue derives the latter as
`env('HORIZON_PREFIX') ?: Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'`, exactly like
Horizon. Override it with `HORIZON_PREFIX` or
`litequeue.supervisor.horizon.prefix` (see [Supervisor & Horizon](supervisor.md)).

To configure anything the environment variables do not cover (TLS, Sentinel, clusters, multiple
databases, per-connection options), add your own `redis` block to `config/litequeue.php`. Each
queue connection's `connection` key (default `default`) selects the named entry under `redis`:

```php
'redis' => [
    'client' => 'phpredis',
    'default' => ['host' => '127.0.0.1', 'port' => 6379, 'database' => 0],
    'cache' => ['host' => '127.0.0.1', 'port' => 6379, 'database' => 1],
],

'connections' => [
    'redis' => [
        'driver' => 'litequeue_redis',
        'connection' => 'default', // -> redis.default
        'queue' => 'default',
    ],
],
```

### In a Laravel app

The service provider resolves Laravel's Redis factory
(`Illuminate\Contracts\Redis\Factory`), so connections come from your `config/database.php`
`redis` array rather than the LiteQueue config. Point a LiteQueue queue connection at one of them:

```php
// config/litequeue.php
'connections' => [
    'redis' => [
        'driver' => 'litequeue_redis',
        'connection' => 'default', // -> database.redis.default
        'queue' => 'default',
    ],
],
```

## Payload signing

Queue payloads are serialized PHP objects, so a writer to Redis can otherwise inject a job class of
their choosing. Payload signing adds integrity verification:

```php
'security' => [
    'sign_payloads' => true,
    'signing_key' => env('LITEQUEUE_SIGNING_KEY', env('APP_KEY')),
    // Optional: restrict which classes are unserialized (default: no restriction).
    'allowed_classes' => null,
],
```

When enabled, `PayloadFactory` signs the serialized command with HMAC-SHA256 and
`CallQueuedHandler` verifies it (with `hash_equals`) before unserializing; tampered payloads throw
`InvalidPayloadException` and are failed safely. Signing is **off by default** so payloads stay
byte-compatible with Laravel/Horizon. If you turn it on, every producer and consumer of the queue
must sign with the same key.

## Database & Eloquent

The standalone `lq` runner boots a database manager and Eloquent, so jobs can use models for CRUD
without a full Laravel application. Configuration lives in `config/database.php`:

```bash
lq install   # publishes config/litequeue.php and config/database.php
```

`lq install` also creates the SQLite file when the default connection is `sqlite`, so a fresh
project works out of the box. The shipped defaults use `env()` and cover `sqlite`, `mysql`, `pgsql`
and `sqlsrv`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=root
DB_PASSWORD=
```

The database manager is bound as `db` and the `DB` facade is available, so the query builder and
Eloquent both work — including per-model connections:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

DB::table('orders')->where('paid', true)->count();

final class Order extends Model
{
    protected $connection = 'mysql';
    protected $guarded = [];
}
```

LiteQueue does not manage your schema — create tables with your own migrations or tooling. Inside a
Laravel app this file is ignored: LiteQueue uses the application's `config/database.php` and DB
connection directly.

## Redis keys

Given queue name `Q` and connection prefix `P`:

| Key | Type | Meaning |
| --- | --- | --- |
| `Pqueues:Q` | list | pending jobs (RPUSH/LPOP) |
| `Pqueues:Q:delayed` | zset | delayed jobs, score = available-at |
| `Pqueues:Q:reserved` | zset | in-flight jobs, score = reserve deadline |
| `Pqueues:Q:notify` | list/pub-sub | wake blocked workers |

This is identical to Laravel's `RedisQueue`, so Horizon and external tooling interoperate.
