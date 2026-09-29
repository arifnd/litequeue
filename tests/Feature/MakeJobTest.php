<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Commands\MakeJobCommand;

final class MakeJobTest extends ConsoleTestCase
{
    public function test_it_creates_a_queued_job(): void
    {
        $this->runCommand(MakeJobCommand::class, ['name' => 'SendEmail']);

        $path = $this->basePath.'/app/Jobs/SendEmail.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('class SendEmail implements ShouldQueue', $this->files->get($path));
    }

    public function test_it_creates_a_sync_job(): void
    {
        $this->runCommand(MakeJobCommand::class, ['name' => 'SyncThing', '--sync' => true]);

        $path = $this->basePath.'/app/Jobs/SyncThing.php';

        $this->assertFileExists($path);
        $this->assertStringNotContainsString('ShouldQueue', $this->files->get($path));
    }

    public function test_it_creates_a_one_shot_job(): void
    {
        $this->runCommand(MakeJobCommand::class, ['name' => 'OnceThing', '--once' => true]);

        $path = $this->basePath.'/app/Jobs/OnceThing.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('ShouldQueue', $this->files->get($path));
    }

    public function test_it_creates_a_matching_test(): void
    {
        $this->runCommand(MakeJobCommand::class, ['name' => 'SendEmail', '--test' => true]);

        $this->assertFileExists($this->basePath.'/app/Jobs/SendEmail.php');
        $this->assertFileExists($this->basePath.'/tests/Feature/SendEmailTest.php');
    }

    public function test_it_supports_nested_namespaces(): void
    {
        $this->runCommand(MakeJobCommand::class, ['name' => 'Mail/WelcomeEmail']);

        $path = $this->basePath.'/app/Jobs/Mail/WelcomeEmail.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\Jobs\Mail;', $this->files->get($path));
    }
}
