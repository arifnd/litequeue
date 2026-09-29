<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Supervisor\ArraySupervisorStore;
use Arifnd\LiteQueue\Supervisor\HorizonStateRepository;
use Arifnd\LiteQueue\Supervisor\SupervisorOptions;
use Arifnd\LiteQueue\Supervisor\SupervisorState;
use PHPUnit\Framework\TestCase;

final class HorizonStateRepositoryTest extends TestCase
{
    private function state(): SupervisorState
    {
        return new SupervisorState(
            name: 'host-abcd:litequeue',
            master: 'host-abcd',
            pid: 123,
            environment: 'production',
            status: 'running',
            processes: ['redis:default' => 1],
            options: new SupervisorOptions(name: 'litequeue', connection: 'redis'),
        );
    }

    public function test_update_writes_horizon_compatible_records(): void
    {
        $store = new ArraySupervisorStore;
        $repository = new HorizonStateRepository($store, 30, 15);

        $repository->update($this->state(), 1000);

        $supervisor = $store->hash('supervisor:host-abcd:litequeue');
        $this->assertNotNull($supervisor);
        $this->assertSame('host-abcd:litequeue', $supervisor['name']);
        $this->assertSame('host-abcd', $supervisor['master']);
        $this->assertSame('123', $supervisor['pid']);
        $this->assertSame('running', $supervisor['status']);
        $this->assertSame(['redis:default' => 1], json_decode($supervisor['processes'], true));

        $options = json_decode($supervisor['options'], true);
        $this->assertSame('redis', $options['connection']);
        $this->assertSame(60, $options['timeout']);
        $this->assertArrayHasKey('autoScalingStrategy', $options);

        $this->assertSame(['host-abcd:litequeue' => 1000], $store->set('supervisors'));
        $this->assertSame(30, $store->expires['supervisor:host-abcd:litequeue']);

        $master = $store->hash('master:host-abcd');
        $this->assertNotNull($master);
        $this->assertSame('production', $master['environment']);
        $this->assertSame(['host-abcd:litequeue'], json_decode($master['supervisors'], true));
        $this->assertSame(15, $store->expires['master:host-abcd']);
    }

    public function test_forget_removes_records(): void
    {
        $store = new ArraySupervisorStore;
        $repository = new HorizonStateRepository($store);

        $state = $this->state();
        $repository->update($state);
        $repository->forget($state);

        $this->assertNull($store->hash('supervisor:host-abcd:litequeue'));
        $this->assertNull($store->hash('master:host-abcd'));
        $this->assertSame([], $store->set('supervisors'));
        $this->assertSame([], $store->set('masters'));
    }

    public function test_options_expose_horizon_keys(): void
    {
        $options = new SupervisorOptions(name: 'litequeue', connection: 'redis', queue: ['default', 'high']);

        $array = $options->toArray();

        foreach (['balance', 'connection', 'queue', 'maxProcesses', 'timeout', 'autoScalingStrategy'] as $key) {
            $this->assertArrayHasKey($key, $array);
        }

        $this->assertSame('default,high', $options->queueString());
    }
}
