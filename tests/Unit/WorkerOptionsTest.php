<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Queue\WorkerOptions;
use PHPUnit\Framework\TestCase;

final class WorkerOptionsTest extends TestCase
{
    public function test_defaults(): void
    {
        $options = new WorkerOptions;

        $this->assertSame('default', $options->name);
        $this->assertSame(1, $options->maxTries);
        $this->assertSame(['default'], $options->queueNames());
    }

    public function test_queue_names_parses_comma_separated_strings(): void
    {
        $options = new WorkerOptions(queue: 'high, low ,');

        $this->assertSame(['high', 'low'], $options->queueNames());
    }

    public function test_queue_names_accepts_arrays(): void
    {
        $options = new WorkerOptions(queue: ['high', 'low']);

        $this->assertSame(['high', 'low'], $options->queueNames());
    }
}
