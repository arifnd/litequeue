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
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobPopped;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;

final class EventSequenceTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $recorded = [];

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

        Event::listen('*', function (string $name): void {
            if (str_starts_with($name, 'Illuminate\\Queue\\Events\\')) {
                $this->recorded[] = class_basename($name);
            }
        });
    }

    private function process(FakeJob $job): void
    {
        $this->queue->enqueue($job);

        (new Worker($this->manager, $this->app['events']))
            ->runNextJob('scripted', 'default', new WorkerOptions(maxTries: 3, backoff: 0));
    }

    public function test_success_event_sequence(): void
    {
        $this->process(new FakeJob);

        $this->assertSame(
            ['JobPopping', 'JobPopped', 'JobProcessing', 'JobProcessed', class_basename(JobAttempted::class)],
            $this->recorded
        );
    }

    public function test_failure_event_sequence(): void
    {
        $this->process(new FakeJob(attempts: 1, shouldFail: true));

        $this->assertSame(
            [
                class_basename(JobPopping::class),
                class_basename(JobPopped::class),
                class_basename(JobProcessing::class),
                class_basename(JobExceptionOccurred::class),
                class_basename(JobReleasedAfterException::class),
                class_basename(JobAttempted::class),
            ],
            $this->recorded
        );
    }
}
