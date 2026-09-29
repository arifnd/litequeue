<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Stringable;

final class QueueName implements Stringable
{
    private function __construct(public readonly string $value) {}

    /**
     * Normalize a nullable queue name against a default.
     */
    public static function parse(?string $queue, string $default = 'default'): self
    {
        $queue = is_string($queue) ? trim($queue) : '';

        return new self($queue !== '' ? $queue : $default);
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
