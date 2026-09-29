<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Arifnd\LiteQueue\Connections\ClosureConnector;
use Arifnd\LiteQueue\Contracts\ConnectorInterface;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory as FactoryContract;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use InvalidArgumentException;

class QueueManager implements FactoryContract
{
    /**
     * @var array<string, ConnectorInterface>
     */
    protected array $connectors = [];

    /**
     * @var array<string, QueueContract>
     */
    protected array $connections = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * Resolve a queue connection instance.
     */
    public function connection($name = null): QueueContract
    {
        $name = is_string($name) && $name !== '' ? $name : $this->getDefaultDriver();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    /**
     * Register a connector resolver for the given driver.
     */
    public function addConnector(string $driver, ConnectorInterface|Closure $resolver): void
    {
        $this->connectors[$driver] = $resolver instanceof Closure
            ? new ClosureConnector($resolver)
            : $resolver;
    }

    /**
     * Register a custom driver creator closure.
     *
     * @param  Closure(array<string, mixed>): QueueContract  $resolver
     */
    public function extend(string $driver, Closure $resolver): void
    {
        $this->addConnector($driver, $resolver);
    }

    public function getConnector(string $driver): ?ConnectorInterface
    {
        return $this->connectors[$driver] ?? null;
    }

    protected function resolve(string $name): QueueContract
    {
        $config = $this->configuration($name);
        $driver = $config['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException("Queue connection [{$name}] has no driver configured.");
        }

        if (! isset($this->connectors[$driver])) {
            throw new InvalidArgumentException(
                "Queue connection [{$name}] uses driver [{$driver}] which is not registered."
            );
        }

        $queue = $this->connectors[$driver]->connect($config);
        $queue->setConnectionName($name);

        return $queue;
    }

    /**
     * @return array<string, mixed>
     */
    protected function configuration(string $name): array
    {
        $config = $this->config['connections'][$name] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException("Queue connection [{$name}] is not configured.");
        }

        return $config;
    }

    public function getDefaultDriver(): string
    {
        $default = $this->config['default'] ?? 'sync';

        return is_string($default) && $default !== '' ? $default : 'sync';
    }

    public function setDefaultDriver(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Disconnect from the given connection, or all connections.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];

            return;
        }

        unset($this->connections[$name]);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
