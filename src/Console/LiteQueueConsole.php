<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Connections\ClosureConnector;
use Arifnd\LiteQueue\Connections\NullConnector;
use Arifnd\LiteQueue\Connections\RedisConnector;
use Arifnd\LiteQueue\Connections\SyncConnector;
use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Arifnd\LiteQueue\Failed\NullFailedJobProvider;
use Arifnd\LiteQueue\Queue\PayloadFactory;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Worker\Worker;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Events\Dispatcher as Events;
use Illuminate\Redis\RedisManager;
use Symfony\Component\Console\Application;

class LiteQueueConsole
{
    /**
     * @param  array<int, string>|null  $argv
     */
    public static function run(?array $argv = null): int
    {
        $container = static::bootstrap();

        $application = new Application('LiteQueue', static::version());

        foreach (static::commands() as $command) {
            $instance = new $command;
            $instance->setLaravel($container);
            $application->addCommand($instance);
        }

        return $application->run();
    }

    public static function version(): string
    {
        return '0.1.0';
    }

    /**
     * @return array<int, class-string>
     */
    protected static function commands(): array
    {
        return [
            Commands\MakeJobCommand::class,
            Commands\MakeServiceCommand::class,
            Commands\MakeTraitCommand::class,
            Commands\WorkCommand::class,
            Commands\SupervisorCommand::class,
            Commands\FailedCommand::class,
            Commands\RetryCommand::class,
            Commands\ForgetCommand::class,
            Commands\FlushCommand::class,
            Commands\InstallCommand::class,
        ];
    }

    public static function bootstrap(?string $basePath = null): Container
    {
        $basePath ??= getcwd() ?: '.';

        $container = new ConsoleContainer;
        Container::setInstance($container);

        $config = static::loadConfig($basePath);

        $container->instance('config', $config);
        $container->instance('litequeue.base_path', $basePath);

        $container->singleton(EventsDispatcher::class, fn () => new Events);
        $container->alias(EventsDispatcher::class, 'events');

        $container->singleton(Cache::class, fn () => new CacheRepository(new ArrayStore));

        $container->singleton(QueueManager::class, function ($c) use ($config) {
            $manager = new QueueManager($c, $config);

            $manager->addConnector('sync', new SyncConnector($c));
            $manager->addConnector('null', new NullConnector($c));

            if (class_exists(RedisConnector::class)) {
                $manager->addConnector('litequeue_redis', new ClosureConnector(
                    fn (array $connection) => (new RedisConnector($c, $c->make(RedisFactory::class)))->connect($connection)
                ));
            }

            return $manager;
        });
        $container->alias(QueueManager::class, QueueFactory::class);

        $container->singleton(PayloadFactory::class, fn ($c) => new PayloadFactory($c));

        $container->singleton(Dispatcher::class, function ($c) {
            $dispatcher = new Dispatcher($c);
            $dispatcher->setQueueResolver(
                fn ($connection = null) => $c->make(QueueManager::class)->connection($connection)
            );

            return $dispatcher;
        });
        $container->alias(Dispatcher::class, DispatcherContract::class);

        $container->singleton(Worker::class, fn ($c) => new Worker(
            $c->make(QueueManager::class),
            $c->make(EventsDispatcher::class),
        ));

        $container->singleton(FailedJobProvider::class, fn () => new NullFailedJobProvider);

        if (static::redisAvailable($config)) {
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

                return new RedisManager($c, $redis['client'] ?? 'phpredis', $redis);
            });
        }

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function loadConfig(string $basePath): array
    {
        $candidates = [
            $basePath.'/config/litequeue.php',
            $basePath.'/litequeue.php',
            dirname(__DIR__, 2).'/config/litequeue.php',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                $config = require $file;

                if (is_array($config)) {
                    return $config;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function redisAvailable(array $config): bool
    {
        return extension_loaded('redis') || class_exists('Predis\\Client') || ! empty($config['redis']);
    }
}
