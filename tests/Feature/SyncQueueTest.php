<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Connections\NullConnector;
use Arifnd\LiteQueue\Connections\SyncConnector;
use Arifnd\LiteQueue\Tests\Fixtures\FailingJob;
use Arifnd\LiteQueue\Tests\Fixtures\RecordingJob;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class SyncQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingJob::$handled = [];
        FailingJob::$attempts = 0;
    }

    private function syncQueue()
    {
        return (new SyncConnector($this->app))
            ->connect(['driver' => 'sync', 'queue' => 'default'])
            ->setConnectionName('sync');
    }

    public function test_it_executes_jobs_inline(): void
    {
        $this->syncQueue()->push(new RecordingJob('a'));

        $this->assertSame(['a'], RecordingJob::$handled);
    }

    public function test_it_raises_processing_and_processed_events(): void
    {
        Event::fake([JobProcessing::class, JobProcessed::class]);

        $this->syncQueue()->push(new RecordingJob('b'));

        Event::assertDispatched(JobProcessing::class);
        Event::assertDispatched(JobProcessed::class);
        $this->assertSame(['b'], RecordingJob::$handled);
    }

    public function test_it_rethrows_job_exceptions(): void
    {
        $this->expectException(RuntimeException::class);

        $this->syncQueue()->push(new FailingJob);
    }

    public function test_null_driver_drops_jobs(): void
    {
        $queue = (new NullConnector($this->app))
            ->connect(['driver' => 'null', 'queue' => 'default'])
            ->setConnectionName('null');

        $queue->push(new RecordingJob('c'));

        $this->assertSame([], RecordingJob::$handled);
        $this->assertSame(0, $queue->size());
        $this->assertNull($queue->pop());
    }
}
