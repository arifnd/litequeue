<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Exceptions\InvalidQueueNameException;
use Arifnd\LiteQueue\Queue\QueueName;
use Arifnd\LiteQueue\Queue\RedisQueue;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use PHPUnit\Framework\TestCase;

final class QueueNameTest extends TestCase
{
    public function test_it_uses_the_default_when_null(): void
    {
        $this->assertSame('default', QueueName::parse(null)->value);
        $this->assertSame('emails', QueueName::parse(null, 'emails')->value);
    }

    public function test_it_trims_and_falls_back_on_empty_strings(): void
    {
        $this->assertSame('default', QueueName::parse('   ')->value);
        $this->assertSame('high', QueueName::parse('  high ')->value);
    }

    public function test_it_casts_to_string(): void
    {
        $this->assertSame('high', (string) QueueName::parse('high'));
    }

    public function test_it_allows_common_queue_names(): void
    {
        foreach (['default', 'high', 'emails.outbound', 'my_queue-1', 'a/b'] as $name) {
            $this->assertSame($name, QueueName::parse($name)->value);
        }
    }

    public function test_it_rejects_the_reserved_separator(): void
    {
        $this->expectException(InvalidQueueNameException::class);

        QueueName::parse('queues:default');
    }

    public function test_it_rejects_control_characters(): void
    {
        $this->expectException(InvalidQueueNameException::class);

        QueueName::parse("bad\nname");
    }

    public function test_it_rejects_overly_long_names(): void
    {
        $this->expectException(InvalidQueueNameException::class);

        QueueName::parse(str_repeat('a', QueueName::MAX_LENGTH + 1));
    }

    public function test_redis_queue_key_building_validates_names(): void
    {
        $queue = new RedisQueue(
            $this->createStub(Container::class),
            $this->createStub(RedisFactory::class),
        );

        $this->assertSame('queues:default', $queue->getQueue(null));

        $this->expectException(InvalidQueueNameException::class);

        $queue->getQueue('bad:name');
    }
}
