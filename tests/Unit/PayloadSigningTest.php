<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Exceptions\InvalidPayloadException;
use Arifnd\LiteQueue\Jobs\CallQueuedHandler;
use Arifnd\LiteQueue\Queue\PayloadFactory;
use Arifnd\LiteQueue\Tests\Fixtures\TestJob;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use PHPUnit\Framework\TestCase;

final class PayloadSigningTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $security
     */
    private function factory(array $security): PayloadFactory
    {
        $container = new Container;
        $container->instance('config', new Repository(['litequeue' => ['security' => $security]]));

        return new PayloadFactory($container);
    }

    public function test_it_does_not_sign_payloads_by_default(): void
    {
        $factory = $this->factory([]);
        $payload = $factory->makeArray(new TestJob('hello'), 'default');

        $this->assertFalse($factory->signsPayloads());
        $this->assertArrayNotHasKey('signature', $payload['data']);

        $factory->verifyCommand($payload['data']);
        $this->addToAssertionCount(1);
    }

    public function test_it_signs_payloads_when_enabled(): void
    {
        $factory = $this->factory(['sign_payloads' => true, 'signing_key' => 'secret']);

        $payload = $factory->makeArray(new TestJob('hello'), 'default');

        $this->assertTrue($factory->signsPayloads());
        $this->assertArrayHasKey('signature', $payload['data']);
        $this->assertSame($factory->sign($payload['data']['command']), $payload['data']['signature']);

        $factory->verifyCommand($payload['data']);
        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_a_tampered_command(): void
    {
        $factory = $this->factory(['sign_payloads' => true, 'signing_key' => 'secret']);

        $data = $factory->makeArray(new TestJob('hello'), 'default')['data'];
        $data['command'] = 'O:8:"EvilJob":0:{}';

        $this->expectException(InvalidPayloadException::class);

        $factory->verifyCommand($data);
    }

    public function test_it_rejects_a_missing_signature(): void
    {
        $factory = $this->factory(['sign_payloads' => true, 'signing_key' => 'secret']);

        $this->expectException(InvalidPayloadException::class);

        $factory->verifyCommand(['command' => 'O:8:"EvilJob":0:{}']);
    }

    public function test_it_requires_a_signing_key_when_enabled(): void
    {
        $this->expectException(InvalidPayloadException::class);

        $this->factory(['sign_payloads' => true, 'signing_key' => '']);
    }

    public function test_the_handler_rejects_a_tampered_payload(): void
    {
        $container = new Container;
        $container->instance('config', new Repository([
            'litequeue' => ['security' => ['sign_payloads' => true, 'signing_key' => 'secret']],
        ]));
        $container->instance(PayloadFactory::class, new PayloadFactory($container));

        $handler = new CallQueuedHandler($this->createStub(Dispatcher::class), $container);

        $this->expectException(InvalidPayloadException::class);

        $handler->call($this->createStub(Job::class), ['command' => 'O:8:"EvilJob":0:{}']);
    }
}
