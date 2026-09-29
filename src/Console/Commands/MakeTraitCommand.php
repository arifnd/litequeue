<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeTraitCommand extends Command
{
    protected $signature = 'make:trait
        {name : The name of the trait}
        {--force : Overwrite the trait if it already exists}
        {--path= : Base application path}';

    protected $description = 'Create a new trait';

    public function handle(): int
    {
        $files = new Filesystem;

        $relative = str_replace('\\', '/', trim((string) $this->argument('name'), '\\/'));
        $segments = $relative === '' ? [] : explode('/', $relative);
        $class = (string) array_pop($segments);
        $subNamespace = implode('\\', $segments);

        $basePath = rtrim((string) ($this->option('path') ?: $this->laravel['litequeue.base_path']), '/');

        [$namespace, $directory] = $this->resolveLocation($basePath);

        if ($subNamespace !== '') {
            $namespace .= '\\'.$subNamespace;
            $directory .= '/'.implode('/', $segments);
        }

        $path = $directory.'/'.$class.'.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("Trait [{$path}] already exists.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($directory);
        $files->put($path, $this->render('trait.stub', $namespace, $class));
        $this->info("Trait [{$path}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveLocation(string $basePath): array
    {
        return match (true) {
            is_dir($basePath.'/app/Concerns') => ['App\\Concerns', $basePath.'/app/Concerns'],
            is_dir($basePath.'/app/Traits') => ['App\\Traits', $basePath.'/app/Traits'],
            default => ['App', $basePath.'/app'],
        };
    }

    protected function render(string $stub, string $namespace, string $class): string
    {
        $contents = (new Filesystem)->get(dirname(__DIR__, 3).'/stubs/'.$stub);

        return str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $class], $contents);
    }
}
