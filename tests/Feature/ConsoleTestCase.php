<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\LiteQueueConsole;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Tester\CommandTester;

abstract class ConsoleTestCase extends TestCase
{
    protected string $basePath;

    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->basePath = sys_get_temp_dir().'/litequeue_'.uniqid();
        $this->files->ensureDirectoryExists($this->basePath);
    }

    protected function tearDown(): void
    {
        LiteQueueConsole::reset();

        // Testbench rebuilds its application between tests and expects the facade
        // root to be the current test application.
        Facade::setFacadeApplication($this->app);

        $this->files->deleteDirectory($this->basePath);

        parent::tearDown();
    }

    /**
     * @param  class-string<Command>  $command
     * @param  array<string, mixed>  $parameters
     */
    protected function runCommand(string $command, array $parameters): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);
        $instance = new $command;
        $instance->setLaravel($container);

        $application = new SymfonyApplication;
        $application->addCommand($instance);

        (new CommandTester($instance))->execute($parameters);
    }
}
