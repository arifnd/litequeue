<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

use Arifnd\LiteQueue\Worker\Worker;

class Supervisor
{
    protected bool $shouldQuit = false;

    protected string $status = 'running';

    public function __construct(
        protected Worker $worker,
        protected HorizonStateRepository $repository,
        protected SupervisorOptions $options,
        protected string $master,
        protected ?int $pid = null,
    ) {}

    public function state(): SupervisorState
    {
        return new SupervisorState(
            name: SupervisorName::supervisor($this->master, $this->options->name),
            master: $this->master,
            pid: $this->pid ?? (int) getmypid(),
            environment: $this->options->environment,
            status: $this->status,
            processes: [$this->options->connection.':'.$this->options->queueString() => 1],
            options: $this->options,
        );
    }

    public function run(): int
    {
        $this->listenForSignals();

        $state = $this->state();
        $this->repository->update($state);

        $startTime = time();
        $jobs = 0;

        while (! $this->shouldQuit) {
            $this->worker->runNextJob(
                $this->options->connection,
                $this->options->queueString(),
                $this->options->toWorkerOptions(),
            );

            $jobs++;
            $this->repository->update($state, time());

            if ($this->options->maxJobs > 0 && $jobs >= $this->options->maxJobs) {
                break;
            }

            if ($this->options->maxTime > 0 && (time() - $startTime) >= $this->options->maxTime) {
                break;
            }
        }

        $this->status = 'paused';
        $this->repository->forget($this->state());

        return 0;
    }

    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    protected function listenForSignals(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGINT, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGQUIT, fn () => $this->shouldQuit = true);
    }
}
