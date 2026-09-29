<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service
        {name : The name of the service class}
        {--force : Overwrite the service if it already exists}
        {--path= : Base application path}';

    protected $description = 'Create a new service class';

    public function handle(): int
    {
        $files = new Filesystem;

        $relative = str_replace('\\', '/', trim((string) $this->argument('name'), '\\/'));
        $segments = $relative === '' ? [] : explode('/', $relative);
        $class = (string) array_pop($segments);
        $subNamespace = implode('\\', $segments);

        $namespace = 'App\\Services'.($subNamespace !== '' ? '\\'.$subNamespace : '');
        $basePath = $this->option('path') ?: $this->laravel['litequeue.base_path'];
        $directory = rtrim((string) $basePath, '/').'/app/Services'.($segments ? '/'.implode('/', $segments) : '');
        $path = $directory.'/'.$class.'.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("Service [{$path}] already exists.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($directory);
        $files->put($path, $this->render('service.stub', $namespace, $class));
        $this->info("Service [{$path}] created successfully.");

        return self::SUCCESS;
    }

    protected function render(string $stub, string $namespace, string $class): string
    {
        $contents = (new Filesystem)->get(dirname(__DIR__, 3).'/stubs/'.$stub);

        return str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $class], $contents);
    }
}
