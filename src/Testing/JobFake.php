<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Testing;

use Illuminate\Contracts\Queue\Job as JobContract;

class JobFake implements JobContract
{
    public bool $deleted = false;

    public bool $released = false;

    public bool $failed = false;

    public function __construct(public string $uuid = 'fake-uuid', public int $attempts = 1) {}

    public function uuid(): ?string
    {
        return $this->uuid;
    }

    public function getJobId(): string
    {
        return '1';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['uuid' => $this->uuid];
    }

    public function fire(): void
    {
        //
    }

    public function release($delay = 0): void
    {
        $this->released = true;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function isDeletedOrReleased(): bool
    {
        return $this->deleted || $this->released;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function hasFailed(): bool
    {
        return $this->failed;
    }

    public function markAsFailed(): void
    {
        $this->failed = true;
    }

    public function fail($e = null): void
    {
        $this->failed = true;
    }

    public function maxTries(): ?int
    {
        return null;
    }

    public function maxExceptions(): ?int
    {
        return null;
    }

    public function timeout(): ?int
    {
        return null;
    }

    public function retryUntil(): ?int
    {
        return null;
    }

    public function getName(): string
    {
        return 'JobFake';
    }

    public function resolveName(): string
    {
        return 'JobFake';
    }

    public function resolveQueuedJobClass(): string
    {
        return self::class;
    }

    public function getConnectionName(): ?string
    {
        return 'fake';
    }

    public function getQueue(): ?string
    {
        return 'default';
    }

    public function getRawBody(): string
    {
        return '{}';
    }
}
