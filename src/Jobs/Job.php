<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Jobs;

use Arifnd\LiteQueue\Exceptions\ManuallyFailedException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\JobName;
use Throwable;

abstract class Job implements JobContract
{
    protected mixed $instance = null;

    protected Container $container;

    protected bool $deleted = false;

    protected bool $released = false;

    protected bool $failed = false;

    protected ?string $connectionName = null;

    protected ?string $queue = null;

    abstract public function getJobId();

    abstract public function getRawBody();

    abstract public function attempts();

    public function uuid()
    {
        return $this->payload()['uuid'] ?? null;
    }

    public function fire()
    {
        $payload = $this->payload();

        [$class, $method] = JobName::parse($payload['job']);

        ($this->instance = $this->resolve($class))->{$method}($this, $payload['data']);
    }

    public function delete()
    {
        $this->deleted = true;
    }

    public function isDeleted()
    {
        return $this->deleted;
    }

    public function release($delay = 0)
    {
        $this->released = true;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function isDeletedOrReleased()
    {
        return $this->isDeleted() || $this->isReleased();
    }

    public function hasFailed()
    {
        return $this->failed;
    }

    public function markAsFailed()
    {
        $this->failed = true;
    }

    public function fail($e = null)
    {
        $this->markAsFailed();

        if ($this->isDeleted()) {
            return;
        }

        try {
            $this->delete();
            $this->failed($e);
        } finally {
            $this->raiseFailedJobEvent($e);
        }
    }

    protected function failed($e): void
    {
        $payload = $this->payload();

        [$class] = JobName::parse($payload['job']);

        if (method_exists($this->instance = $this->resolve($class), 'failed')) {
            $this->instance->failed($payload['data'], $e, $payload['uuid'] ?? '', $this);
        }
    }

    protected function raiseFailedJobEvent(?Throwable $e): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobFailed(
                $this->connectionName,
                $this,
                $e ?: new ManuallyFailedException
            ));
        }
    }

    protected function resolve(string $class): mixed
    {
        return $this->container->make($class);
    }

    public function getResolvedJob(): mixed
    {
        return $this->instance;
    }

    public function payload(): array
    {
        return json_decode($this->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function maxTries()
    {
        return $this->payload()['maxTries'] ?? null;
    }

    public function maxExceptions()
    {
        return $this->payload()['maxExceptions'] ?? null;
    }

    public function shouldFailOnTimeout()
    {
        return $this->payload()['failOnTimeout'] ?? false;
    }

    public function backoff()
    {
        return $this->payload()['backoff'] ?? $this->payload()['delay'] ?? null;
    }

    public function timeout()
    {
        return $this->payload()['timeout'] ?? null;
    }

    public function retryUntil()
    {
        return $this->payload()['retryUntil'] ?? null;
    }

    public function getName()
    {
        return $this->payload()['job'];
    }

    public function resolveName()
    {
        return JobName::resolve($this->getName(), $this->payload());
    }

    public function resolveQueuedJobClass()
    {
        return JobName::resolveClassName($this->getName(), $this->payload());
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
