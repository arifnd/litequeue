<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Support;

use Illuminate\Contracts\Container\Container;

final class Config
{
    /**
     * Read a LiteQueue config value from either the Laravel (`litequeue.*`) or
     * standalone (top-level) config shape.
     */
    public static function get(Container $app, string $key, mixed $default = null): mixed
    {
        if (! $app->bound('config')) {
            return $default;
        }

        $config = $app->make('config');

        return $config['litequeue.'.$key] ?? $config[$key] ?? $default;
    }
}
