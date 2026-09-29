<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Commands\MakeJobCommand;
use Arifnd\LiteQueue\Console\LiteQueueConsole;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Queue\SyncQueue;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleTest extends TestCase
{
    private string $basePath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->basePath = sys_get_temp_dir().'/litequeue_'.uniqid();
        $this->files->ensureDirectoryExists($this->basePath);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->basePath);

        parent::tearDown();
    }

    public function test_bootstrap_resolves_the_default_connection(): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);

        $manager = $container->make(QueueManager::class);

        $this->assertInstanceOf(SyncQueue::class, $manager->connection());
    }

    public function test_make_job_creates_a_queued_job(): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);
        $command = new MakeJobCommand;
        $command->setLaravel($container);

        $application = new SymfonyApplication;
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['name' => 'SendEmail']);

        $path = $this->basePath.'/app/Jobs/SendEmail.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('class SendEmail implements ShouldQueue', $this->files->get($path));
    }

    public function test_make_job_supports_sync_jobs(): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);
        $command = new MakeJobCommand;
        $command->setLaravel($container);

        $application = new SymfonyApplication;
        $application->addCommand($command);

        (new CommandTester($command))->execute(['name' => 'SyncThing', '--sync' => true]);

        $path = $this->basePath.'/app/Jobs/SyncThing.php';

        $this->assertFileExists($path);
        $this->assertStringNotContainsString('ShouldQueue', $this->files->get($path));
    }
}
