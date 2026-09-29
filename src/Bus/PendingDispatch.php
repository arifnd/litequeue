<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Bus;

use Arifnd\LiteQueue\Facades\Bus;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class PendingDispatch
{
    protected bool $afterResponse = false;

    public function __construct(
        protected object $job,
        protected bool $shouldDispatch = true,
    ) {}

    public function onConnection($connection): static
    {
        $this->job->connection = $connection;

        return $this;
    }

    public function onQueue($queue): static
    {
        $this->job->queue = $queue;

        return $this;
    }

    public function delay($delay): static
    {
        $this->job->delay = $delay;

        return $this;
    }

    public function withoutDelay(): static
    {
        $this->job->delay = null;

        return $this;
    }

    public function afterCommit(): static
    {
        $this->job->afterCommit = true;

        return $this;
    }

    public function beforeCommit(): static
    {
        $this->job->afterCommit = false;

        return $this;
    }

    public function afterResponse(bool $afterResponse = true): static
    {
        $this->afterResponse = $afterResponse;

        return $this;
    }

    public function chain($chain): static
    {
        if (method_exists($this->job, 'chain')) {
            $this->job->chain($chain);
        }

        return $this;
    }

    public function getJob(): object
    {
        return $this->job;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->job, $method)) {
            $result = $this->job->{$method}(...$parameters);

            return $result === null ? $this : $result;
        }

        return $this;
    }

    public function __destruct()
    {
        if (! $this->shouldDispatch) {
            return;
        }

        if (! $this->shouldDispatch()) {
            return;
        }

        if ($this->afterResponse) {
            Bus::dispatchAfterResponse($this->job);

            return;
        }

        Bus::dispatch($this->job);
    }

    /**
     * Determine if the job should be dispatched (honoring unique-job locks).
     */
    protected function shouldDispatch(): bool
    {
        if (! $this->job instanceof ShouldBeUnique) {
            return true;
        }

        $container = Container::getInstance();

        if (! $container->bound(Cache::class)) {
            return true;
        }

        return (new UniqueLock($container->make(Cache::class)))->acquire($this->job);
    }
}
