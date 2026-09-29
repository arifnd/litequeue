<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

use Arifnd\LiteQueue\Supervisor\Contracts\SupervisorStore;
use Illuminate\Redis\Connections\Connection;

/**
 * Writes Horizon-compatible supervisor state to the shared Redis prefix.
 */
class RedisSupervisorStore implements SupervisorStore
{
    public function __construct(protected Connection $connection) {}

    public function put(string $key, array $fields): void
    {
        $this->connection->hmset($key, $fields);
    }

    public function addToSet(string $key, int $score, string $member): void
    {
        $this->connection->zadd($key, $score, $member);
    }

    public function expire(string $key, int $seconds): void
    {
        $this->connection->expire($key, $seconds);
    }

    public function remove(string $key): void
    {
        $this->connection->del($key);
    }

    public function removeFromSet(string $key, array $members): void
    {
        if ($members === []) {
            return;
        }

        $this->connection->zrem($key, ...$members);
    }
}
