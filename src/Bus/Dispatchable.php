<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Bus;

use Arifnd\LiteQueue\Facades\Bus;

trait Dispatchable
{
    public static function dispatch(...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments));
    }

    public static function dispatchIf($boolean, ...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments), (bool) $boolean);
    }

    public static function dispatchUnless($boolean, ...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments), ! $boolean);
    }

    public static function dispatchSync(...$arguments): mixed
    {
        return Bus::dispatchSync(new static(...$arguments));
    }

    public static function dispatchAfterResponse(...$arguments): void
    {
        Bus::dispatchAfterResponse(new static(...$arguments));
    }
}
