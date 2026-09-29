<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Testing;

use Closure;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

class QueueFake implements QueueContract
{
    /**
     * @var array<int, object>
     */
    protected array $jobs = [];

    protected ?string $connectionName = null;

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushOn($queue, $job, $data);
    }

    public function pushOn($queue, $job, $data = '')
    {
        $this->jobs[] = (object) ['job' => $job, 'queue' => $queue, 'data' => $data];

        return null;
    }

    /**
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array<string, mixed>  $options
     * @return null
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->jobs[] = (object) ['job' => $payload, 'queue' => $queue, 'data' => ''];

        return null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushOn($queue, $job, $data);
    }

    public function laterOn($queue, $delay, $job, $data = '')
    {
        return $this->pushOn($queue, $job, $data);
    }

    /**
     * @param  mixed  $jobs
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        foreach ((array) $jobs as $job) {
            $this->pushOn($queue, $job, $data);
        }
    }

    public function size($queue = null)
    {
        return count($this->jobs);
    }

    public function pop($queue = null)
    {
        return null;
    }

    /**
     * @param  string|null  $queue
     * @return int
     */
    public function pendingSize($queue = null)
    {
        return count($this->jobs);
    }

    /**
     * @param  string|null  $queue
     * @return int
     */
    public function delayedSize($queue = null)
    {
        return 0;
    }

    /**
     * @param  string|null  $queue
     * @return int
     */
    public function reservedSize($queue = null)
    {
        return 0;
    }

    /**
     * @param  string|null  $queue
     * @return int|null
     */
    public function creationTimeOfOldestPendingJob($queue = null)
    {
        return null;
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
     * @param  mixed  $job
     * @return Collection<int, object>
     */
    public function pushed($job = null, ?Closure $callback = null): Collection
    {
        return (new Collection($this->jobs))->filter(function (object $record) use ($job, $callback) {
            if ($job !== null) {
                $class = is_object($record->job) ? get_class($record->job) : (string) $record->job;

                if ($class !== $job) {
                    return false;
                }
            }

            return $callback ? $callback($record->job, $record->queue, $record->data) : true;
        })->values();
    }

    /**
     * @param  mixed  $job
     */
    public function assertPushed($job, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->pushed($job, $callback)->count() > 0,
            "The expected [{$job}] job was not pushed."
        );
    }

    /**
     * @param  mixed  $job
     */
    public function assertNotPushed($job, ?Closure $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->pushed($job, $callback)->count() > 0,
            "The unexpected [{$job}] job was pushed."
        );
    }

    /**
     * @param  mixed  $job
     */
    public function assertPushedOn(string $queue, $job, ?Closure $callback = null): void
    {
        $this->assertPushed($job, function ($job, $pushedQueue, $data) use ($queue, $callback) {
            return $pushedQueue === $queue && ($callback ? $callback($job, $pushedQueue, $data) : true);
        });
    }

    /**
     * @param  mixed  $job
     */
    public function assertPushedTimes($job, int $times = 1): void
    {
        $count = $this->pushed($job)->count();

        PHPUnit::assertSame($times, $count, "The [{$job}] job was pushed {$count} times instead of {$times} times.");
    }

    public function assertNothingPushed(): void
    {
        PHPUnit::assertEmpty($this->jobs, 'Jobs were pushed unexpectedly.');
    }
}
