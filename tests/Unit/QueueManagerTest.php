<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Tests\Fixtures\FakeQueue;
use Arifnd\LiteQueue\Tests\Fixtures\TestJob;
use Arifnd\LiteQueue\Tests\TestCase;
use InvalidArgumentException;

final class QueueManagerTest extends TestCase
{
    private function manager(): QueueManager
    {
        return new QueueManager($this->app, [
            'default' => 'fake',
            'connections' => [
                'fake' => ['driver' => 'fake'],
                'other' => ['driver' => 'fake'],
            ],
        ]);
    }

    public function test_it_resolves_the_default_connection(): void
    {
        $manager = $this->manager();
        $manager->extend('fake', fn (array $config) => new FakeQueue($this->app, 'default'));

        $queue = $manager->connection();

        $this->assertInstanceOf(FakeQueue::class, $queue);
        $this->assertSame('fake', $queue->getConnectionName());
    }

    public function test_it_caches_resolved_connections(): void
    {
        $manager = $this->manager();
        $manager->extend('fake', fn (array $config) => new FakeQueue($this->app, 'default'));

        $this->assertSame($manager->connection('fake'), $manager->connection('fake'));
    }

    public function test_purge_forgets_connections(): void
    {
        $manager = $this->manager();
        $manager->extend('fake', fn (array $config) => new FakeQueue($this->app, 'default'));

        $first = $manager->connection('fake');
        $manager->purge('fake');

        $this->assertNotSame($first, $manager->connection('fake'));
    }

    public function test_it_pushes_through_the_resolved_connection(): void
    {
        $manager = $this->manager();
        $manager->extend('fake', fn (array $config) => new FakeQueue($this->app, 'default'));

        $manager->connection('fake')->push(new TestJob('hello'));

        $this->assertSame(1, $manager->connection('fake')->size());
    }

    public function test_unknown_connection_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager()->connection('missing');
    }
}
