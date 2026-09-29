<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class Delay
{
    private function __construct(public readonly int $availableAt) {}

    /**
     * Normalize any supported delay into an absolute "available at" timestamp.
     */
    public static function from(DateTimeInterface|DateInterval|int|null $delay): self
    {
        if ($delay === null) {
            return new self(time());
        }

        if ($delay instanceof DateTimeInterface) {
            return new self($delay->getTimestamp());
        }

        if ($delay instanceof DateInterval) {
            return new self((new DateTimeImmutable)->add($delay)->getTimestamp());
        }

        if ($delay < 0) {
            throw new InvalidArgumentException('Delay cannot be negative.');
        }

        return new self(time() + $delay);
    }

    /**
     * Normalize any supported delay into a number of seconds from now.
     */
    public static function seconds(DateTimeInterface|DateInterval|int|null $delay): int
    {
        if ($delay === null) {
            return 0;
        }

        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }

        if ($delay instanceof DateInterval) {
            return max(0, self::from($delay)->availableAt - time());
        }

        return max(0, $delay);
    }

    public function secondsRemaining(): int
    {
        return max(0, $this->availableAt - time());
    }

    public function isReady(): bool
    {
        return $this->availableAt <= time();
    }
}
