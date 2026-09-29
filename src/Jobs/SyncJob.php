<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Jobs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;

class SyncJob extends Job implements JobContract
{
    protected string $job;

    protected string $payload;

    public function __construct(Container $container, string $payload, ?string $connectionName, ?string $queue)
    {
        $this->payload = $payload;
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    public function release($delay = 0)
    {
        parent::release($delay);
    }

    public function attempts()
    {
        return 1;
    }

    public function getJobId()
    {
        return '';
    }

    public function getRawBody()
    {
        return $this->payload;
    }
}
