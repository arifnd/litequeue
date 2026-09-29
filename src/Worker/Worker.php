<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Worker;

use Arifnd\LiteQueue\Exceptions\MaxAttemptsExceededException;
use Arifnd\LiteQueue\Queue\WorkerOptions;
use Arifnd\LiteQueue\Support\JobEventFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Contracts\Queue\Factory as QueueManager;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobPopped;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Throwable;

class Worker
{
    protected ?Cache $cache = null;

    protected ?string $name = null;

    protected bool $shouldQuit = false;

    protected bool $paused = false;

    protected bool $lostConnection = false;

    protected int $status = 0;

    protected ?string $stopReason = null;

    public function __construct(
        protected QueueManager $manager,
        protected EventsDispatcher $events,
        protected ?ExceptionHandler $exceptions = null,
    ) {}

    public function runNextJob(string $connectionName, ?string $queue, WorkerOptions $options): void
    {
        $job = $this->getNextJob($this->manager->connection($connectionName), (string) ($queue ?: 'default'));

        if ($job) {
            $this->runJob($job, $connectionName, $options);

            return;
        }

        $this->sleep($options->sleep);
    }

    protected function runJob(JobContract $job, string $connectionName, WorkerOptions $options): void
    {
        try {
            $this->process($connectionName, $job, $options);
        } catch (Throwable $e) {
            $this->reportException($e);
        }
    }

    protected function getNextJob(object $connection, string $queue): ?JobContract
    {
        $this->raiseBeforeJobPopEvent($connection->getConnectionName(), $queue);

        try {
            foreach (explode(',', $queue) as $index => $name) {
                if ($name === '') {
                    continue;
                }

                if (($job = $connection->pop($name, $index)) !== null) {
                    $this->raiseAfterJobPopEvent($connection->getConnectionName(), $job);

                    return $job;
                }
            }
        } catch (Throwable $e) {
            $this->reportException($e);
            $this->sleep(1);
        }

        return null;
    }

    public function process(string $connectionName, JobContract $job, WorkerOptions $options): void
    {
        $exception = null;

        try {
            $this->raiseBeforeJobEvent($connectionName, $job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, (int) $options->maxTries);

            if ($job->isDeleted()) {
                return;
            }

            $job->fire();

            $this->raiseAfterJobEvent($connectionName, $job);
        } catch (Throwable $e) {
            $exception = $e;

            try {
                $this->handleJobException($connectionName, $job, $options, $e);
            } catch (Throwable $releaseException) {
                if ($releaseException !== $e) {
                    throw $releaseException;
                }
            }
        } finally {
            $this->events->dispatch(JobEventFactory::attempted($connectionName, $job, $exception));
        }
    }

    protected function handleJobException(string $connectionName, JobContract $job, WorkerOptions $options, Throwable $e): void
    {
        try {
            if (! $job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, (int) $options->maxTries, $e);
                $this->markJobAsFailedIfWillExceedMaxExceptions($connectionName, $job, $e);
            }

            $this->raiseExceptionOccurredJobEvent($connectionName, $job, $e);
        } finally {
            if (! $job->isDeleted() && ! $job->isReleased() && ! $job->hasFailed()) {
                $backoff = $this->calculateBackoff($job, $options);

                $job->release($backoff);

                $this->events->dispatch(new JobReleasedAfterException($connectionName, $job, $backoff));
            }
        }

