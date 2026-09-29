<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'null' => [
            'driver' => 'null',
        ],

        'redis' => [
            'driver' => 'litequeue_redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
            'migration_batch_size' => -1,
        ],

        'litequeue' => [
            'driver' => 'litequeue_redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('LITEQUEUE_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
            'migration_batch_size' => -1,
        ],

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
        'workers_name' => 'default',
        'balance' => 'off',
        'maxProcesses' => 1,
        'minProcesses' => 1,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 1,
        'timeout' => 60,
        'sleep' => 3,
        'rest' => 0,
        'nice' => 0,
        'backoff' => 0,
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
