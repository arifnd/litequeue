<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Arifnd\LiteQueue\Jobs\SyncJob;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Throwable;

class SyncQueue extends Queue
{
    public function push($job, $data = '', $queue = null)
    {
        if ($this->shouldDispatchAfterCommit($job) && $this->container->bound('db.transactions')) {
            return $this->container->make('db.transactions')->addCallback(
                fn () => $this->executeJob($job, $data, $queue)
            );
        }

        return $this->executeJob($job, $data, $queue);
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    protected function executeJob($job, $data = '', $queue = null): int
    {
        $queueJob = $this->resolveJob($this->createPayload($job, $this->getQueue($queue), $data), $queue);

        try {
            $this->raiseBeforeJobEvent($queueJob);
            $queueJob->fire();
            $this->raiseAfterJobEvent($queueJob);
        } catch (Throwable $e) {
            $exceptionOccurred = true;
            $this->handleException($queueJob, $e);
        } finally {
            $this->raiseJobAttemptedEvent($queueJob, $exceptionOccurred ?? false);
        }

        return 0;
    }

    protected function resolveJob(string $payload, ?string $queue): SyncJob
    {
        return new SyncJob($this->container, $payload, $this->connectionName, $queue);
    }

    protected function handleException(SyncJob $queueJob, Throwable $e): void
    {
        $this->raiseExceptionOccurredJobEvent($queueJob, $e);

        $queueJob->fail($e);

        throw $e;
    }

    protected function raiseBeforeJobEvent(JobContract $job): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobProcessing($this->connectionName, $job));
        }
    }

    protected function raiseAfterJobEvent(JobContract $job): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobProcessed($this->connectionName, $job));
        }
    }

    protected function raiseJobAttemptedEvent(JobContract $job, bool $exceptionOccurred = false): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobAttempted($this->connectionName, $job, $exceptionOccurred));
        }
    }

    protected function raiseExceptionOccurredJobEvent(JobContract $job, Throwable $e): void
    {
        if ($this->container->bound('events')) {
            $this->container->make('events')->dispatch(new JobExceptionOccurred($this->connectionName, $job, $e));
        }
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return null;
    }

    public function size($queue = null)
    {
        return 0;
    }

    public function pop($queue = null)
    {
        return null;
    }
}