        throw $e;
    }

    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(string $connectionName, JobContract $job, int $maxTries): void
    {
        $maxTries = ! is_null($job->maxTries()) ? (int) $job->maxTries() : $maxTries;

        $retryUntil = $job->retryUntil();

        if ($retryUntil && time() <= $retryUntil) {
            return;
        }

        if (! $retryUntil && ($maxTries === 0 || $job->attempts() <= $maxTries)) {
            return;
        }

        $this->failJob($job, $e = $this->maxAttemptsExceededException($job));

        throw $e;
    }

    protected function markJobAsFailedIfWillExceedMaxAttempts(string $connectionName, JobContract $job, int $maxTries, Throwable $e): void
    {
        $maxTries = ! is_null($job->maxTries()) ? (int) $job->maxTries() : $maxTries;

        if ($job->retryUntil() && $job->retryUntil() <= time()) {
            $this->failJob($job, $e);
        }

        if (! $job->retryUntil() && $maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $e);
        }
    }

    protected function markJobAsFailedIfWillExceedMaxExceptions(string $connectionName, JobContract $job, Throwable $e): void
    {
        if (! $this->cache || is_null($uuid = $job->uuid()) || is_null($maxExceptions = $job->maxExceptions())) {
            return;
        }

        $key = 'job-exceptions:'.$uuid;

        if (! $this->cache->get($key)) {
            $this->cache->put($key, 0, 86400);
        }

        if ($maxExceptions <= $this->cache->increment($key)) {
            $this->cache->forget($key);

            $this->failJob($job, $e);
        }
    }

    protected function failJob(JobContract $job, Throwable $e): void
    {
        $job->fail($e);
    }

    protected function calculateBackoff(JobContract $job, WorkerOptions $options): int
    {
        $backoff = method_exists($job, 'backoff') && ! is_null($job->backoff())
            ? $job->backoff()
            : $options->backoff;

        $backoff = is_array($backoff) ? $backoff : explode(',', (string) $backoff);

        return (int) ($backoff[$job->attempts() - 1] ?? end($backoff));
    }

    public function daemon(string $connectionName, ?string $queue, WorkerOptions $options): int
    {
        $this->listenForSignals();

        $lastRestart = $this->getTimestampOfLastQueueRestart();
        $startTime = time();
        $jobsProcessed = 0;

        while (true) {
            if ($this->shouldQuit) {
                break;
            }

            if ($this->memoryExceeded($options->memory)) {
                $this->stop(12, $options, 'memory limit exceeded');

                break;
            }

            if ($this->queueShouldRestart($lastRestart)) {
                $this->stop(0, $options, 'queue restarted');

                break;
            }

            $this->events->dispatch(new Looping($connectionName, $queue));

            $this->runNextJob($connectionName, $queue, $options);

            $jobsProcessed++;

            if ($options->stopWhenEmpty && $this->manager->connection($connectionName)->size($queue) === 0) {
                break;
            }

            if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
                break;
            }

            if ($options->maxTime > 0 && (time() - $startTime) >= $options->maxTime) {
                break;
            }

            if ($options->rest > 0) {
                $this->sleep($options->rest);
            }
        }

        $this->events->dispatch(new WorkerStopping($this->status));

        return $this->status;
    }

    protected function raiseBeforeJobPopEvent(?string $connectionName, ?string $queue = null): void
    {
        $this->events->dispatch(new JobPopping($connectionName, $queue));
    }

    protected function raiseAfterJobPopEvent(?string $connectionName, JobContract $job): void
    {
        $this->events->dispatch(new JobPopped($connectionName, $job));
    }

    protected function raiseBeforeJobEvent(string $connectionName, JobContract $job): void
    {
        $this->events->dispatch(new JobProcessing($connectionName, $job));
    }

    protected function raiseAfterJobEvent(string $connectionName, JobContract $job): void
    {
        $this->events->dispatch(new JobProcessed($connectionName, $job));
    }

    protected function raiseExceptionOccurredJobEvent(string $connectionName, JobContract $job, Throwable $e): void
    {
        $this->events->dispatch(new JobExceptionOccurred($connectionName, $job, $e));
    }

    protected function maxAttemptsExceededException(JobContract $job): MaxAttemptsExceededException
    {
        return new MaxAttemptsExceededException(
            ($job->resolveName() ?: 'Job').' has been attempted too many times or run too long.'
        );
    }

    public function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    public function sleep(int $seconds): void
    {
        sleep(max(0, $seconds));
    }

    protected function listenForSignals(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGINT, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGQUIT, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGUSR2, fn () => $this->paused = true);
    }

    protected function queueShouldRestart(?int $lastRestart): bool
    {
        return $this->getTimestampOfLastQueueRestart() != $lastRestart;
    }

    protected function getTimestampOfLastQueueRestart(): ?int
    {
        return $this->cache?->get('illuminate:queue:restart');
    }

    public function setCache(Cache $cache): void
    {
        $this->cache = $cache;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function stop(int $status = 0, ?WorkerOptions $options = null, ?string $reason = null): void
    {
        $this->status = $status;
        $this->stopReason = $reason;
        $this->shouldQuit = true;
    }

    public function kill(int $status = 0, ?WorkerOptions $options = null, ?string $reason = null): void
    {
        $this->stop($status, $options, $reason);
    }

    protected function reportException(Throwable $e): void
    {
        $this->exceptions?->report($e);
    }

    public function getManager(): QueueManager
    {
        return $this->manager;
    }
}
