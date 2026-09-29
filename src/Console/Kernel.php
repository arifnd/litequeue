<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

use Arifnd\LiteQueue\Console\Bootstrap\RegisterCore;
use Arifnd\LiteQueue\Console\Bootstrap\RegisterDatabase;
use Arifnd\LiteQueue\Console\Bootstrap\RegisterRedis;
use Arifnd\LiteQueue\Queue\PayloadFactory;
use Arifnd\LiteQueue\Support\QueueRegistrar;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

class Kernel
{
    protected static ?Container $previousContainer = null;

    protected static ?ApplicationContract $previousFacadeApp = null;

    protected static ?ConnectionResolverInterface $previousModelResolver = null;

    protected static ?EventsDispatcher $previousModelDispatcher = null;

    protected static bool $bootstrapped = false;

    public static function bootstrap(?string $basePath = null): Container
    {
        $basePath ??= getcwd() ?: '.';

        static::captureGlobalState();

        $container = new ConsoleContainer;
        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $loader = new ConfigLoader($basePath);
        $config = $loader->litequeue();

        // The standalone runner has no migrated failed_jobs table unless the user
        // provides one, so only use the database driver when explicitly configured.
        if (getenv('QUEUE_FAILED_DRIVER') === false && isset($config['failed'])) {
            $config['failed']['driver'] = 'null';
        }

        $container->instance('config', new Repository($config + ['database' => $loader->database()]));
        $container->instance('litequeue.base_path', $basePath);

        RegisterCore::register($container);
        RegisterDatabase::register($container);
        QueueRegistrar::register($container, $config);
        RegisterRedis::register($container, $config);

        return $container;
    }

    /**
     * Restore the global state replaced by bootstrap (container, facades, Eloquent).
     */
    public static function reset(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(static::$previousFacadeApp);

        if (static::$previousContainer !== null) {
            Container::setInstance(static::$previousContainer);
        }

        if (static::$previousModelResolver !== null) {
            Model::setConnectionResolver(static::$previousModelResolver);
        } else {
            Model::unsetConnectionResolver();
        }

        if (static::$previousModelDispatcher !== null) {
            Model::setEventDispatcher(static::$previousModelDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }

        PayloadFactory::createPayloadUsing(null);
        static::$bootstrapped = false;
    }

    /**
     * Snapshot the global state that bootstrap mutates so it can be restored.
     */
    protected static function captureGlobalState(): void
    {
        if (static::$bootstrapped) {
            return;
        }

        static::$previousContainer = Container::getInstance();
        static::$previousFacadeApp = Facade::getFacadeApplication();
        static::$previousModelResolver = Model::getConnectionResolver();
        static::$previousModelDispatcher = Model::getEventDispatcher();
        static::$bootstrapped = true;
    }
}
