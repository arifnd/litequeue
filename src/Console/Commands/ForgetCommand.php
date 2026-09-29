<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Illuminate\Console\Command;

class ForgetCommand extends Command
{
    protected $signature = 'forget {id : The ID of the failed job to forget}';

    protected $description = 'Remove a failed queue job';

    public function handle(): int
    {
        $forgotten = $this->laravel->make(FailedJobProvider::class)->forget($this->argument('id'));

        $this->info($forgotten ? 'Failed job forgotten.' : 'Failed job not found.');

        return $forgotten ? self::SUCCESS : self::FAILURE;
    }
}
