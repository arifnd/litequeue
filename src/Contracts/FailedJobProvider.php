<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Contracts;

use Throwable;

interface FailedJobProvider
{
    /**
     * Log a failed job into storage.
     */
    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int|null;

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array<int, object>
     */
    public function all(): array;

    /**
     * Get a single failed job.
     */
    public function find(mixed $id): ?object;

    /**
     * Delete a single failed job from storage.
     */
    public function forget(mixed $id): bool;

    /**
     * Delete all of the failed jobs from storage.
     */
    public function flush(?int $hours = null): void;

    /**
     * Count the failed jobs.
     */
    public function count(): int;
}
