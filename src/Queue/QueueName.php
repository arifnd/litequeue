<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Arifnd\LiteQueue\Exceptions\InvalidQueueNameException;
use Stringable;

final class QueueName implements Stringable
{
    public const MAX_LENGTH = 255;

    private function __construct(public readonly string $value) {}

    /**
     * Normalize a nullable queue name against a default.
     *
     * @throws InvalidQueueNameException
     */
    public static function parse(?string $queue, string $default = 'default'): self
    {
        $queue = is_string($queue) ? trim($queue) : '';

        if ($queue === '') {
            $queue = $default;
        }

        self::assertValid($queue);

        return new self($queue);
    }

    /**
     * @throws InvalidQueueNameException
     */
    public static function assertValid(string $queue): void
    {
        if ($queue === '' || trim($queue) === '') {
            throw new InvalidQueueNameException('A queue name cannot be empty.');
        }

        if (strlen($queue) > self::MAX_LENGTH) {
            throw new InvalidQueueNameException(
                sprintf('A queue name cannot be longer than %d characters.', self::MAX_LENGTH)
            );
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $queue) === 1) {
            throw new InvalidQueueNameException('A queue name cannot contain control characters.');
        }

        if (str_contains($queue, ':')) {
            throw new InvalidQueueNameException('A queue name cannot contain the reserved ":" separator.');
        }
    }

    public function isDefault(string $default = 'default'): bool
    {
        return $this->value === $default;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
