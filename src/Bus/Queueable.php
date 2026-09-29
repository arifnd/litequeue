<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Bus;

use Illuminate\Support\Collection;

trait Queueable
{
    public ?string $connection = null;

    public ?string $queue = null;

    public mixed $delay = null;

    public ?bool $afterCommit = null;

    public mixed $chain = null;

    public mixed $middleware = null;

    public int|array|null $backoff = null;

    public function onConnection($connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function onQueue($queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function allOnConnection($connection): static
    {
        $this->connection = $connection;
        $this->chain = Collection::wrap($this->chain)->each->onConnection($connection);

        return $this;
    }

    public function allOnQueue($queue): static
    {
        $this->queue = $queue;
        $this->chain = Collection::wrap($this->chain)->each->onQueue($queue);

        return $this;
    }

    public function delay($delay): static
    {
        $this->delay = $delay;

        return $this;
    }

    public function withoutDelay(): static
    {
        $this->delay = null;

        return $this;
    }

    public function afterCommit(): static
    {
        $this->afterCommit = true;

        return $this;
    }

    public function beforeCommit(): static
    {
        $this->afterCommit = false;

        return $this;
    }

    public function through($middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    public function chain($chain): static
    {
        $this->chain = Collection::wrap($chain);

        return $this;
    }

    public function backoff($backoff): static
    {
        $this->backoff = $backoff;

        return $this;
    }
}
