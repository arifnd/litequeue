<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console\Commands;

class MakeTraitCommand extends ScaffoldCommand
{
    protected $signature = 'make:trait
        {name : The name of the trait}
        {--force : Overwrite the trait if it already exists}
        {--path= : Base application path}';

    protected $description = 'Create a new trait';

    protected function type(): string
    {
        return 'Trait';
    }

    protected function location(string $basePath): array
    {
        return match (true) {
            is_dir($basePath.'/app/Concerns') => ['App\\Concerns', $basePath.'/app/Concerns'],
            is_dir($basePath.'/app/Traits') => ['App\\Traits', $basePath.'/app/Traits'],
            default => ['App', $basePath.'/app'],
        };
    }

    protected function stub(): string
    {
        return 'trait.stub';
    }
}
