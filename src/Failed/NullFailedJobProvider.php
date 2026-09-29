<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Failed;

use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Throwable;

class NullFailedJobProvider implements FailedJobProvider
{
    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int|null
    {
        return null;
    }

    public function all(): array
    {
        return [];
    }

    public function find(mixed $id): ?object
    {
        return null;
    }

    public function forget(mixed $id): bool
    {
        return false;
    }

    public function flush(?int $hours = null): void
    {
        //
    }

    public function count(): int
    {
        return 0;
    }
}
