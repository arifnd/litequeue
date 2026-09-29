<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Exceptions;

use RuntimeException;

class InvalidPayloadException extends RuntimeException
{
    public function __construct(string $message = '', public readonly mixed $value = null)
    {
        parent::__construct($message);
    }
}
