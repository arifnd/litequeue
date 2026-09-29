<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Bootstrap;

use Illuminate\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\RedisManager;

final class RegisterRedis
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function register(Container $container, array $config): void
    {
        if (! self::available($config)) {
            return;
        }

        $container->singleton(RedisFactory::class, function ($c) use ($config) {
            $redis = $config['redis'] ?? [];

            if (empty($redis) || ! isset($redis['default'])) {
                $redis = [
                    'client' => extension_loaded('redis') ? 'phpredis' : 'predis',
                    'default' => [
                        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                        'password' => getenv('REDIS_PASSWORD') ?: null,
                        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                        'database' => (int) (getenv('REDIS_DB') ?: 0),
                    ],
                    'options' => ['prefix' => getenv('REDIS_PREFIX') ?: ''],
                ];
            }

            $redis = static::registerHorizonConnection($redis, $config);

            return new RedisManager($c, $redis['client'] ?? 'phpredis', $redis);
        });
    }

    /**
     * Ensure a Redis connection carrying the Horizon prefix exists for the
     * supervisor state, without disturbing the queue connection prefix.
     *
     * @param  array<string, mixed>  $redis
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function registerHorizonConnection(array $redis, array $config): array
    {
        $name = (string) ($config['supervisor']['horizon']['redis_connection'] ?? 'horizon');

        if (isset($redis[$name])) {
            return $redis;
        }

        $base = $redis['default'] ?? [];

        $redis[$name] = array_merge($base, [
            'options' => array_merge($base['options'] ?? [], [
                'prefix' => self::horizonPrefix($config),
            ]),
        ]);

        return $redis;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function horizonPrefix(array $config): string
    {
        return (string) ($config['supervisor']['horizon']['prefix'] ?? 'laravel_horizon:');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function available(array $config): bool
    {
        return extension_loaded('redis') || class_exists('Predis\\Client') || ! empty($config['redis']);
    }
}
