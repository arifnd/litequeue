<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

final class ConfigLoader
{
    public function __construct(private string $basePath) {}

    /**
     * @return array<string, mixed>
     */
    public function litequeue(): array
    {
        return $this->load([
            $this->basePath.'/config/litequeue.php',
            $this->basePath.'/litequeue.php',
            dirname(__DIR__, 2).'/config/litequeue.php',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function database(): array
    {
        return $this->resolveSqlitePaths($this->load([
            $this->basePath.'/config/database.php',
            $this->basePath.'/database.php',
            dirname(__DIR__, 2).'/config/database.php',
        ]));
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<string, mixed>
     */
    private function load(array $candidates): array
    {
        foreach ($candidates as $file) {
            if (! is_file($file)) {
                continue;
            }

            $config = require $file;

            if (is_array($config)) {
                return $config;
            }
        }

        return [];
    }

    /**
     * Resolve relative SQLite paths against the application base path.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function resolveSqlitePaths(array $config): array
    {
        foreach ($config['connections'] ?? [] as $name => $connection) {
            $database = $connection['database'] ?? null;

            if (($connection['driver'] ?? null) !== 'sqlite'
                || ! is_string($database)
                || $database === ''
                || $database === ':memory:'
                || str_starts_with($database, 'file:')
                || str_starts_with($database, '/')) {
                continue;
            }

            $config['connections'][$name]['database'] = rtrim($this->basePath, '/').'/'.ltrim($database, '/');
        }

        return $config;
    }
}
