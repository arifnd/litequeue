<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

use Illuminate\Container\Container;
use Symfony\Component\Console\Application;

class LiteQueueConsole
{
    /**
     * @param  array<int, string>|null  $argv
     */
    public static function run(?array $argv = null): int
    {
        $container = Kernel::bootstrap();

        $application = new Application('LiteQueue', static::version());

        foreach (static::commands() as $command) {
            $instance = new $command;
            $instance->setLaravel($container);
            $application->addCommand($instance);
        }

        return $application->run();
    }

    public static function bootstrap(?string $basePath = null): Container
    {
        return Kernel::bootstrap($basePath);
    }

    public static function reset(): void
    {
        Kernel::reset();
    }

    public static function version(): string
    {
        return '0.1.0';
    }

    /**
     * @return array<int, class-string>
     */
    protected static function commands(): array
    {
        return [
            Commands\MakeJobCommand::class,
            Commands\MakeServiceCommand::class,
            Commands\MakeTraitCommand::class,
            Commands\WorkCommand::class,
            Commands\SupervisorCommand::class,
            Commands\FailedCommand::class,
            Commands\RetryCommand::class,
            Commands\ForgetCommand::class,
            Commands\FlushCommand::class,
            Commands\InstallCommand::class,
        ];
    }
}
