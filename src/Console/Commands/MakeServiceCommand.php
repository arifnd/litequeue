<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

class MakeServiceCommand extends ScaffoldCommand
{
    protected $signature = 'make:service
        {name : The name of the service class}
        {--force : Overwrite the service if it already exists}
        {--path= : Base application path}';

    protected $description = 'Create a new service class';

    protected function type(): string
    {
        return 'Service';
    }

    protected function location(string $basePath): array
    {
        return ['App\\Services', $basePath.'/app/Services'];
    }

    protected function stub(): string
    {
        return 'service.stub';
    }
}
