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
            'redis_connection' => env('LITEQUEUE_HORIZON_REDIS_CONNECTION', 'default'),
            'prefix' => env('HORIZON_PREFIX', 'laravel_horizon:'),
            'ttl' => 30,
            'master_ttl' => 15,
            'heartbeat_interval' => 1,
        ],
    ],
];
```

## Redis keys

Given queue name `Q` and connection prefix `P`:

| Key | Type | Meaning |
| --- | --- | --- |
| `Pqueues:Q` | list | pending jobs (RPUSH/LPOP) |
| `Pqueues:Q:delayed` | zset | delayed jobs, score = available-at |
| `Pqueues:Q:reserved` | zset | in-flight jobs, score = reserve deadline |
| `Pqueues:Q:notify` | list/pub-sub | wake blocked workers |

This is identical to Laravel's `RedisQueue`, so Horizon and external tooling interoperate.
