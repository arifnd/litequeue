<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class ConsoleContainer extends Container implements Application
{
    public function version(): string
    {
        return LiteQueueConsole::version();
    }

    public function basePath($path = ''): string
    {
        $base = (string) ($this['litequeue.base_path'] ?? getcwd() ?: '');

        return $path === '' ? $base : rtrim($base, '/').'/'.ltrim($path, '/');
    }

    public function bootstrapPath($path = ''): string
    {
        return $this->basePath('bootstrap/'.$this->segments($path));
    }

    public function configPath($path = ''): string
    {
        return $this->basePath('config/'.$this->segments($path));
    }

    public function databasePath($path = ''): string
    {
        return $this->basePath('database/'.$this->segments($path));
    }

    public function langPath($path = ''): string
    {
        return $this->basePath('lang/'.$this->segments($path));
    }

    public function publicPath($path = ''): string
    {
        return $this->basePath('public/'.$this->segments($path));
    }

    public function resourcePath($path = ''): string
    {
        return $this->basePath('resources/'.$this->segments($path));
    }

    public function storagePath($path = ''): string
    {
        return $this->basePath('storage/'.$this->segments($path));
    }

    public function environment(...$environments): string|bool
    {
        $current = (string) (getenv('APP_ENV') ?: 'production');

        if ($environments === []) {
            return $current;
        }

        foreach ($environments as $environment) {
            foreach ((array) $environment as $pattern) {
                if (is_string($pattern) && Str::is($pattern, $current)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function runningInConsole(): bool
    {
        return true;
    }

    public function runningUnitTests(): bool
    {
        return false;
    }

    public function hasDebugModeEnabled(): bool
    {
        return false;
    }

    public function maintenanceMode(): never
    {
        throw new RuntimeException('Maintenance mode is not supported by the standalone runner.');
    }

    public function isDownForMaintenance(): bool
    {
        return false;
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function registerConfiguredProviders(): void {}

    public function register($provider, $force = false): ServiceProvider
    {
        throw new RuntimeException('Service providers are not supported by the standalone runner.');
    }

    public function registerDeferredProvider($provider, $service = null): void {}

    public function resolveProvider($provider): ServiceProvider
    {
        throw new RuntimeException('Service providers are not supported by the standalone runner.');
    }

    public function boot(): void {}

    public function booting($callback): void {}

    public function booted($callback): void {}

    public function bootstrapWith(array $bootstrappers): void {}

    public function getLocale(): string
    {
        return 'en';
    }

    public function getNamespace(): string
    {
        return 'App';
    }

    public function getProviders($provider): array
    {
        return [];
    }

    public function hasBeenBootstrapped(): bool
    {
        return false;
    }

    public function loadDeferredProviders(): void {}

    public function setLocale($locale): void {}

    public function shouldSkipMiddleware(): bool
    {
        return false;
    }

    public function terminating($callback): static
    {
        return $this;
    }

    public function terminate(): void {}

    private function segments(mixed $path): string
    {
        return is_string($path) ? ltrim($path, '/') : '';
    }
}
