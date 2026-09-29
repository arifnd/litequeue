<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Contracts;

use DateInterval;
use DateTimeInterface;

interface PayloadFactory
{
    /**
     * Create a JSON payload string from the given job and data.
     */
    public function make(
        object|string $job,
        string $queue,
        ?string $connection = null,
        DateTimeInterface|DateInterval|int|null $delay = null,
    ): string;
}
