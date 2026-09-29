<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Commands\MakeJobCommand;
use Arifnd\LiteQueue\Console\Commands\MakeServiceCommand;
use Arifnd\LiteQueue\Console\Commands\MakeTraitCommand;
use Arifnd\LiteQueue\Console\LiteQueueConsole;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Queue\SyncQueue;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Console\Command;
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

    public function test_make_service_creates_a_service_class(): void
    {
        $this->runCommand(MakeServiceCommand::class, ['name' => 'Billing/InvoiceService']);

        $path = $this->basePath.'/app/Services/Billing/InvoiceService.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\Services\Billing;', $this->files->get($path));
        $this->assertStringContainsString('class InvoiceService', $this->files->get($path));
    }

    public function test_make_trait_prefers_the_concerns_directory(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/app/Concerns');

        $this->runCommand(MakeTraitCommand::class, ['name' => 'RecordsActivity']);

        $path = $this->basePath.'/app/Concerns/RecordsActivity.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\Concerns;', $this->files->get($path));
        $this->assertStringContainsString('trait RecordsActivity', $this->files->get($path));
    }

    public function test_make_trait_uses_traits_directory_when_present(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/app/Traits');

        $this->runCommand(MakeTraitCommand::class, ['name' => 'Loggable']);

        $this->assertFileExists($this->basePath.'/app/Traits/Loggable.php');
        $this->assertStringContainsString('namespace App\Traits;', $this->files->get($this->basePath.'/app/Traits/Loggable.php'));
    }

    public function test_bootstrap_registers_a_horizon_connection_with_the_configured_prefix(): void
    {
        $method = new \ReflectionMethod(LiteQueueConsole::class, 'registerHorizonConnection');

        $redis = [
            'default' => ['host' => '127.0.0.1', 'port' => 6379],
            'options' => ['prefix' => 'laravel_database_'],
        ];
        $config = ['supervisor' => ['horizon' => [
            'redis_connection' => 'horizon',
            'prefix' => 'myapp_horizon:',
        ]]];

        $result = $method->invoke(null, $redis, $config);

        $this->assertSame('myapp_horizon:', $result['horizon']['options']['prefix']);
        $this->assertSame('127.0.0.1', $result['horizon']['host']);
        $this->assertSame('laravel_database_', $result['options']['prefix']);
    }

    public function test_bootstrap_keeps_an_existing_horizon_connection(): void
    {
        $method = new \ReflectionMethod(LiteQueueConsole::class, 'registerHorizonConnection');

        $redis = [
            'default' => ['host' => '127.0.0.1'],
            'horizon' => ['host' => '10.0.0.9', 'options' => ['prefix' => 'custom:']],
        ];

        $result = $method->invoke(null, $redis, []);

        $this->assertSame('10.0.0.9', $result['horizon']['host']);
    }

    /**
     * @param  class-string<Command>  $command
     * @param  array<string, mixed>  $parameters
     */
    private function runCommand(string $command, array $parameters): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);
        $instance = new $command;
        $instance->setLaravel($container);

        $application = new SymfonyApplication;
        $application->addCommand($instance);

        (new CommandTester($instance))->execute($parameters);
    }
}
