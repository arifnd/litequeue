<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Fixtures;

use Arifnd\LiteQueue\Jobs\InteractsWithQueue;
use Closure;

class MiddlewareJob
{
    use InteractsWithQueue;

    /**
     * @var array<int, string>
     */
    public static array $log = [];

    public function __construct(public string $value = 'm') {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RecordingMiddleware];
    }

    public function handle(): void
    {
        self::$log[] = 'handle:'.$this->attempts();
    }
}

class RecordingMiddleware
{
    public function handle(object $job, Closure $next): mixed
    {
        MiddlewareJob::$log[] = 'before';

        $result = $next($job);

        MiddlewareJob::$log[] = 'after';

        return $result;
    }
}
