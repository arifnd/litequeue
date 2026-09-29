<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Support;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Connections\ClosureConnector;
use Arifnd\LiteQueue\Connections\NullConnector;
use Arifnd\LiteQueue\Connections\RedisConnector;
use Arifnd\LiteQueue\Connections\SyncConnector;
use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Arifnd\LiteQueue\Failed\DatabaseFailedJobProvider;
use Arifnd\LiteQueue\Failed\NullFailedJobProvider;
use Arifnd\LiteQueue\Queue\PayloadFactory;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Worker\Worker;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * Registers the queue bindings shared by the standalone runner and the
 * Laravel service provider.
 */
final class QueueRegistrar
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function register(Container $app, array $config): void
    {
        self::registerManager($app, $config);
        self::registerPayloadFactory($app);
        self::registerDispatcher($app);
        self::registerWorker($app);
        self::registerFailedJobProvider($app, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function registerManager(Container $app, array $config): void
    {
        $app->singleton(QueueManager::class, function ($c) use ($config) {
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

        $app->alias(QueueManager::class, QueueFactory::class);
    }

    protected static function registerPayloadFactory(Container $app): void
    {
        $app->singleton(PayloadFactory::class, fn ($c) => new PayloadFactory($c));
    }

    protected static function registerDispatcher(Container $app): void
    {
        $app->singleton(Dispatcher::class, function ($c) {
            $dispatcher = new Dispatcher($c);
            $dispatcher->setQueueResolver(
                fn ($connection = null) => $c->make(QueueManager::class)->connection($connection)
            );

            return $dispatcher;
        });

        $app->bind(DispatcherContract::class, fn ($c) => $c->make(Dispatcher::class));
    }

    protected static function registerWorker(Container $app): void
    {
        $app->singleton(Worker::class, function ($c) {
            $worker = new Worker(
                $c->make(QueueManager::class),
                $c->make('events'),
                $c->bound(ExceptionHandler::class) ? $c->make(ExceptionHandler::class) : null,
            );

            if ($c->bound('cache')) {
                $worker->setCache($c->make('cache')->store());
            }

            return $worker;
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function registerFailedJobProvider(Container $app, array $config): void
    {
        $app->singleton(FailedJobProvider::class, function ($c) use ($config) {
            $failed = $config['failed'] ?? [];
            $driver = $failed['driver'] ?? 'null';

            if (in_array($driver, ['database', 'database-uuids'], true) && $c->bound('db')) {
                return new DatabaseFailedJobProvider(
                    $c->make('db'),
                    (string) ($failed['database'] ?? 'default'),
                    (string) ($failed['table'] ?? 'failed_jobs'),
                );
            }

            return new NullFailedJobProvider;
        });
    }
}
