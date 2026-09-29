<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Bootstrap;

use Arifnd\LiteQueue\Console\ExceptionHandler;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Events\Dispatcher as Events;

final class RegisterCore
{
    public static function register(Container $container): void
    {
        $container->singleton(EventsDispatcher::class, fn () => new Events);
        $container->alias(EventsDispatcher::class, 'events');

        $container->singleton(Cache::class, fn () => new CacheRepository(new ArrayStore));

        $container->singleton(ExceptionHandler::class, fn () => new ExceptionHandler);
        $container->alias(ExceptionHandler::class, ExceptionHandlerContract::class);
    }
}
