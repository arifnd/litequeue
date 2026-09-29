<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

/**
 * Mutable options container for a single worker invocation.
 */
final class WorkerOptions
{
    /**
     * @param  string|array<int, string>  $queue
     * @param  int|string|array<int, int|string>  $backoff
     */
    public function __construct(
        public string $name = 'default',
        public string|array $queue = 'default',
        public int $delay = 0,
        public int|string|array $backoff = 0,
        public int $memory = 128,
        public int $timeout = 60,
        public int $sleep = 3,
        public int $maxTries = 1,
        public int $maxExceptions = 0,
        public bool $force = false,
        public bool $stopWhenEmpty = false,
        public int $maxJobs = 0,
        public int $maxTime = 0,
        public int $rest = 0,
        public bool $failOnTimeout = false,
        public bool $throwOnTimeout = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function queueNames(): array
    {
        $queues = is_array($this->queue) ? $this->queue : explode(',', $this->queue);

        return array_values(array_filter(array_map('trim', $queues), fn ($q) => $q !== ''));
    }
}
