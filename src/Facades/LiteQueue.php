<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Facades;

use Arifnd\LiteQueue\Queue\QueueManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Contracts\Queue\Queue connection(string|null $name = null)
 * @method static void extend(string $driver, \Closure $resolver)
 * @method static void purge(string|null $name = null)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 *
 * @see QueueManager
 */
class LiteQueue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QueueManager::class;
    }
}
