<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Queue\PayloadFactory;
use Arifnd\LiteQueue\Tests\Fixtures\TestJob;
use Arifnd\LiteQueue\Tests\TestCase;

final class PayloadFactoryTest extends TestCase
{
    private PayloadFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new PayloadFactory($this->app);
    }

    protected function tearDown(): void
    {
        PayloadFactory::createPayloadUsing(null);

        parent::tearDown();
    }

    public function test_object_payload_has_the_expected_shape(): void
    {
        $payload = json_decode($this->factory->make(new TestJob('hello'), 'emails'), true);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $payload['uuid']);
        $this->assertSame(TestJob::class, $payload['displayName']);
        $this->assertSame('Arifnd\\LiteQueue\\Jobs\\CallQueuedHandler@call', $payload['job']);
        $this->assertSame(TestJob::class, $payload['data']['commandName']);
        $this->assertNull($payload['delay']);
        $this->assertIsInt($payload['createdAt']);
    }

    public function test_command_round_trips(): void
    {
        $payload = json_decode($this->factory->make(new TestJob('round-trip'), 'emails'), true);
        $job = unserialize($payload['data']['command']);

        $this->assertInstanceOf(TestJob::class, $job);
        $this->assertSame('round-trip', $job->message);
    }

    public function test_delay_is_recorded_in_seconds(): void
    {
        $payload = json_decode($this->factory->make(new TestJob, 'emails', null, 90), true);

        $this->assertSame(90, $payload['delay']);
    }

    public function test_string_payload(): void
    {
        $payload = json_decode($this->factory->make('App\\Jobs\\Ping@handle', 'emails', null, null), true);

        $this->assertSame('App\\Jobs\\Ping', $payload['displayName']);
        $this->assertSame('App\\Jobs\\Ping@handle', $payload['job']);
    }

    public function test_create_payload_hook_can_mutate_payload(): void
    {
        PayloadFactory::createPayloadUsing(fn (string $queue, array $payload) => array_merge($payload, [
            'tenant' => 'acme',
        ]));

        $payload = json_decode($this->factory->make(new TestJob, 'emails'), true);

        $this->assertSame('acme', $payload['tenant']);
    }
}
