<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Arifnd\LiteQueue\Queue\Queue;

class FakeQueue extends Queue
{
    /**
     * @var array<int, string>
     */
    public array $pushed = [];

    /**
     * @var array<int, string|null>
     */
    public array $queues = [];

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->pushed[] = $payload;
        $this->queues[] = $queue;

        return json_decode($payload, true)['id'] ?? null;
    }

    public function size($queue = null)
    {
        return count($this->pushed);
    }

    public function pop($queue = null)
    {
        return null;
    }
}
