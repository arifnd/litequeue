<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Jobs;

use Arifnd\LiteQueue\Queue\RedisQueue;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;

class RedisJob extends Job implements JobContract
{
    protected RedisQueue $redis;

    protected string $job;

    /**
     * @var array<string, mixed>
     */
    protected array $decoded;

    protected string $reserved;

    public function __construct(
        Container $container,
        RedisQueue $redis,
        string $job,
        string $reserved,
        ?string $connectionName,
        ?string $queue,
    ) {
        $this->job = $job;
        $this->redis = $redis;
        $this->queue = $queue;
        $this->reserved = $reserved;
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->decoded = $this->payload();
    }

    public function getRawBody()
    {
        return $this->job;
    }

    public function delete()
    {
        parent::delete();

        $this->redis->deleteReserved($this->queue, $this);
    }

    public function release($delay = 0)
    {
        parent::release($delay);

        $this->redis->deleteAndRelease($this->queue, $this, $delay);
    }

    public function attempts()
    {
        return (int) (($this->decoded['attempts'] ?? 0) + 1);
    }

    public function getJobId()
    {
        return $this->decoded['id'] ?? null;
    }

    public function getRedisQueue(): RedisQueue
    {
        return $this->redis;
    }

    public function getReservedJob(): string
    {
        return $this->reserved;
    }
}
