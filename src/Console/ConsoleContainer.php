<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

use Illuminate\Container\Container;

class ConsoleContainer extends Container
{
    public function runningUnitTests(): bool
    {
        return false;
    }

    public function runningInConsole(): bool
    {
        return true;
    }

    public function environment(): string
    {
        return (string) (getenv('APP_ENV') ?: 'production');
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }
}
