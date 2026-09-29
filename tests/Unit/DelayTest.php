<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Queue\Delay;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DelayTest extends TestCase
{
    public function test_null_is_immediately_available(): void
    {
        $this->assertSame(0, Delay::seconds(null));
        $this->assertTrue(Delay::from(null)->isReady());
    }

    public function test_integer_seconds_are_relative_to_now(): void
    {
        $delay = Delay::from(60);

        $this->assertSame(60, $delay->secondsRemaining());
        $this->assertFalse($delay->isReady());
    }

    public function test_datetime_is_absolute(): void
    {
        $when = (new DateTimeImmutable)->modify('+120 seconds');

        $this->assertSame($when->getTimestamp(), Delay::from($when)->availableAt);
    }

    public function test_date_interval_is_supported(): void
    {
        $this->assertSame(60, Delay::seconds(new DateInterval('PT1M')));
    }

    public function test_negative_delay_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Delay::from(-1);
    }
}
