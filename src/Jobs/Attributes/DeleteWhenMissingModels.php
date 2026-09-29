<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Jobs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class DeleteWhenMissingModels {}
