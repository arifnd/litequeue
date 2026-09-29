<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Connections;

use Arifnd\LiteQueue\Contracts\ConnectorInterface;
use Arifnd\LiteQueue\Queue\RedisQueue;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class RedisConnector implements ConnectorInterface
{
    public function __construct(
        protected Container $container,
        protected RedisFactory $redis,
    ) {}

    public function connect(array $config): QueueContract
    {
        $queue = new RedisQueue(
            $this->container,
            $this->redis,
            (string) ($config['queue'] ?? 'default'),
            isset($config['connection']) ? (string) $config['connection'] : null,
            (int) ($config['retry_after'] ?? 90),
            isset($config['block_for']) ? (int) $config['block_for'] : null,
            (bool) ($config['after_commit'] ?? false),
            (int) ($config['migration_batch_size'] ?? -1),
        );

        return $queue->setConfig($config);
    }
}
