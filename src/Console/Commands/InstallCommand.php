<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'install {--force : Overwrite an existing configuration file}';

    protected $description = 'Publish the LiteQueue configuration file';

    public function handle(): int
    {
        $files = new Filesystem;
        $basePath = $this->laravel['litequeue.base_path'] ?? getcwd();
        $target = rtrim((string) $basePath, '/').'/config/litequeue.php';

        if ($files->exists($target) && ! $this->option('force')) {
            $this->error("Configuration [{$target}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->copy(dirname(__DIR__, 3).'/config/litequeue.php', $target);

        $this->info("Configuration published to [{$target}].");

        return self::SUCCESS;
    }
}
