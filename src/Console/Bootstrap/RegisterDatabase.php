<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Bootstrap;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;

final class RegisterDatabase
{
    /**
     * Boot a database manager and Eloquent so jobs can use models standalone.
     */
    public static function register(Container $container): void
    {
        $container->singleton('db.factory', fn ($c) => new ConnectionFactory($c));

        $container->singleton('db', fn ($c) => new DatabaseManager($c, $c->make('db.factory')));
        $container->alias('db', DatabaseManager::class);
        $container->alias('db', ConnectionResolverInterface::class);

        Model::setConnectionResolver($container->make('db'));
        Model::setEventDispatcher($container->make(EventsDispatcher::class));
    }
}
