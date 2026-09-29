<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Arifnd\LiteQueue\Bus\Dispatchable;
use Arifnd\LiteQueue\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class UniqueJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public string $key = 'default') {}

    public function uniqueId(): string
    {
        return $this->key;
    }

    public function handle(): void
    {
        //
    }
}
