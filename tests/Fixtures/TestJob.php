<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Arifnd\LiteQueue\Queue\SerializesModels;

class TestJob
{
    use SerializesModels;

    public function __construct(
        public string $message = 'hello',
        public mixed $model = null,
    ) {}

    public function handle(): void
    {
        //
    }
}
