<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Commands\InstallCommand;

final class InstallCommandTest extends ConsoleTestCase
{
    public function test_it_publishes_the_litequeue_and_database_configs(): void
    {
        $this->runCommand(InstallCommand::class, []);

        $this->assertFileExists($this->basePath.'/config/litequeue.php');
        $this->assertFileExists($this->basePath.'/config/database.php');
    }

    public function test_it_can_skip_the_database_config(): void
    {
        $this->runCommand(InstallCommand::class, ['--no-database' => true]);

        $this->assertFileExists($this->basePath.'/config/litequeue.php');
        $this->assertFileDoesNotExist($this->basePath.'/config/database.php');
    }

    public function test_it_does_not_overwrite_an_existing_database_config(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/config');
        $this->files->put($this->basePath.'/config/database.php', '<?php return ["custom" => true];');

        $this->runCommand(InstallCommand::class, []);

        $this->assertStringContainsString('custom', $this->files->get($this->basePath.'/config/database.php'));
    }

    public function test_it_creates_the_sqlite_database_file(): void
    {
        $this->runCommand(InstallCommand::class, []);

        $this->assertFileExists($this->basePath.'/database/database.sqlite');
    }
}
