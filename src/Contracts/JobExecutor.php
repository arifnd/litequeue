<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Contracts;

interface JobExecutor
{
    /**
     * Execute an already-decoded job instance.
     */
    public function execute(object $job): void;
}
