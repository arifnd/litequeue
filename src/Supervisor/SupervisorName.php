<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

use Illuminate\Support\Str;

final class SupervisorName
{
    public static function master(?string $host = null): string
    {
        return Str::slug($host ?: gethostname() ?: 'litequeue').'-'.Str::lower(Str::random(4));
    }

    public static function supervisor(string $master, string $name): string
    {
        return $master.':'.$name;
    }
}
