<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

use Illuminate\Filesystem\Filesystem;

class MakeJobCommand extends ScaffoldCommand
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

    protected function type(): string
    {
        return 'Job';
    }

    protected function location(string $basePath): array
    {
        return ['App\\Jobs', $basePath.'/app/Jobs'];
    }

    protected function stub(): string
    {
        return match (true) {
            (bool) $this->option('sync') => 'job.sync.stub',
            (bool) $this->option('once') => 'job.queued.oneshot.stub',
            default => 'job.queued.stub',
        };
    }

    protected function after(Filesystem $files, string $basePath, string $namespace, string $class): int
    {
        if (! $this->option('test') && ! $this->option('pest')) {
            return self::SUCCESS;
        }

        $directory = $basePath.'/tests/Feature';
        $path = $directory.'/'.$class.'Test.php';

        if (! $files->exists($path) || $this->option('force')) {
            $files->ensureDirectoryExists($directory);
            $files->put($path, $this->render('job.test.stub', $namespace, $class));
            $this->info("Test [{$path}] created successfully.");
        }

        return self::SUCCESS;
    }
}
