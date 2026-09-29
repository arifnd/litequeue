<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Bus;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Pipeline\Pipeline;
use RuntimeException;

class Dispatcher implements DispatcherContract
{
    protected ?Closure $queueResolver = null;

    /**
     * @var array<class-string, class-string>
     */
    protected array $maps = [];

    /**
     * @var array<int, mixed>
     */
    protected array $pipes = [];

    /**
     * @var (callable(mixed): mixed)|null
     */
    protected $handlerResolver = null;

    public function __construct(protected Container $container) {}

    public function dispatch($command)
    {
        if ($this->queueResolver !== null && $command instanceof ShouldQueue) {
            return $this->dispatchToQueue($command);
        }

        return $this->dispatchNow($command);
    }

    public function dispatchSync($command, $handler = null)
    {
        return $this->dispatchNow($command, $handler);
    }

    public function dispatchNow($command, $handler = null)
    {
        $handler = $handler ?: $this->getCommandHandler($command);

        $execute = function ($command) use ($handler) {
            if ($handler) {
                return $this->container->call([$handler, 'handle'], ['command' => $command]);
            }

            return $this->container->call([$command, 'handle']);
        };

        if ($this->pipes === []) {
            return $execute($command);
        }

        return (new Pipeline($this->container))
            ->send($command)
            ->through($this->pipes)
            ->then($execute);
    }

    public function dispatchAfterResponse($command, $handler = null): void
    {
        if (method_exists($this->container, 'terminating')) {
            $this->container->terminating(fn () => $this->dispatchNow($command, $handler));

            return;
        }

        $this->dispatchNow($command, $handler);
    }

    /**
     * @return mixed
     */
    public function dispatchToQueue($command)
    {
        if ($this->queueResolver === null) {
            throw new RuntimeException('No queue resolver has been set on the dispatcher.');
        }

        $connection = property_exists($command, 'connection') ? $command->connection : null;
        $queue = ($this->queueResolver)($connection);

        if (! $queue instanceof QueueContract) {
            throw new RuntimeException('Queue resolver did not return a queue connection.');
        }

        if (method_exists($command, 'queue')) {
            return $command->queue($queue, $command);
        }

        return $this->pushCommandToQueue($queue, $command);
    }

    protected function pushCommandToQueue(QueueContract $queue, $command)
    {
        if (isset($command->queue, $command->delay)) {
            return $queue->laterOn($command->queue, $command->delay, $command);
        }

        if (isset($command->queue)) {
            return $queue->pushOn($command->queue, $command);
        }

        if (isset($command->delay)) {
            return $queue->later($command->delay, $command);
        }

        return $queue->push($command);
    }

    public function hasCommandHandler($command)
    {
        return array_key_exists(get_class($command), $this->maps);
    }

    public function getCommandHandler($command)
    {
        if ($this->hasCommandHandler($command)) {
            return $this->container->make($this->maps[get_class($command)]);
        }

        if ($this->handlerResolver !== null) {
            return ($this->handlerResolver)($command);
        }

        return null;
    }

    public function pipeThrough(array $pipes)
    {
        $this->pipes = $pipes;

        return $this;
    }

    public function map(array $map)
    {
        $this->maps = array_merge($this->maps, $map);

        return $this;
    }

    /**
     * @param  (callable(mixed): mixed)  $resolver
     */
    public function setHandlerResolver(callable $resolver): static
    {
        $this->handlerResolver = $resolver;

        return $this;
    }

    /**
     * @param  (callable(string|null): QueueContract)  $resolver
     */
    public function setQueueResolver(callable $resolver): static
    {
        $this->queueResolver = Closure::fromCallable($resolver);

        return $this;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function findBatch(string $batchId): mixed
    {
        return null;
    }
}
