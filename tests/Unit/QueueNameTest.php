<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Queue\QueueName;
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
}
