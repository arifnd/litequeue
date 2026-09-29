<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Jobs;

use Illuminate\Contracts\Queue\Job;

trait InteractsWithQueue
{
    public ?Job $job = null;

    public function attempts(): int
    {
        return $this->job ? $this->job->attempts() : 1;
    }

    public function delete(): void
    {
        $this->job?->delete();
    }

    public function release($delay = 0): void
    {
        $this->job?->release($delay);
    }

    public function setJob(Job $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function fail($exception = null): void
    {
        $this->job?->fail($exception);
    }
}
