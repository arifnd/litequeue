<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Connections;

use Arifnd\LiteQueue\Contracts\ConnectorInterface;
use Closure;
use Illuminate\Contracts\Queue\Queue as QueueContract;

class ClosureConnector implements ConnectorInterface
{
    /**
     * @param  Closure(array<string, mixed>): QueueContract  $resolver
     */
    public function __construct(protected Closure $resolver) {}

    public function connect(array $config): QueueContract
    {
        return ($this->resolver)($config);
    }
}
