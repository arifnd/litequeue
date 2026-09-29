<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\LiteQueueServiceProvider;
use Arifnd\LiteQueue\Tests\TestCase;

final class ScaffoldingTest extends TestCase
{
    public function test_service_provider_class_exists(): void
    {
        $this->assertTrue(class_exists(LiteQueueServiceProvider::class));
    }

    public function test_service_provider_is_loaded(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(LiteQueueServiceProvider::class));
    }
}
