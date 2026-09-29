# Jobs

## Generating a job

```bash
lq make:job SendEmail
lq make:job SendEmail --sync
lq make:job SendEmail --once --test
```

The default queued stub uses LiteQueue's `Dispatchable` and `Queueable` traits:

```php
<?php

namespace App\Jobs;

use Arifnd\LiteQueue\Bus\Dispatchable;
use Arifnd\LiteQueue\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmail implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        //
    }
}
```

## Dispatching

```php
SendEmail::dispatch($user->id);
SendEmail::dispatch($user->id)->onConnection('litequeue')->onQueue('emails')->delay(60);
SendEmail::dispatchIf($should, $user->id);
SendEmail::dispatchSync($user->id);
```

Helpers and the Bus facade are also available:

```php
use function Arifnd\LiteQueue\dispatch;
use Arifnd\LiteQueue\Facades\Bus;

dispatch(new SendEmail($id));
Bus::dispatchSync(new SendEmail($id));
```

## Job middleware

```php
public function middleware(): array
{
    return [new MyJobMiddleware];
}
```

## Unique jobs

Implement `Illuminate\Contracts\Queue\ShouldBeUnique` and a `uniqueId()` method. LiteQueue holds a
cache lock while the job is queued and releases it when the job completes or fails.

## Serializing models

Use `Arifnd\LiteQueue\Queue\SerializesModels` (Eloquent models are stored as identifiers and
restored on execution).

## Retries, timeout and backoff

```php
public int $tries = 3;
public int $timeout = 60;
public array $backoff = [1, 5, 10];
```

Or fluently:

```php
SendEmail::dispatch($id)->delay(10); // delay only
```
