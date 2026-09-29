<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final class WithoutRelations {}
