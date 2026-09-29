<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Queue\SyncQueue;
use Arifnd\LiteQueue\Tests\Fixtures\FakeQueue;
use Arifnd\LiteQueue\Tests\Fixtures\MiddlewareJob;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;

final class JobExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MiddlewareJob::$log = [];

        $manager = new QueueManager($this->app, [
            'default' => 'fake',
            'connections' => ['fake' => ['driver' => 'fake']],
        ]);
        $manager->extend('fake', fn () => new FakeQueue($this->app, 'default'));

        $dispatcher = new Dispatcher($this->app);
        $dispatcher->setQueueResolver(fn ($connection = null) => $manager->connection($connection));

        $this->app->instance(DispatcherContract::class, $dispatcher);
    }

    private function syncQueue(): SyncQueue
    {
        return (new SyncQueue($this->app, 'default'))->setConnectionName('sync');
    }

    public function test_job_middleware_wraps_the_handler(): void
    {
        $this->syncQueue()->push(new MiddlewareJob('m'));

        $this->assertSame(['before', 'handle:1', 'after'], MiddlewareJob::$log);
    }

    public function test_interacts_with_queue_reports_attempts(): void
    {
        $this->syncQueue()->push(new MiddlewareJob('m'));

        $this->assertSame('handle:1', MiddlewareJob::$log[1]);
    }
}
