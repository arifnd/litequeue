<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Commands\MakeTraitCommand;

final class MakeTraitTest extends ConsoleTestCase
{
    public function test_it_prefers_the_concerns_directory(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/app/Concerns');

        $this->runCommand(MakeTraitCommand::class, ['name' => 'RecordsActivity']);

        $path = $this->basePath.'/app/Concerns/RecordsActivity.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\Concerns;', $this->files->get($path));
        $this->assertStringContainsString('trait RecordsActivity', $this->files->get($path));
    }

    public function test_it_uses_the_traits_directory_when_present(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/app/Traits');

        $this->runCommand(MakeTraitCommand::class, ['name' => 'Loggable']);

        $path = $this->basePath.'/app/Traits/Loggable.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\Traits;', $this->files->get($path));
    }

    public function test_it_falls_back_to_the_app_namespace(): void
    {
        $this->runCommand(MakeTraitCommand::class, ['name' => 'Plain']);

        $path = $this->basePath.'/app/Plain.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App;', $this->files->get($path));
    }
}
