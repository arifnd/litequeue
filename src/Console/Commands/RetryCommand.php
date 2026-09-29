<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

class RetryCommand extends Command
{
    protected $signature = 'retry {id : The ID of the failed job, or "all"}';

    protected $description = 'Retry a failed queue job';

    public function handle(): int
    {
        $provider = $this->laravel->make(FailedJobProvider::class);
        $id = (string) $this->argument('id');

        $jobs = $id === 'all' ? $provider->all() : array_filter([$provider->find($id)]);

        if ($jobs === []) {
            $this->error('No failed jobs found.');

            return self::FAILURE;
        }

        foreach ($jobs as $job) {
            $this->laravel->make(QueueFactory::class)
                ->connection($job->connection)
                ->pushRaw($job->payload, $job->queue);

            $provider->forget($job->id);

            $this->info("Retried job [{$job->uuid}].");
        }

        return self::SUCCESS;
    }
}
