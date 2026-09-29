<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Connections\RedisConnector;
use Arifnd\LiteQueue\Jobs\RedisJob;
use Arifnd\LiteQueue\Queue\RedisQueue;
use Arifnd\LiteQueue\Tests\Fixtures\TestJob;
use Arifnd\LiteQueue\Tests\TestCase;
use Throwable;

final class RedisQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->app['redis']->connection()->ping();
        } catch (Throwable $e) {
            $this->markTestSkipped('Redis is not available: '.$e->getMessage());
        }

        $this->app['redis']->connection()->flushdb();
    }

    private function queue(array $overrides = []): RedisQueue
    {
        $config = array_merge([
            'driver' => 'litequeue_redis',
            'queue' => 'default',
            'connection' => null,
            'retry_after' => 90,
            'migration_batch_size' => -1,
        ], $overrides);

        /** @var RedisQueue $queue */
        $queue = (new RedisConnector($this->app, $this->app['redis']))->connect($config);

        return $queue->setConnectionName('redis');
    }

    public function test_push_places_the_job_on_the_expected_keys(): void
    {
        $this->queue()->push(new TestJob('redis'));

        $connection = $this->app['redis']->connection();

        $this->assertSame(1, $connection->llen('queues:default'));
        $this->assertSame(1, $connection->llen('queues:default:notify'));
        $this->assertSame(1, $this->queue()->size());
    }

    public function test_pop_reserves_the_job(): void
    {
        $this->queue()->push(new TestJob('redis'));

        $job = $this->queue()->pop();

        $this->assertInstanceOf(RedisJob::class, $job);
        $this->assertSame(1, $job->attempts());
        $this->assertSame(0, $this->queue()->pendingSize());
        $this->assertSame(1, $this->queue()->reservedSize());
    }

    public function test_delayed_jobs_are_not_available_immediately(): void
    {
        $queue = $this->queue();
        $queue->later(60, new TestJob('later'));

        $this->assertSame(1, $queue->delayedSize());
        $this->assertNull($queue->pop());
    }

    public function test_released_jobs_become_available_again(): void
    {
        $queue = $this->queue();
        $queue->push(new TestJob('release'));

        $job = $queue->pop();
        $this->assertNotNull($job);
        $job->release(0);

        $second = $queue->pop();

        $this->assertInstanceOf(RedisJob::class, $second);
        $this->assertSame(2, $second->attempts());
    }

    public function test_payload_contains_redis_metadata(): void
    {
        $queue = $this->queue();
        $queue->push(new TestJob('meta'));

        $raw = $this->app['redis']->connection()->lindex('queues:default', 0);
        $payload = json_decode($raw, true);

        $this->assertArrayHasKey('id', $payload);
        $this->assertSame(0, $payload['attempts']);
        $this->assertSame(0, $payload['delay']);
    }
}
