<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Bootstrap;

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
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

final class RegisterQueue
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function register(Container $container, array $config): void
    {
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
            $c->make(ExceptionHandlerContract::class),
        ));

        $container->singleton(FailedJobProvider::class, fn () => new NullFailedJobProvider);
    }
}
