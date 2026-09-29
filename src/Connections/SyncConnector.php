<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Connections;

use Arifnd\LiteQueue\Contracts\ConnectorInterface;
use Arifnd\LiteQueue\Queue\SyncQueue;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue as QueueContract;

class SyncConnector implements ConnectorInterface
{
    public function __construct(protected Container $container) {}

    public function connect(array $config): QueueContract
    {
        $queue = new SyncQueue(
            $this->container,
            (string) ($config['queue'] ?? 'default'),
            (bool) ($config['after_commit'] ?? false),
        );

        return $queue->setConfig($config);
    }
}
