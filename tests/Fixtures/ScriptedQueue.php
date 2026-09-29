<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Arifnd\LiteQueue\Queue\Queue;
use Illuminate\Contracts\Queue\Job as JobContract;

class ScriptedQueue extends Queue
{
    /**
     * @var array<int, JobContract>
     */
    public array $jobs = [];

    public function enqueue(JobContract $job): void
    {
        $this->jobs[] = $job;
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return null;
    }

    public function size($queue = null)
    {
        return count($this->jobs);
    }

    public function pop($queue = null, $index = 0): ?JobContract
    {
        return array_shift($this->jobs);
    }
}
