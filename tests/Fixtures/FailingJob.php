<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use RuntimeException;

class FailingJob
{
    public static int $attempts = 0;

    public function handle(): void
    {
        self::$attempts++;

        throw new RuntimeException('nope');
    }
}
