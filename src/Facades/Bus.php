<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Facades;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed dispatch(mixed $command)
 * @method static mixed dispatchSync(mixed $command, mixed $handler = null)
 * @method static mixed dispatchNow(mixed $command, mixed $handler = null)
 * @method static void dispatchAfterResponse(mixed $command, mixed $handler = null)
 *
 * @see Dispatcher
 */
class Bus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Dispatcher::class;
    }
}
