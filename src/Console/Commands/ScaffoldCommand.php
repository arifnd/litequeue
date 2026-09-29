<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

abstract class ScaffoldCommand extends Command
{
    /**
     * The label shown in the command output (Job, Service, Trait, ...).
     */
    abstract protected function type(): string;

    /**
     * Resolve the namespace root and absolute directory root for this scaffold.
     *
     * @return array{0: string, 1: string}
     */
    abstract protected function location(string $basePath): array;

    /**
     * The stub file, relative to the package `stubs` directory.
     */
    abstract protected function stub(): string;

    public function handle(): int
    {
        $files = new Filesystem;
        $basePath = rtrim((string) ($this->option('path') ?: $this->laravel['litequeue.base_path']), '/');

        $relative = str_replace('\\', '/', trim((string) $this->argument('name'), '\\/'));
        $segments = $relative === '' ? [] : explode('/', $relative);
        $class = (string) array_pop($segments);

        if ($class === '') {
            $this->error(strtolower($this->type()).' name is required.');

            return self::FAILURE;
        }

        [$namespace, $directory] = $this->location($basePath);

        if ($segments !== []) {
            $namespace .= '\\'.implode('\\', $segments);
            $directory .= '/'.implode('/', $segments);
        }

        $path = $directory.'/'.$class.'.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("{$this->type()} [{$path}] already exists.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($directory);
        $files->put($path, $this->render($this->stub(), $namespace, $class));
        $this->info("{$this->type()} [{$path}] created successfully.");

        return $this->after($files, $basePath, $namespace, $class);
    }

    protected function after(Filesystem $files, string $basePath, string $namespace, string $class): int
    {
        return self::SUCCESS;
    }

    protected function render(string $stub, string $namespace, string $class): string
    {
        $contents = (new Filesystem)->get(dirname(__DIR__, 3).'/stubs/'.$stub);

        return str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $class], $contents);
    }
}
