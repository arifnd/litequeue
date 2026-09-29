<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Illuminate\Contracts\Queue\Job as JobContract;
use RuntimeException;

class FakeJob implements JobContract
{
    public bool $fired = false;

    public bool $deleted = false;

    public bool $released = false;

    public bool $failed = false;

    public ?int $releaseDelay = null;

    public function __construct(
        public int $attempts = 1,
        public ?int $maxTries = null,
        public bool $shouldFail = false,
        public string $uuid = 'test-uuid',
    ) {}

    public function fire(): void
    {
        $this->fired = true;

        if ($this->shouldFail) {
            throw new RuntimeException('job failed');
        }
    }

    public function uuid(): ?string
    {
        return $this->uuid;
    }

    public function getJobId(): string
    {
        return '1';
    }

    public function payload(): array
    {
        return ['uuid' => $this->uuid];
    }

    public function delete(): void
    {
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function release($delay = 0): void
    {
        $this->released = true;
        $this->releaseDelay = (int) $delay;
    }

    public function isReleased(): bool
    {
        return $this->released;
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
        $this->deleted = true;
    }

    public function maxTries(): ?int
    {
        return $this->maxTries;
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
        return 'FakeJob';
    }

    public function resolveName(): string
    {
        return 'FakeJob';
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
        return json_encode(['uuid' => $this->uuid], JSON_THROW_ON_ERROR);
    }
}
