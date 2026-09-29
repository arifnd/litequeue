<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Testing\QueueFake;
use Arifnd\LiteQueue\Tests\Fixtures\QueuedJob;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class QueueFakeTest extends TestCase
{
    public function test_it_records_pushed_jobs(): void
    {
        $fake = new QueueFake;
        $fake->push(new QueuedJob('a'));
        $fake->pushOn('emails', new QueuedJob('b'));

        $fake->assertPushed(QueuedJob::class);
        $fake->assertPushedTimes(QueuedJob::class, 2);
        $fake->assertPushedOn('emails', QueuedJob::class);
    }

    public function test_it_supports_callbacks(): void
    {
        $fake = new QueueFake;
        $fake->push(new QueuedJob('payload'));

        $fake->assertPushed(QueuedJob::class, fn (QueuedJob $job) => $job->value === 'payload');
    }

    public function test_assert_not_pushed(): void
    {
        $fake = new QueueFake;
        $fake->assertNotPushed(QueuedJob::class);
    }

    public function test_assert_pushed_failure(): void
    {
        $this->expectException(AssertionFailedError::class);

        (new QueueFake)->assertPushed(QueuedJob::class);
    }
}
