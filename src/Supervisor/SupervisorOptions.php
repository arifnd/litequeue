<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

use Arifnd\LiteQueue\Queue\WorkerOptions;

/**
 * Horizon-shaped supervisor options.
 */
final class SupervisorOptions
{
    /**
     * @param  string|array<int, string>  $queue
     */
    public function __construct(
        public string $name = 'litequeue',
        public string $connection = 'sync',
        public string|array $queue = 'default',
        public string $workersName = 'default',
        public string $balance = 'off',
        public int $backoff = 0,
        public int $maxTime = 0,
        public int $maxJobs = 0,
        public int $maxProcesses = 1,
        public int $minProcesses = 1,
        public int $memory = 128,
        public int $timeout = 60,
        public int $sleep = 3,
        public int $maxTries = 1,
        public bool $force = false,
        public int $nice = 0,
        public int $balanceCooldown = 3,
        public int $balanceMaxShift = 1,
        public int $parentId = 0,
        public int $rest = 0,
        public string $autoScalingStrategy = 'time',
        public string $environment = 'production',
    ) {}

    public function queueString(): string
    {
        return is_array($this->queue) ? implode(',', $this->queue) : $this->queue;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'balance' => $this->balance,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'backoff' => $this->backoff,
            'force' => $this->force,
            'maxProcesses' => $this->maxProcesses,
            'minProcesses' => $this->minProcesses,
            'maxTries' => $this->maxTries,
            'maxTime' => $this->maxTime,
            'maxJobs' => $this->maxJobs,
            'memory' => $this->memory,
            'nice' => $this->nice,
            'name' => $this->name,
            'workersName' => $this->workersName,
            'sleep' => $this->sleep,
            'timeout' => $this->timeout,
            'balanceCooldown' => $this->balanceCooldown,
            'balanceMaxShift' => $this->balanceMaxShift,
            'parentId' => $this->parentId,
            'rest' => $this->rest,
            'autoScalingStrategy' => $this->autoScalingStrategy,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function toWorkerOptions(): WorkerOptions
    {
        return new WorkerOptions(
            name: $this->workersName,
            queue: $this->queue,
            backoff: $this->backoff,
            memory: $this->memory,
            timeout: $this->timeout,
            sleep: $this->sleep,
            maxTries: $this->maxTries,
            force: $this->force,
            maxJobs: $this->maxJobs,
            maxTime: $this->maxTime,
            rest: $this->rest,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            name: (string) ($config['name'] ?? 'litequeue'),
            connection: (string) ($config['connection'] ?? 'sync'),
            queue: $config['queue'] ?? 'default',
            workersName: (string) ($config['workers_name'] ?? 'default'),
            balance: (string) ($config['balance'] ?? 'off'),
            backoff: (int) ($config['backoff'] ?? 0),
            maxTime: (int) ($config['maxTime'] ?? 0),
            maxJobs: (int) ($config['maxJobs'] ?? 0),
            maxProcesses: (int) ($config['maxProcesses'] ?? 1),
            minProcesses: (int) ($config['minProcesses'] ?? 1),
            memory: (int) ($config['memory'] ?? 128),
            timeout: (int) ($config['timeout'] ?? 60),
            sleep: (int) ($config['sleep'] ?? 3),
            maxTries: (int) ($config['tries'] ?? 1),
            force: (bool) ($config['force'] ?? false),
            nice: (int) ($config['nice'] ?? 0),
            balanceCooldown: (int) ($config['balanceCooldown'] ?? 3),
            balanceMaxShift: (int) ($config['balanceMaxShift'] ?? 1),
            parentId: (int) ($config['parentId'] ?? 0),
            rest: (int) ($config['rest'] ?? 0),
            autoScalingStrategy: (string) ($config['autoScalingStrategy'] ?? 'time'),
            environment: (string) ($config['environment'] ?? 'production'),
        );
    }
}
