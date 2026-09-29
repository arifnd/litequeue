<?php

declare(strict_types=1);

use Arifnd\LiteQueue\Bus\Dispatcher;

if (! function_exists('dispatch')) {
    function dispatch(mixed $job): mixed
    {
        return app(Dispatcher::class)->dispatch($job);
    }
}

if (! function_exists('dispatch_sync')) {
    function dispatch_sync(mixed $job): mixed
    {
        return app(Dispatcher::class)->dispatchSync($job);
    }
}

if (! function_exists('dispatch_if')) {
    function dispatch_if(mixed $condition, mixed $job): mixed
    {
        return $condition ? app(Dispatcher::class)->dispatch($job) : null;
    }
}

if (! function_exists('dispatch_after_response')) {
    function dispatch_after_response(mixed $job): void
    {
        app(Dispatcher::class)->dispatchAfterResponse($job);
    }
}
