<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Facades\LiteQueue;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Queue\SyncQueue;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

final class ServiceProviderTest extends TestCase
{
    public function test_it_merges_the_configuration(): void
    {
        $this->assertSame('sync', config('litequeue.default'));
        $this->assertArrayHasKey('redis', config('litequeue.connections'));
    }

    public function test_it_binds_the_queue_manager(): void
    {
        $this->assertInstanceOf(QueueManager::class, $this->app->make(QueueFactory::class));
    }

    public function test_it_binds_the_dispatcher(): void
    {
        $this->assertInstanceOf(Dispatcher::class, $this->app->make(Dispatcher::class));
    }

    public function test_the_facade_resolves_a_connection(): void
    {
        $this->assertInstanceOf(SyncQueue::class, LiteQueue::connection('sync'));
    }

    public function test_it_registers_the_litequeue_redis_driver(): void
    {
        $manager = $this->app->make(QueueManager::class);

        $this->assertNotNull($manager->getConnector('litequeue_redis'));
    }
}
