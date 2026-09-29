<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Support;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobAttempted;
use ReflectionObject;
use Throwable;

/**
 * Builds queue events across Laravel versions whose signatures differ.
 */
final class JobEventFactory
{
    /**
     * Laravel 11/12 expose "exceptionOccurred" (bool); Laravel 13 exposes
     * "exception" (?Throwable). Constructing with two arguments and setting the
     * matching property keeps a single code path valid on every supported version.
     */
    public static function attempted(string $connectionName, JobContract $job, ?Throwable $exception = null): JobAttempted
    {
        $event = new JobAttempted($connectionName, $job);

        $reflection = new ReflectionObject($event);

        if ($reflection->hasProperty('exception')) {
            $reflection->getProperty('exception')->setValue($event, $exception);
        } elseif ($reflection->hasProperty('exceptionOccurred')) {
            $reflection->getProperty('exceptionOccurred')->setValue($event, $exception !== null);
        }

        return $event;
    }
}
