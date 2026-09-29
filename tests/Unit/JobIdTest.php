<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Queue\JobId;
use PHPUnit\Framework\TestCase;

final class JobIdTest extends TestCase
{
    public function test_generate_returns_a_uuid(): void
    {
        $uuid = JobId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function test_random_respects_length(): void
    {
        $this->assertSame(16, strlen(JobId::random(16)));
    }
}
