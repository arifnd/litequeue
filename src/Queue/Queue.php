<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;

abstract class Queue implements QueueContract
{
    protected ?string $connectionName = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    public function __construct(
        protected Container $container,
        protected string $default = 'default',
        protected bool $dispatchAfterCommit = false,
    ) {}

    abstract public function pushRaw($payload, $queue = null, array $options = []);

    abstract public function size($queue = null);

    abstract public function pop($queue = null);

    public function pendingSize($queue = null)
    {
        return $this->size($queue);
    }

    public function delayedSize($queue = null)
    {
        return 0;
    }

    public function reservedSize($queue = null)
    {
        return 0;
    }

    public function creationTimeOfOldestPendingJob($queue = null)
    {
        return null;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            fn ($payload, $queue) => $this->pushRaw($payload, $queue)
        );
    }

    public function pushOn($queue, $job, $data = '')
    {
        return $this->push($job, $data, $queue);
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data, $delay),
            $queue,
            $delay,
            fn ($payload, $queue, $delay) => $this->laterRaw($delay, $payload, $queue)
        );
    }

    public function laterOn($queue, $delay, $job, $data = '')
    {
        return $this->later($delay, $job, $data, $queue);
    }

    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * Push a raw payload onto the queue after (n) seconds.
     *
     * @param  DateTimeInterface|DateInterval|int  $delay
     * @param  string  $payload
     * @param  string|null  $queue
     * @return mixed
     */
    protected function laterRaw($delay, $payload, $queue = null)
    {
        return $this->pushRaw($payload, $queue);
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  string|object  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @param  DateTimeInterface|DateInterval|int|null  $delay
     */
    protected function createPayload($job, $queue, $data = '', $delay = null): string
    {
        $factory = $this->payloadFactory();

        $payload = $this->addPayloadMetadata($factory->makeArray($job, $queue, $data));
        $payload['delay'] = $delay === null ? null : $this->secondsUntil($delay);

        return $factory->encode($payload);
    }

    /**
     * Allow drivers to add metadata (for example a reserved id) to a payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addPayloadMetadata(array $payload): array
    {
        return $payload;
    }

    protected function payloadFactory(): PayloadFactory
    {
        return $this->container->make(PayloadFactory::class);
    }

    /**
     * Enqueue a job using the given callback, honoring "after commit" dispatch.
     *
     * @param  string|object  $job
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  DateTimeInterface|DateInterval|int|null  $delay
     * @param  callable  $callback
     * @return mixed
     */
    protected function enqueueUsing($job, $payload, $queue, $delay, $callback)
    {
        if ($this->shouldDispatchAfterCommit($job) && $this->container->bound('db.transactions')) {
            return $this->container->make('db.transactions')->addCallback(
                function () use ($queue, $job, $payload, $delay, $callback) {
                    $this->raiseJobQueueingEvent($queue, $job, $payload, $delay);

                    return tap($callback($payload, $queue, $delay), function ($jobId) use ($queue, $job, $payload, $delay) {
                        $this->raiseJobQueuedEvent($queue, $jobId, $job, $payload, $delay);
                    });
                }
            );
        }

        $this->raiseJobQueueingEvent($queue, $job, $payload, $delay);

        return tap($callback($payload, $queue, $delay), function ($jobId) use ($queue, $job, $payload, $delay) {
            $this->raiseJobQueuedEvent($queue, $jobId, $job, $payload, $delay);
        });
    }

    /**
     * @param  string|object  $job
     */
    protected function shouldDispatchAfterCommit($job): bool
    {
        if ($job instanceof ShouldQueueAfterCommit) {
            return ! (isset($job->afterCommit) && $job->afterCommit === false);
        }

        if (is_object($job) && property_exists($job, 'afterCommit')) {
            return (bool) $job->afterCommit;
        }

        return $this->dispatchAfterCommit;
    }

    /**
     * @param  string|object  $job
     * @param  DateTimeInterface|DateInterval|int|null  $delay
     */
    protected function raiseJobQueueingEvent($queue, $job, $payload, $delay): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobQueueing(
                $this->connectionName,
                $queue,
                $job,
                $payload,
                $delay === null ? null : $this->secondsUntil($delay),
            ));
        }
    }

    /**
     * @param  string|object  $job
     * @param  string|int|null  $jobId
     * @param  DateTimeInterface|DateInterval|int|null  $delay
     */
    protected function raiseJobQueuedEvent($queue, $jobId, $job, $payload, $delay): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobQueued(
                $this->connectionName,
                $queue,
                $jobId,
                $job,
                $payload,
                $delay === null ? null : $this->secondsUntil($delay),
            ));
        }
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function setConnectionName($name)
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * @param  DateTimeInterface|DateInterval|int|null  $delay
     */
    protected function secondsUntil($delay): int
    {
        return Delay::seconds($delay);
    }

    /**
     * @param  DateTimeInterface|DateInterval|int|null  $delay
     */
    protected function availableAt($delay = 0): int
    {
        return Delay::from($delay)->availableAt;
    }

    public function getQueue($queue): string
    {
        return (string) QueueName::parse($queue, $this->default);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
