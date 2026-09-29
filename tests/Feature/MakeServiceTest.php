<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Commands\MakeServiceCommand;

final class MakeServiceTest extends ConsoleTestCase
{
    public function test_it_creates_a_service_class(): void
    {
        $this->runCommand(MakeServiceCommand::class, ['name' => 'Billing/InvoiceService']);

        $path = $this->basePath.'/app/Services/Billing/InvoiceService.php';

        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\Services\Billing;', $this->files->get($path));
        $this->assertStringContainsString('class InvoiceService', $this->files->get($path));
    }

    public function test_it_does_not_overwrite_without_force(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/app/Services');
        $this->files->put($this->basePath.'/app/Services/Billing.php', 'original');

        $this->runCommand(MakeServiceCommand::class, ['name' => 'Billing']);

        $this->assertSame('original', $this->files->get($this->basePath.'/app/Services/Billing.php'));
    }
}
