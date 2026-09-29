<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

class RecordingJob
{
    /**
     * @var array<int, string>
     */
    public static array $handled = [];

    public function __construct(public string $value = 'x') {}

    public function handle(): void
    {
        self::$handled[] = $this->value;
    }
}
