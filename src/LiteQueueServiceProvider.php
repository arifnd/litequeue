<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Connections\RedisConnector;
use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Arifnd\LiteQueue\Support\QueueRegistrar;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;

class LiteQueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/litequeue.php', 'litequeue');

        QueueRegistrar::register($this->app, (array) ($this->app['config']['litequeue'] ?? []));

        // Laravel's BusServiceProvider is deferred; using an extender guarantees our
        // dispatcher replaces its binding whenever the contract is resolved.
        $this->app->extend(
            DispatcherContract::class,
            fn ($dispatcher, $app) => $app->make(Dispatcher::class)
        );
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
            return (new RedisConnector($this->app, $this->app->make(Factory::class)))->connect($config);
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
