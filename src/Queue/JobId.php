<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Illuminate\Support\Str;

final class JobId
{
    /**
     * Generate a Laravel-compatible UUID for a job payload.
     */
    public static function generate(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Generate a random identifier, matching Laravel's Redis reserved id.
     */
    public static function random(int $length = 32): string
    {
        return Str::random($length);
    }
}
