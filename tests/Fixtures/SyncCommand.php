<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

class SyncCommand
{
    public static int $handled = 0;

    public function __construct(public string $value = 'sync') {}

    public function handle(): void
    {
        self::$handled++;
    }
}
