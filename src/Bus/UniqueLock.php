<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Bus;

use Illuminate\Contracts\Cache\Repository as Cache;

class UniqueLock
{
    public function __construct(protected Cache $cache) {}

    public function acquire(object $job): bool
    {
        $lock = $this->cache->lock($this->getKey($job));

        if (! $lock->get()) {
            return false;
        }

        $this->cache->put(
            $this->getKey($job),
            $this->uniqueId($job),
            $this->uniqueFor($job)
        );

        return true;
    }

    public function release(object $job): void
    {
        $this->cache->forget($this->getKey($job));
        $this->cache->lock($this->getKey($job))->release();
    }

    public function getKey(object $job): string
    {
        return 'laravel_unique_job:'.get_class($job).$this->uniqueId($job);
    }

    protected function uniqueId(object $job): string
    {
        return method_exists($job, 'uniqueId') ? (string) $job->uniqueId() : '';
    }

    protected function uniqueFor(object $job): ?int
    {
        return method_exists($job, 'uniqueFor') ? $job->uniqueFor() : null;
    }
}
