<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Arifnd\LiteQueue\Bus\Dispatchable;
use Arifnd\LiteQueue\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public string $value = 'queued') {}

    public function handle(): void
    {
        //
    }
}
