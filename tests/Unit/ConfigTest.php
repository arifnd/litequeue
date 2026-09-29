<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private function app(array $items): Container
    {
        $container = new Container;
        $container->instance('config', new Repository($items));

        return $container;
    }

    public function test_it_reads_the_laravel_namespaced_shape(): void
    {
        $app = $this->app(['litequeue' => ['default' => 'redis', 'failed' => ['driver' => 'database']]]);

        $this->assertSame('redis', Config::get($app, 'default'));
        $this->assertSame(['driver' => 'database'], Config::get($app, 'failed'));
    }

    public function test_it_reads_the_standalone_top_level_shape(): void
    {
        $app = $this->app(['default' => 'sync', 'supervisor' => ['name' => 'litequeue']]);

        $this->assertSame('sync', Config::get($app, 'default'));
        $this->assertSame('litequeue', Config::get($app, 'supervisor.name'));
    }

    public function test_it_prefers_the_namespaced_shape(): void
    {
        $app = $this->app(['litequeue' => ['default' => 'redis'], 'default' => 'sync']);

        $this->assertSame('redis', Config::get($app, 'default'));
    }

    public function test_it_falls_back_to_the_default(): void
    {
        $this->assertSame('fallback', Config::get($this->app([]), 'missing', 'fallback'));
    }
}
