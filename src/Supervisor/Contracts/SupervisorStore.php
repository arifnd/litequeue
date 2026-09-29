<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor\Contracts;

interface SupervisorStore
{
    /**
     * @param  array<string, string>  $fields
     */
    public function put(string $key, array $fields): void;

    public function addToSet(string $key, int $score, string $member): void;

    public function expire(string $key, int $seconds): void;

    public function remove(string $key): void;

    /**
     * @param  array<int, string>  $members
     */
    public function removeFromSet(string $key, array $members): void;
}
