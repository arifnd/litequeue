<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Jobs;

use Arifnd\LiteQueue\Bus\UniqueLock;
use Arifnd\LiteQueue\Jobs\Attributes\DeleteWhenMissingModels;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pipeline\Pipeline;
use ReflectionClass;
use RuntimeException;

class CallQueuedHandler
{
    public function __construct(
        protected Dispatcher $dispatcher,
        protected Container $container,
    ) {}

    public function call(Job $job, array $data): void
    {
        try {
            $command = $this->setJobInstanceIfNecessary($job, $this->getCommand($data));
        } catch (ModelNotFoundException $e) {
            $this->handleModelNotFound($job, $e);

            return;
        }

        $this->dispatchThroughMiddleware($job, $command);

        if (! $job->isReleased() && ! $this->commandShouldBeUniqueUntilProcessing($command)) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        if (! $job->hasFailed() && ! $job->isReleased()) {
            $this->ensureNextJobInChainIsDispatched($command);
        }

        if (! $job->isDeletedOrReleased()) {
            $job->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function getCommand(array $data): mixed
    {
        if (str_starts_with((string) $data['command'], 'O:')) {
            return unserialize($data['command']);
        }

        if ($this->container->bound(Encrypter::class)) {
            return unserialize($this->container->make(Encrypter::class)->decrypt($data['command']));
        }

        throw new RuntimeException('Unable to extract job payload.');
    }

    protected function dispatchThroughMiddleware(Job $job, mixed $command): mixed
    {
        if ($command instanceof \__PHP_Incomplete_Class) {
            throw new Exception('Job is incomplete class: '.json_encode($command));
        }

        $middleware = method_exists($command, 'middleware') ? $command->middleware() : [];

        if (is_object($command) && property_exists($command, 'middleware') && is_array($command->middleware)) {
            $middleware = array_merge($middleware, $command->middleware);
        }

        return (new Pipeline($this->container))
            ->send($command)
            ->through($middleware)
            ->then(fn ($command) => $this->dispatcher->dispatchNow(
                $command,
                $this->resolveHandler($job, $command)
            ));
    }

    protected function resolveHandler(Job $job, mixed $command): mixed
    {
        $handler = $this->dispatcher->getCommandHandler($command) ?: null;

        if ($handler) {
            $this->setJobInstanceIfNecessary($job, $handler);
        }

        return $handler;
    }

    protected function setJobInstanceIfNecessary(Job $job, mixed $instance): mixed
    {
        if (is_object($instance) && in_array(InteractsWithQueue::class, class_uses_recursive($instance), true)) {
            $instance->setJob($job);
        }

        return $instance;
    }

    protected function ensureNextJobInChainIsDispatched(mixed $command): void
    {
        if (method_exists($command, 'dispatchNextJobInChain')) {
            $command->dispatchNextJobInChain();
        }
    }

    protected function ensureUniqueJobLockIsReleased(mixed $command): void
    {
        if (! $this->commandShouldBeUnique($command)) {
            return;
        }

        if (! $this->container->bound(Cache::class)) {
            return;
        }

        (new UniqueLock($this->container->make(Cache::class)))->release($command);
    }

    protected function commandShouldBeUnique(mixed $command): bool
    {
        return $command instanceof ShouldBeUnique;
    }

    protected function commandShouldBeUniqueUntilProcessing(mixed $command): bool
    {
        return $command instanceof ShouldBeUniqueUntilProcessing;
    }

    protected function handleModelNotFound(Job $job, ModelNotFoundException $e): void
    {
        $class = $job->resolveQueuedJobClass();

        try {
            $reflectionClass = new ReflectionClass($class);
            $shouldDelete = $reflectionClass->getDefaultProperties()['deleteWhenMissingModels']
                ?? count($reflectionClass->getAttributes(DeleteWhenMissingModels::class)) !== 0;
        } catch (Exception) {
            $shouldDelete = false;
        }

        if ($shouldDelete) {
            $job->delete();

            return;
        }

        $job->fail($e);
    }

    /**
     * Call the "failed" method on the job instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function failed(array $data, mixed $e, string $uuid, ?Job $job = null): void
    {
        $command = $this->getCommand($data);

        if ($job !== null) {
            $command = $this->setJobInstanceIfNecessary($job, $command);
        }

        if (! $this->commandShouldBeUniqueUntilProcessing($command)) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        if ($command instanceof \__PHP_Incomplete_Class) {
            return;
        }

        if (method_exists($command, 'failed')) {
            $command->failed($e);
        }
    }
}
