<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Failed;

use Arifnd\LiteQueue\Contracts\FailedJobProvider;
use DateTimeInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class DatabaseFailedJobProvider implements FailedJobProvider
{
    public function __construct(
        protected ConnectionResolverInterface $resolver,
        protected string $database,
        protected string $table = 'failed_jobs',
    ) {}

    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int|null
    {
        $uuid = json_decode($payload, true)['uuid'] ?? Str::uuid()->toString();

        $exception = (string) mb_convert_encoding((string) $exception, 'UTF-8');

        return $this->getTable()->insertGetId([
            'uuid' => $uuid,
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => $exception,
            'failed_at' => Carbon::now(),
        ]) ? $uuid : null;
    }

    public function all(): array
    {
        return $this->getTable()->orderBy('id', 'desc')->get()->all();
    }

    public function find(mixed $id): ?object
    {
        return $this->getTable()->find($id);
    }

    public function forget(mixed $id): bool
    {
        return $this->getTable()->where('id', $id)->delete() > 0;
    }

    public function flush(?int $hours = null): void
    {
        $this->getTable()
            ->when($hours, fn ($query, $hours) => $query->where('failed_at', '<=', Carbon::now()->subHours((int) $hours)))
            ->delete();
    }

    public function prune(DateTimeInterface $before): int
    {
        return $this->getTable()->where('failed_at', '<', $before)->delete();
    }

    public function count(): int
    {
        return $this->getTable()->count();
    }

    public function getTable(): Builder
    {
        return $this->resolver->connection($this->database)->table($this->table);
    }
}
