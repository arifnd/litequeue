<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue;

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
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;

class LiteQueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/litequeue.php', 'litequeue');

        $this->registerQueueManager();
        $this->registerPayloadFactory();
        $this->registerDispatcher();
        $this->registerWorker();
        $this->registerFailedJobProvider();
    }

    public function boot(): void
    {
        $this->registerJobFailedListener();
        $this->registerQueueDriverExtensions();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/litequeue.php' => $this->configPath(),
            ], 'litequeue-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->databasePath(),
            ], 'litequeue-migrations');

            $this->publishes([
                __DIR__.'/../stubs' => $this->basePath('stubs/litequeue'),
            ], 'litequeue-stubs');
        }
    }

    protected function registerQueueManager(): void
    {
        $this->app->singleton(QueueManager::class, function ($app) {
            $manager = new QueueManager($app, $app['config']['litequeue'] ?? []);

            $manager->addConnector('sync', new SyncConnector($app));
            $manager->addConnector('null', new NullConnector($app));
            $manager->addConnector('litequeue_redis', new ClosureConnector(
                fn (array $connection) => (new RedisConnector($app, $app->make(\Illuminate\Contracts\Redis\Factory::class)))->connect($connection)
            ));

            return $manager;
        });

        $this->app->singleton(
            Factory::class,
            fn ($app) => $app->make(QueueManager::class)
        );
    }

    protected function registerPayloadFactory(): void
    {
        $this->app->singleton(PayloadFactory::class, fn ($app) => new PayloadFactory($app));
    }

    protected function registerDispatcher(): void
    {
        $this->app->singleton(Dispatcher::class, function ($app) {
            $dispatcher = new Dispatcher($app);
            $dispatcher->setQueueResolver(
                fn ($connection = null) => $app->make(QueueManager::class)->connection($connection)
            );

            return $dispatcher;
        });

        // Laravel's BusServiceProvider is deferred; using an extender guarantees our
        // dispatcher replaces its binding whenever the contract is resolved.
        $this->app->extend(
            \Illuminate\Contracts\Bus\Dispatcher::class,
            fn ($dispatcher, $app) => $app->make(Dispatcher::class)
        );
    }

    protected function registerWorker(): void
    {
        $this->app->singleton(Worker::class, function ($app) {
            $worker = new Worker($app->make(QueueManager::class), $app->make('events'));

            if ($app->bound('cache')) {
                $worker->setCache($app->make('cache')->store());
            }

            return $worker;
        });
    }

    protected function registerFailedJobProvider(): void
    {
        $this->app->singleton(FailedJobProvider::class, function ($app) {
            $config = $app['config']['litequeue.failed'] ?? [];
            $driver = $config['driver'] ?? 'null';

            if (in_array($driver, ['database', 'database-uuids'], true) && $app->bound('db')) {
                return new DatabaseFailedJobProvider(
                    $app['db'],
                    (string) ($config['database'] ?? 'default'),
                    (string) ($config['table'] ?? 'failed_jobs'),
                );
            }

            return new NullFailedJobProvider;
        });
    }

    protected function registerJobFailedListener(): void
    {
        if (! $this->app->bound('events')) {
            return;
        }

        $this->app['events']->listen(JobFailed::class, function (JobFailed $event): void {
            $this->app->make(FailedJobProvider::class)->log(
                (string) $event->connectionName,
                (string) $event->job->getQueue(),
                (string) $event->job->getRawBody(),
                $event->exception,
            );
        });
    }

    protected function registerQueueDriverExtensions(): void
    {
        if (! $this->app->bound('queue')) {
            return;
        }

        $this->app['queue']->extend('litequeue_redis', function (array $config) {
            return (new RedisConnector($this->app, $this->app->make(\Illuminate\Contracts\Redis\Factory::class)))->connect($config);
        });
    }

    protected function configPath(): string
    {
        return $this->basePath('config/litequeue.php');
    }

    protected function databasePath(): string
    {
        return $this->basePath('database/migrations');
    }

    protected function basePath(string $path = ''): string
    {
        return $this->app->basePath($path);
    }
}
