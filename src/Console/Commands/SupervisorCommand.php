<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Arifnd\LiteQueue\Console\ExceptionHandler;
use Arifnd\LiteQueue\Supervisor\ArraySupervisorStore;
use Arifnd\LiteQueue\Supervisor\Contracts\SupervisorStore;
use Arifnd\LiteQueue\Supervisor\HorizonStateRepository;
use Arifnd\LiteQueue\Supervisor\RedisSupervisorStore;
use Arifnd\LiteQueue\Supervisor\Supervisor;
use Arifnd\LiteQueue\Supervisor\SupervisorName;
use Arifnd\LiteQueue\Supervisor\SupervisorOptions;
use Arifnd\LiteQueue\Worker\Worker;
use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class SupervisorCommand extends Command
{
    protected $signature = 'supervisor
        {connection? : The queue connection to work}
        {--queue= : The queues to work (comma separated)}
        {--name= : The supervisor name}
        {--environment= : The application environment}
        {--max-jobs= : Number of jobs to process before stopping}
        {--max-time= : Maximum number of seconds to run}
        {--tries= : Number of times to attempt a job}
        {--timeout= : Job timeout in seconds}
        {--sleep= : Seconds to sleep when no job is available}
        {--memory= : Memory limit in megabytes}
        {--backoff= : Retry backoff seconds}
        {--rest= : Seconds to rest between jobs}
        {--dry-run : Use an in-memory store instead of Redis}';

    protected $description = 'Run as a Horizon-compatible supervisor';

    public function handle(): int
    {
        if ($this->laravel->bound(ExceptionHandler::class)) {
            $this->laravel->make(ExceptionHandler::class)->setOutput($this->output);
        }

        $config = $this->laravel['config']['supervisor'] ?? [];

        $options = SupervisorOptions::fromConfig($this->mergeOptions($config));

        $store = $this->option('dry-run')
            ? new ArraySupervisorStore
            : $this->redisStore($config);

        $repository = new HorizonStateRepository(
            $store,
            (int) ($config['horizon']['ttl'] ?? 30),
            (int) ($config['horizon']['master_ttl'] ?? 15),
        );

        $supervisor = new Supervisor(
            $this->laravel->make(Worker::class),
            $repository,
            $options,
            SupervisorName::master(),
        );

        $this->info("Supervisor [{$supervisor->state()->name}] running.");

        return $supervisor->run();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function mergeOptions(array $config): array
    {
        $overrides = array_filter([
            'connection' => $this->argument('connection'),
            'queue' => $this->option('queue'),
            'name' => $this->option('name'),
            'environment' => $this->option('environment'),
            'maxJobs' => $this->option('max-jobs'),
            'maxTime' => $this->option('max-time'),
            'tries' => $this->option('tries'),
            'timeout' => $this->option('timeout'),
            'sleep' => $this->option('sleep'),
            'memory' => $this->option('memory'),
            'backoff' => $this->option('backoff'),
            'rest' => $this->option('rest'),
        ], fn ($value) => $value !== null && $value !== '');

        if (isset($overrides['queue']) && is_string($overrides['queue'])) {
            $overrides['queue'] = explode(',', $overrides['queue']);
        }

        return array_merge($config, $overrides);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function redisStore(array $config): SupervisorStore
    {
        if (! $this->laravel->bound(RedisFactory::class)) {
            return new ArraySupervisorStore;
        }

        $connection = $this->laravel->make(RedisFactory::class)
            ->connection($config['horizon']['redis_connection'] ?? 'horizon');

        return new RedisSupervisorStore($connection);
    }
}
