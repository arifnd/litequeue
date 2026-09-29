# Testing

LiteQueue ships fakes so application tests can assert dispatch behaviour without a broker.

```php
use Arifnd\LiteQueue\Testing\QueueFake;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

$fake = new QueueFake;
app()->instance(QueueFactory::class, $fake);

SendEmail::dispatch($user->id);

$fake->assertPushed(SendEmail::class);
$fake->assertPushed(SendEmail::class, fn (SendEmail $job) => $job->userId === $user->id);
$fake->assertPushedOn('emails', SendEmail::class);
$fake->assertPushedTimes(SendEmail::class, 1);
$fake->assertNotPushed(OtherJob::class);
$fake->assertNothingPushed();
```

`Arifnd\LiteQueue\Testing\JobFake` is a no-op `Illuminate\Contracts\Queue\Job` for worker tests.

## Running the LiteQueue test suite

```bash
composer check     # format + static analysis + tests
```

Redis integration tests skip automatically when no Redis server is reachable.
