<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Illuminate\Console\Command;

class FailedCommand extends Command
{
    protected $signature = 'failed {--queue= : Only show failed jobs for the given queue}';

    protected $description = 'List all of the failed queue jobs';

    public function handle(): int
    {
        $jobs = $this->laravel->make(FailedJobProvider::class)->all();

        if ($jobs === []) {
            $this->info('No failed jobs found.');

            return self::SUCCESS;
        }

        $rows = array_map(fn ($job) => [
            $job->id ?? '',
            $job->uuid ?? '',
            $job->connection ?? '',
            $job->queue ?? '',
            $job->failed_at ?? '',
        ], $jobs);

        $this->table(['ID', 'UUID', 'Connection', 'Queue', 'Failed At'], $rows);

        return self::SUCCESS;
    }
}
