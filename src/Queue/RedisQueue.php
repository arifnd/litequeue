<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Arifnd\LiteQueue\Jobs\RedisJob;
use Arifnd\LiteQueue\Redis\LuaScripts;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

class RedisQueue extends Queue
{
    protected ?int $retryAfter;

    protected ?int $blockFor;

    protected int $migrationBatchSize;

    protected bool $secondaryQueueHadJob = false;

    public function __construct(
        Container $container,
        protected RedisFactory $redis,
        string $default = 'default',
        protected ?string $connection = null,
        ?int $retryAfter = 90,
        ?int $blockFor = null,
        bool $dispatchAfterCommit = false,
        int $migrationBatchSize = -1,
    ) {
        parent::__construct($container, $default, $dispatchAfterCommit);

        $this->retryAfter = $retryAfter;
        $this->blockFor = $blockFor;
        $this->migrationBatchSize = $migrationBatchSize;
    }

    public function size($queue = null)
    {
        $queue = $this->getQueue($queue);

        return (int) $this->getConnection()->eval(
            LuaScripts::size(),
            3,
            $queue,
            $queue.':delayed',
            $queue.':reserved'
        );
    }

    public function pendingSize($queue = null)
    {
        return (int) $this->getConnection()->llen($this->getQueue($queue));
    }

    public function delayedSize($queue = null)
    {
        return (int) $this->getConnection()->zcard($this->getQueue($queue).':delayed');
    }

    public function reservedSize($queue = null)
    {
        return (int) $this->getConnection()->zcard($this->getQueue($queue).':reserved');
    }

    public function creationTimeOfOldestPendingJob($queue = null)
    {
        $payload = $this->getConnection()->lindex($this->getQueue($queue), 0);

        if (! $payload) {
            return null;
        }

        $data = json_decode($payload, true);

        return $data['createdAt'] ?? null;
    }

    /**
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array<string, mixed>  $options
     * @return string|null
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->getConnection()->eval(
            LuaScripts::push(),
            2,
            $this->getQueue($queue),
            $this->getQueue($queue).':notify',
            $payload
        );

        return json_decode($payload, true)['id'] ?? null;
    }

    protected function laterRaw($delay, $payload, $queue = null)
    {
        $this->getConnection()->eval(
            LuaScripts::later(),
            1,
            $this->getQueue($queue).':delayed',
            $this->availableAt($delay),
            $payload
        );

        return json_decode($payload, true)['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addPayloadMetadata(array $payload): array
    {
        return array_merge($payload, [
            'id' => JobId::random(),
            'attempts' => 0,
        ]);
    }

    /**
     * @param  string|null  $queue
     * @param  int  $index
     */
    public function pop($queue = null, $index = 0): ?RedisJob
    {
        $prefixed = $this->getQueue($queue);

        $this->migrate($prefixed);

        $block = ! $this->secondaryQueueHadJob && $index == 0;

        [$job, $reserved] = $this->retrieveNextJob($prefixed, $block);

        if ($index == 0) {
            $this->secondaryQueueHadJob = false;
        }

        if ($reserved) {
            if ($index > 0) {
                $this->secondaryQueueHadJob = true;
            }

            return new RedisJob(
                $this->container,
                $this,
                $job,
                $reserved,
                $this->connectionName,
                $queue ?: $this->default
            );
        }

        return null;
    }

    protected function migrate(string $queue): void
    {
        $this->migrateExpiredJobs($queue.':delayed', $queue);

        if ($this->retryAfter !== null) {
            $this->migrateExpiredJobs($queue.':reserved', $queue);
        }
    }

    /**
     * @return array<int, mixed>
     */
    public function migrateExpiredJobs(string $from, string $to): array
    {
        return (array) $this->getConnection()->eval(
            LuaScripts::migrateExpiredJobs(),
            3,
            $from,
            $to,
            $to.':notify',
            $this->currentTime(),
            $this->migrationBatchSize
        );
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function retrieveNextJob(string $queue, bool $block = true): array
    {
        $nextJob = $this->getConnection()->eval(
            LuaScripts::pop(),
            3,
            $queue,
            $queue.':reserved',
            $queue.':notify',
            $this->availableAt($this->retryAfter)
        );

        if (empty($nextJob)) {
            return [null, null];
        }

        [$job, $reserved] = $nextJob;

        if (! $job && $this->blockFor !== null && $block
            && $this->getConnection()->blpop([$queue.':notify'], $this->blockFor)) {
            return $this->retrieveNextJob($queue, false);
        }

        return [$job, $reserved];
    }

    /**
     * @param  string|null  $queue
     * @param  object  $job
     */
    public function deleteReserved($queue, $job): void
    {
        $this->getConnection()->zrem($this->getQueue($queue).':reserved', $job->getReservedJob());
    }

    /**
     * @param  string|null  $queue
     * @param  object  $job
     * @param  int  $delay
     */
    public function deleteAndRelease($queue, $job, $delay): void
    {
        $queue = $this->getQueue($queue);

        $this->getConnection()->eval(
            LuaScripts::release(),
            2,
            $queue.':delayed',
            $queue.':reserved',
            $job->getReservedJob(),
            $this->availableAt($delay)
        );
    }

    /**
     * @param  string|null  $queue
     */
    public function clear($queue): int
    {
        $queue = $this->getQueue($queue);

        return (int) $this->getConnection()->eval(
            LuaScripts::clear(),
            4,
            $queue,
            $queue.':delayed',
            $queue.':reserved',
            $queue.':notify'
        );
    }

    /**
     * @param  string|null  $queue
     */
    public function getQueue($queue): string
    {
        return 'queues:'.(string) QueueName::parse(is_string($queue) ? $queue : null, $this->default);
    }

    public function getConnection(): PhpRedisConnection|PredisConnection
    {
        /** @var PhpRedisConnection|PredisConnection $connection */
        $connection = $this->redis->connection($this->connection);

        return $connection;
    }

    public function getRedis(): RedisFactory
    {
        return $this->redis;
    }

    protected function currentTime(): int
    {
        return time();
    }
}
