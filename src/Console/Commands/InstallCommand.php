<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'install
        {--force : Overwrite existing configuration files}
        {--no-database : Do not publish config/database.php}';

    protected $description = 'Publish the LiteQueue and database configuration files';

    public function handle(): int
    {
        $files = new Filesystem;
        $basePath = rtrim((string) ($this->laravel['litequeue.base_path'] ?? getcwd()), '/');

        $status = $this->publish($files, $basePath.'/config/litequeue.php', 'config/litequeue.php');

        if (! $this->option('no-database')) {
            $this->publish($files, $basePath.'/config/database.php', 'config/database.php', required: false);
            $this->createSqliteDatabase($files, $basePath);
        }

        return $status;
    }

    protected function createSqliteDatabase(Filesystem $files, string $basePath): void
    {
        $path = $basePath.'/config/database.php';

        if (! $files->exists($path)) {
            return;
        }

        $config = require $path;

        if (! is_array($config)) {
            return;
        }

        $connection = $config['connections'][$config['default'] ?? null] ?? null;
        $database = $connection['database'] ?? null;

        if (($connection['driver'] ?? null) !== 'sqlite' || ! is_string($database) || $database === '') {
            return;
        }

        if ($database === ':memory:' || str_starts_with($database, 'file:')) {
            return;
        }

        if (! str_starts_with($database, '/')) {
            $database = rtrim($basePath, '/').'/'.ltrim($database, '/');
        }

        if (! $files->exists($database)) {
            $files->ensureDirectoryExists(dirname($database));
            $files->put($database, '');

            $this->info("SQLite database created at [{$database}].");
        }
    }

    protected function publish(Filesystem $files, string $target, string $source, bool $required = true): int
    {
        if ($files->exists($target) && ! $this->option('force')) {
            if ($required) {
                $this->error("Configuration [{$target}] already exists. Use --force to overwrite.");

                return self::FAILURE;
            }

            $this->line("Configuration [{$target}] already exists. Skipping.");

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->copy(dirname(__DIR__, 3).'/'.$source, $target);

        $this->info("Configuration published to [{$target}].");

        return self::SUCCESS;
    }
}
