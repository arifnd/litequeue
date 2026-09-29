<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Queue\WorkerOptions;
use Arifnd\LiteQueue\Tests\Fixtures\FakeJob;
use Arifnd\LiteQueue\Tests\Fixtures\ScriptedQueue;
use Arifnd\LiteQueue\Tests\TestCase;
use Arifnd\LiteQueue\Worker\Worker;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;

final class WorkerTest extends TestCase
{
    private ScriptedQueue $queue;

    private QueueManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new ScriptedQueue($this->app, 'default');

        $this->manager = new QueueManager($this->app, [
            'default' => 'scripted',
            'connections' => ['scripted' => ['driver' => 'scripted']],
        ]);
        $this->manager->extend('scripted', fn () => $this->queue);
    }

    private function processJob(FakeJob $job, WorkerOptions $options): void
    {
        $this->queue->enqueue($job);

        (new Worker($this->manager, $this->app['events']))
            ->runNextJob('scripted', 'default', $options);
    }

    public function test_it_processes_a_successful_job(): void
    {
        Event::fake([JobProcessing::class, JobProcessed::class, JobAttempted::class]);

        $job = new FakeJob;
        $this->processJob($job, new WorkerOptions(maxTries: 3));

        $this->assertTrue($job->fired);
        Event::assertDispatched(JobProcessing::class);
        Event::assertDispatched(JobProcessed::class);
        Event::assertDispatched(JobAttempted::class);
    }

    public function test_it_releases_a_failing_job_for_retry(): void
    {
        Event::fake([JobReleasedAfterException::class]);

        $job = new FakeJob(attempts: 1, shouldFail: true);
        $this->processJob($job, new WorkerOptions(maxTries: 3, backoff: 5));

        $this->assertTrue($job->released);
        $this->assertSame(5, $job->releaseDelay);
        $this->assertFalse($job->failed);
        Event::assertDispatched(JobReleasedAfterException::class);
    }

    public function test_it_fails_a_job_that_exhausted_attempts(): void
    {
        $job = new FakeJob(attempts: 3, shouldFail: true);
        $this->processJob($job, new WorkerOptions(maxTries: 3));

        $this->assertTrue($job->failed);
        $this->assertFalse($job->released);
    }

    public function test_it_fails_a_job_that_already_exceeded_max_attempts(): void
    {
        $job = new FakeJob(attempts: 5);
        $this->processJob($job, new WorkerOptions(maxTries: 3));

        $this->assertTrue($job->failed);
        $this->assertFalse($job->fired);
    }

    public function test_backoff_array_uses_attempt_index(): void
    {
        $job = new FakeJob(attempts: 2, shouldFail: true);
        $this->processJob($job, new WorkerOptions(maxTries: 5, backoff: [1, 5, 10]));

        $this->assertSame(5, $job->releaseDelay);
    }
}
