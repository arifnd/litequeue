<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Illuminate\Console\Command;

class FlushCommand extends Command
{
    protected $signature = 'flush {--hours= : Only flush jobs older than the given number of hours}';

    protected $description = 'Flush all of the failed queue jobs';

    public function handle(): int
    {
        $hours = $this->option('hours');

        $this->laravel->make(FailedJobProvider::class)->flush($hours === null ? null : (int) $hours);

        $this->info('Failed jobs flushed.');

        return self::SUCCESS;
    }
}
