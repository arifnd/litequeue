<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Bus\Dispatcher;
use Arifnd\LiteQueue\Facades\Bus;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Tests\Fixtures\FakeQueue;
use Arifnd\LiteQueue\Tests\Fixtures\QueuedJob;
use Arifnd\LiteQueue\Tests\Fixtures\SyncCommand;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;

final class DispatcherTest extends TestCase
{
    /**
     * @return array{0: Dispatcher, 1: QueueManager}
     */
    private function boot(): array
    {
        $manager = new QueueManager($this->app, [
            'default' => 'fake',
            'connections' => ['fake' => ['driver' => 'fake']],
        ]);
        $manager->extend('fake', fn () => new FakeQueue($this->app, 'default'));

        $dispatcher = new Dispatcher($this->app);
        $dispatcher->setQueueResolver(fn ($connection = null) => $manager->connection($connection));

        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->app->instance(DispatcherContract::class, $dispatcher);

        return [$dispatcher, $manager];
    }

    protected function setUp(): void
    {
        parent::setUp();

        SyncCommand::$handled = 0;
    }

    public function test_non_queued_commands_run_inline(): void
    {
        $this->boot();

        $this->app->make(DispatcherContract::class)->dispatch(new SyncCommand);

        $this->assertSame(1, SyncCommand::$handled);
    }

    public function test_queued_commands_are_pushed_to_the_queue(): void
    {
        [$dispatcher, $manager] = $this->boot();

        $dispatcher->dispatch((new QueuedJob('a'))->onQueue('emails')->onConnection('fake'));

        $queue = $manager->connection('fake');
        $this->assertInstanceOf(FakeQueue::class, $queue);
        $this->assertCount(1, $queue->pushed);
        $this->assertSame(['emails'], $queue->queues);
    }

    public function test_delay_pushes_a_later_job(): void
    {
        [$dispatcher, $manager] = $this->boot();

        $dispatcher->dispatch((new QueuedJob('b'))->onConnection('fake')->delay(30));

        $payload = json_decode($manager->connection('fake')->pushed[0], true);
        $this->assertSame(30, $payload['delay']);
    }

    public function test_dispatchable_trait_defers_dispatch_until_destruction(): void
    {
        [, $manager] = $this->boot();

        QueuedJob::dispatch('c')->onConnection('fake')->onQueue('high');

        $queue = $manager->connection('fake');
        $this->assertInstanceOf(FakeQueue::class, $queue);
        $this->assertCount(1, $queue->pushed);
        $this->assertSame(['high'], $queue->queues);
    }

    public function test_dispatch_if_false_does_not_dispatch(): void
    {
        [, $manager] = $this->boot();

        QueuedJob::dispatchIf(false, 'd')->onConnection('fake');

        $this->assertCount(0, $manager->connection('fake')->pushed);
    }

    public function test_bus_facade_dispatches_sync(): void
    {
        $this->boot();

        Bus::dispatchSync(new SyncCommand);

        $this->assertSame(1, SyncCommand::$handled);
    }

    public function test_dispatch_helper_function(): void
    {
        $this->boot();

        dispatch(new SyncCommand);

        $this->assertSame(1, SyncCommand::$handled);
    }
}
