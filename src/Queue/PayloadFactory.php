<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Arifnd\LiteQueue\Contracts\PayloadFactory as PayloadFactoryContract;
use Arifnd\LiteQueue\Exceptions\InvalidPayloadException;
use Arifnd\LiteQueue\Jobs\CallQueuedHandler;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Collection;
use Throwable;

class PayloadFactory implements PayloadFactoryContract
{
    /**
     * @var (callable(string, array<string, mixed>): array<string, mixed>)|null
     */
    protected static $createPayloadCallback = null;

    protected bool $signPayloads = false;

    protected ?string $signingKey = null;

    /**
     * @var array<int, class-string>|null
     */
    protected ?array $allowedClasses = null;

    public function __construct(
        protected Container $container,
        protected string $handler = CallQueuedHandler::class.'@call',
    ) {
        $security = $this->securityConfig();

        $this->signPayloads = (bool) ($security['sign_payloads'] ?? false);
        $this->signingKey = isset($security['signing_key']) ? (string) $security['signing_key'] : null;

        $allowed = $security['allowed_classes'] ?? null;
        $this->allowedClasses = is_array($allowed) ? array_values($allowed) : null;

        if ($this->signPayloads && ($this->signingKey === null || $this->signingKey === '')) {
            throw new InvalidPayloadException('Payload signing is enabled but no signing key is configured.');
        }
    }

    public function make(
        object|string $job,
        string $queue,
        ?string $connection = null,
        DateTimeInterface|DateInterval|int|null $delay = null,
    ): string {
        $payload = $this->makeArray($job, $queue);
        $payload['delay'] = $delay === null ? null : Delay::seconds($delay);

        return $this->encode($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function makeArray(object|string $job, string $queue, mixed $data = ''): array
    {
        return is_object($job)
            ? $this->objectPayload($job, $queue)
            : $this->stringPayload($job, $queue, $data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidPayloadException(
                'Unable to JSON encode payload. Error ('.json_last_error().'): '.json_last_error_msg(),
                $payload
            );
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    protected function objectPayload(object $job, string $queue): array
    {
        $data = [
            'commandName' => get_class($job),
            'command' => $this->serializeJob($job),
            'batchId' => $this->attribute($job, 'batchId'),
        ];

        if ($this->signPayloads) {
            $data['signature'] = $this->sign((string) $data['command']);
        }

        return $this->withHooks($queue, [
            'uuid' => JobId::generate(),
            'displayName' => $this->displayName($job),
            'job' => $this->handler,
            'maxTries' => $this->jobTries($job),
            'maxExceptions' => $this->attribute($job, 'maxExceptions'),
            'failOnTimeout' => $this->attribute($job, 'failOnTimeout', false),
            'backoff' => $this->jobBackoff($job),
            'timeout' => $this->attribute($job, 'timeout'),
            'retryUntil' => $this->jobExpiration($job),
            'data' => $data,
            'createdAt' => time(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function stringPayload(string $job, string $queue, mixed $data): array
    {
        return $this->withHooks($queue, [
            'uuid' => JobId::generate(),
            'displayName' => str_contains($job, '@') ? explode('@', $job)[0] : $job,
            'job' => $job,
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'data' => $data,
            'createdAt' => time(),
        ]);
    }

    protected function serializeJob(object $job): string
    {
        try {
            $job = clone $job;

            return $this->shouldBeEncrypted($job) && $this->container->bound(Encrypter::class)
                ? $this->container->make(Encrypter::class)->encrypt(serialize($job))
                : serialize($job);
        } catch (Throwable $e) {
            throw new InvalidPayloadException(
                sprintf('Failed to serialize job of type [%s]: %s', get_class($job), $e->getMessage()),
                $job
            );
        }
    }

    protected function displayName(object $job): string
    {
        return method_exists($job, 'displayName') ? (string) $job->displayName() : get_class($job);
    }

    protected function jobTries(object $job): mixed
    {
        if (! method_exists($job, 'tries') && ! $this->hasAttribute($job, 'tries')) {
            return null;
        }

        return $this->attribute($job, 'tries');
    }

    protected function jobBackoff(object $job): ?string
    {
        if (! method_exists($job, 'backoff') && ! $this->hasAttribute($job, 'backoff')) {
            return null;
        }

        $backoff = $this->attribute($job, 'backoff');

        if ($backoff === null) {
            return null;
        }

        return Collection::wrap($backoff)
            ->map(fn ($value) => $value instanceof DateTimeInterface ? Delay::seconds($value) : $value)
            ->implode(',');
    }

    protected function jobExpiration(object $job): mixed
    {
        if (! method_exists($job, 'retryUntil') && ! $this->hasAttribute($job, 'retryUntil')) {
            return null;
        }

        $expiration = $this->attribute($job, 'retryUntil');

        return $expiration instanceof DateTimeInterface ? $expiration->getTimestamp() : $expiration;
    }

    protected function shouldBeEncrypted(object $job): bool
    {
        if ($job instanceof ShouldBeEncrypted) {
            return true;
        }

        return (bool) $this->attribute($job, 'shouldBeEncrypted', false);
    }

    protected function hasAttribute(object $job, string $name): bool
    {
        return property_exists($job, $name) || isset($job->{$name});
    }

    protected function attribute(object $job, string $name, mixed $default = null): mixed
    {
        if (property_exists($job, $name)) {
            return $job->{$name} ?? $default;
        }

        if (method_exists($job, $name)) {
            return $job->{$name}();
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withHooks(string $queue, array $payload): array
    {
        if (static::$createPayloadCallback === null) {
            return $payload;
        }

        return (static::$createPayloadCallback)($queue, $payload);
    }

    public static function createPayloadUsing(?callable $callback): void
    {
        static::$createPayloadCallback = $callback === null ? null : Closure::fromCallable($callback);
    }

    public function signsPayloads(): bool
    {
        return $this->signPayloads;
    }

    /**
     * @return array<int, class-string>|null
     */
    public function allowedClasses(): ?array
    {
        return $this->allowedClasses;
    }

    public function sign(string $value): string
    {
        return hash_hmac('sha256', $value, (string) $this->signingKey);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidPayloadException
     */
    public function verifyCommand(array $data): void
    {
        if (! $this->signPayloads) {
            return;
        }

        $command = $data['command'] ?? null;
        $signature = $data['signature'] ?? null;

        if (! is_string($command) || ! is_string($signature) || ! hash_equals($this->sign($command), $signature)) {
            throw new InvalidPayloadException('The job payload signature is invalid.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function securityConfig(): array
    {
        if (! $this->container->bound('config')) {
            return [];
        }

        $config = $this->container->make('config');
        $security = $config['litequeue.security'] ?? $config['security'] ?? null;

        return is_array($security) ? $security : [];
    }
}
