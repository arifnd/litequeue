<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

class NullQueue extends Queue
{
    public function push($job, $data = '', $queue = null)
    {
        return null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return null;
    }

    /**
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array<string, mixed>  $options
     * @return null
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return null;
    }

    public function size($queue = null)
    {
        return 0;
    }

    public function pop($queue = null)
    {
        return null;
    }
}
