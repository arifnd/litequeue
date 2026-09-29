<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Contracts;

interface QueueDriver
{
    public function getConnectionName(): ?string;

    public function setConnectionName(?string $name): static;
}
