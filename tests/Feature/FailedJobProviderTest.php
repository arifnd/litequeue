<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Failed\DatabaseFailedJobProvider;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class FailedJobProviderTest extends TestCase
{
    private DatabaseFailedJobProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        $this->provider = new DatabaseFailedJobProvider($this->app['db'], 'testing', 'failed_jobs');
    }

    private function log(string $uuid = 'uuid-1'): string|int|null
    {
        return $this->provider->log(
            'redis',
            'default',
            json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\Ping']),
            new RuntimeException('boom')
        );
    }

    public function test_it_logs_a_failed_job(): void
    {
        $id = $this->log();

        $this->assertSame('uuid-1', $id);
        $this->assertSame(1, $this->provider->count());
        $this->assertNotNull($this->provider->find(1));
    }

    public function test_it_forgets_a_failed_job(): void
    {
        $this->log();

        $this->assertTrue($this->provider->forget(1));
        $this->assertSame(0, $this->provider->count());
    }

    public function test_it_flushes_failed_jobs(): void
    {
        $this->log('uuid-a');
        $this->log('uuid-b');

        $this->provider->flush();

        $this->assertSame(0, $this->provider->count());
    }

    public function test_it_lists_failed_jobs(): void
    {
        $this->log();

        $this->assertCount(1, $this->provider->all());
    }
}
