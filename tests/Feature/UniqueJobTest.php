<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Tests\Fixtures\FakeQueue;
use Arifnd\LiteQueue\Tests\Fixtures\UniqueJob;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Cache\Repository as Cache;

final class UniqueJobTest extends TestCase
{
    private FakeQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Cache::class, new CacheRepository(new ArrayStore));

        $this->queue = new FakeQueue($this->app, 'default');

        $manager = new QueueManager($this->app, [
            'default' => 'fake',
            'connections' => ['fake' => ['driver' => 'fake']],
        ]);
        $manager->extend('fake', fn () => $this->queue);

        $dispatcher = new Dispatcher($this->app);
        $dispatcher->setQueueResolver(fn ($connection = null) => $manager->connection($connection));

        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->app->instance(DispatcherContract::class, $dispatcher);
    }

    public function test_it_does_not_dispatch_the_same_unique_job_twice(): void
    {
        UniqueJob::dispatch('same')->onConnection('fake');
        UniqueJob::dispatch('same')->onConnection('fake');

        $this->assertCount(1, $this->queue->pushed);
    }

    public function test_it_dispatches_unique_jobs_with_different_ids(): void
    {
        UniqueJob::dispatch('one')->onConnection('fake');
        UniqueJob::dispatch('two')->onConnection('fake');

        $this->assertCount(2, $this->queue->pushed);
    }
}
