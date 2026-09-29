<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

use Arifnd\LiteQueue\Supervisor\Contracts\SupervisorStore;

/**
 * In-memory supervisor store, useful for tests and dry runs.
 */
class ArraySupervisorStore implements SupervisorStore
{
    /**
     * @var array<string, array<string, string>>
     */
    public array $hashes = [];

    /**
     * @var array<string, array<string, int>>
     */
    public array $sets = [];

    /**
     * @var array<string, int>
     */
    public array $expires = [];

    public function put(string $key, array $fields): void
    {
        $this->hashes[$key] = $fields;
    }

    public function addToSet(string $key, int $score, string $member): void
    {
        $this->sets[$key][$member] = $score;
    }

    public function expire(string $key, int $seconds): void
    {
        $this->expires[$key] = $seconds;
    }

    public function remove(string $key): void
    {
        unset($this->hashes[$key], $this->expires[$key]);
    }

    public function removeFromSet(string $key, array $members): void
    {
        foreach ($members as $member) {
            unset($this->sets[$key][$member]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function hash(string $key): ?array
    {
        return $this->hashes[$key] ?? null;
    }

    /**
     * @return array<string, int>
     */
    public function set(string $key): array
    {
        return $this->sets[$key] ?? [];
    }
}
