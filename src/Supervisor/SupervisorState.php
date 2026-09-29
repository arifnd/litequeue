<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

final class SupervisorState
{
    /**
     * @param  array<string, int>  $processes
     */
    public function __construct(
        public string $name,
        public string $master,
        public int $pid,
        public string $environment,
        public string $status,
        public array $processes,
        public SupervisorOptions $options,
    ) {}

    /**
     * @return array<string, string>
     */
    public function supervisorHash(): array
    {
        return [
            'name' => $this->name,
            'master' => $this->master,
            'pid' => (string) $this->pid,
            'status' => $this->status,
            'processes' => json_encode($this->processes, JSON_THROW_ON_ERROR),
            'options' => $this->options->toJson(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function masterHash(): array
    {
        return [
            'name' => $this->master,
            'environment' => $this->environment,
            'pid' => (string) $this->pid,
            'status' => $this->status,
            'supervisors' => json_encode([$this->name], JSON_THROW_ON_ERROR),
        ];
    }
}
