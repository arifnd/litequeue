<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Arifnd\LiteQueue\Queue\WorkerOptions;
use Arifnd\LiteQueue\Worker\Worker;
use Illuminate\Console\Command;

class WorkCommand extends Command
{
    protected $signature = 'work
        {connection? : The queue connection to work}
        {--queue=default : The names of the queues to work}
        {--name=default : The name of the worker}
        {--once : Only process the next job on the queue}
        {--stop-when-empty : Stop when the queue is empty}
        {--delay=0 : The number of seconds to delay failed jobs}
        {--backoff=0 : The number of seconds to wait before retrying}
        {--max-jobs=0 : The number of jobs to process before stopping}
        {--max-time=0 : The maximum number of seconds the worker should run}
        {--force : Force the worker to run even in maintenance mode}
        {--memory=128 : The memory limit in megabytes}
        {--sleep=3 : Seconds to sleep when no job is available}
        {--tries=1 : Number of times to attempt a job before logging it failed}
        {--timeout=60 : The number of seconds a child process can run}
        {--rest=0 : Seconds to rest between jobs}';

    protected $description = 'Start processing jobs on the queue';

    public function handle(): int
    {
        $connectionName = $this->argument('connection')
            ?: $this->laravel['config']['default']
            ?? 'sync';

        $options = new WorkerOptions(
            name: (string) $this->option('name'),
            queue: (string) $this->option('queue'),
            delay: (int) $this->option('delay'),
            backoff: (int) $this->option('backoff'),
            memory: (int) $this->option('memory'),
            timeout: (int) $this->option('timeout'),
            sleep: (int) $this->option('sleep'),
            maxTries: (int) $this->option('tries'),
            force: (bool) $this->option('force'),
            stopWhenEmpty: (bool) $this->option('stop-when-empty'),
            maxJobs: (int) $this->option('max-jobs'),
            maxTime: (int) $this->option('max-time'),
            rest: (int) $this->option('rest'),
        );

        $this->info("Processing jobs on the [{$connectionName}] connection.");

        /** @var Worker $worker */
        $worker = $this->laravel->make(Worker::class);

        if ($this->option('once')) {
            $worker->runNextJob((string) $connectionName, (string) $options->queue, $options);

            return self::SUCCESS;
        }

        return $worker->daemon((string) $connectionName, (string) $options->queue, $options);
    }
}
