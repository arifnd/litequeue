<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Contracts;

use Illuminate\Contracts\Queue\Queue;

interface ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): Queue;
}
