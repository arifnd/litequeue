<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeJobCommand extends Command
{
    protected $signature = 'make:job
        {name : The name of the job class}
        {--sync : Create a synchronous job}
        {--queued : Create a queued job (default)}
        {--once : Create a one-shot queued job}
        {--test : Create a matching test class}
        {--pest : Create a matching Pest test}
        {--force : Overwrite the job if it already exists}
        {--path= : Base application path}';

    protected $description = 'Create a new job class';

    public function handle(): int
    {
        $files = new Filesystem;

        $relative = str_replace('\\', '/', trim((string) $this->argument('name'), '\\/'));
        $segments = $relative === '' ? [] : explode('/', $relative);
        $class = (string) array_pop($segments);
        $subNamespace = implode('\\', $segments);

        $namespace = 'App\\Jobs'.($subNamespace !== '' ? '\\'.$subNamespace : '');
        $basePath = $this->option('path') ?: $this->laravel['litequeue.base_path'];
        $directory = rtrim((string) $basePath, '/').'/app/Jobs'.($segments ? '/'.implode('/', $segments) : '');
        $path = $directory.'/'.$class.'.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("Job [{$path}] already exists.");

            return self::FAILURE;
        }

        $stub = match (true) {
            (bool) $this->option('sync') => 'job.sync.stub',
            (bool) $this->option('once') => 'job.queued.oneshot.stub',
            default => 'job.queued.stub',
        };

        $files->ensureDirectoryExists($directory);
        $files->put($path, $this->render($stub, $namespace, $class));
        $this->info("Job [{$path}] created successfully.");

        if ($this->option('test') || $this->option('pest')) {
            $testDirectory = rtrim((string) $basePath, '/').'/tests/Feature';
            $testPath = $testDirectory.'/'.$class.'Test.php';

            if (! $files->exists($testPath) || $this->option('force')) {
                $files->ensureDirectoryExists($testDirectory);
                $files->put($testPath, $this->render('job.test.stub', $namespace, $class));
                $this->info("Test [{$testPath}] created successfully.");
            }
        }

        return self::SUCCESS;
    }

    protected function render(string $stub, string $namespace, string $class): string
    {
        $contents = (new Filesystem)->get(dirname(__DIR__, 3).'/stubs/'.$stub);

        return str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $class], $contents);
    }
}
