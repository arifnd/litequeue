<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Console\Bootstrap\RegisterRedis;
use Arifnd\LiteQueue\Console\ExceptionHandler;
use Arifnd\LiteQueue\Console\LiteQueueConsole;
use Arifnd\LiteQueue\Queue\PayloadFactory;
use Arifnd\LiteQueue\Queue\QueueManager;
use Arifnd\LiteQueue\Queue\SyncQueue;
use Arifnd\LiteQueue\Tests\Fixtures\TestJob;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

final class ConsoleBootstrapTest extends ConsoleTestCase
{
    public function test_it_resolves_the_default_connection(): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);

        $manager = $container->make(QueueManager::class);

        $this->assertInstanceOf(SyncQueue::class, $manager->connection());
    }

    public function test_it_binds_a_console_exception_handler(): void
    {
        $container = LiteQueueConsole::bootstrap($this->basePath);

        $this->assertInstanceOf(ExceptionHandler::class, $container->make(ExceptionHandlerContract::class));
    }

    public function test_registers_a_horizon_connection_with_the_configured_prefix(): void
    {
        $method = new ReflectionMethod(RegisterRedis::class, 'registerHorizonConnection');

        $redis = [
            'default' => ['host' => '127.0.0.1', 'port' => 6379],
            'options' => ['prefix' => 'laravel_database_'],
        ];
        $config = ['supervisor' => ['horizon' => [
            'redis_connection' => 'horizon',
            'prefix' => 'myapp_horizon:',
        ]]];

        $result = $method->invoke(null, $redis, $config);

        $this->assertSame('myapp_horizon:', $result['horizon']['options']['prefix']);
        $this->assertSame('127.0.0.1', $result['horizon']['host']);
        $this->assertSame('laravel_database_', $result['options']['prefix']);
    }

    public function test_it_keeps_an_existing_horizon_connection(): void
    {
        $method = new ReflectionMethod(RegisterRedis::class, 'registerHorizonConnection');

        $redis = [
            'default' => ['host' => '127.0.0.1'],
            'horizon' => ['host' => '10.0.0.9', 'options' => ['prefix' => 'custom:']],
        ];

        $result = $method->invoke(null, $redis, []);

        $this->assertSame('10.0.0.9', $result['horizon']['host']);
    }

    public function test_it_is_reentrant_and_reset_restores_global_state(): void
    {
        $first = LiteQueueConsole::bootstrap($this->basePath);
        $second = LiteQueueConsole::bootstrap($this->basePath);

        $this->assertNotSame($first, $second);
        $this->assertSame($second, Container::getInstance());

        LiteQueueConsole::reset();

        $this->assertSame($this->app, Container::getInstance());
    }

    public function test_reset_clears_the_payload_creation_callback(): void
    {
        PayloadFactory::createPayloadUsing(fn (string $queue, array $payload): array => array_merge($payload, ['injected' => true]));

        $factory = new PayloadFactory($this->app);
        $this->assertArrayHasKey('injected', $factory->makeArray(new TestJob, 'default'));

        LiteQueueConsole::reset();

        $this->assertArrayNotHasKey('injected', $factory->makeArray(new TestJob, 'default'));
    }

    public function test_it_boots_eloquent_and_supports_model_crud(): void
    {
        $this->writeInMemoryDatabase();

        $container = LiteQueueConsole::bootstrap($this->basePath);

        $this->assertInstanceOf(DatabaseManager::class, $container->make('db'));

        $container->make('db')->connection()->getSchemaBuilder()->create('widgets', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $model = new class extends Model
        {
            protected $table = 'widgets';

            protected $guarded = [];
        };

        $model->newQuery()->create(['name' => 'alpha']);

        $this->assertSame('alpha', $model->newQuery()->first()->name);
    }

    public function test_the_db_facade_and_query_builder_work_standalone(): void
    {
        $this->writeInMemoryDatabase();

        LiteQueueConsole::bootstrap($this->basePath);

        DB::connection()->getSchemaBuilder()->create('widgets', function ($table): void {
            $table->id();
            $table->string('name');
        });

        DB::table('widgets')->insert(['name' => 'alpha']);

        $this->assertSame('alpha', DB::table('widgets')->value('name'));
    }

    public function test_it_resolves_relative_sqlite_paths_against_the_base_path(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/config');
        $this->files->put($this->basePath.'/config/database.php', <<<'PHP'
        <?php

        return [
            'default' => 'testing',
            'connections' => [
                'testing' => ['driver' => 'sqlite', 'database' => 'database/app.sqlite', 'prefix' => ''],
            ],
        ];
        PHP);

        $container = LiteQueueConsole::bootstrap($this->basePath);

        $this->assertSame(
            $this->basePath.'/database/app.sqlite',
            $container['config']['database.connections.testing.database']
        );
    }

    private function writeInMemoryDatabase(): void
    {
        $this->files->ensureDirectoryExists($this->basePath.'/config');
        $this->files->put($this->basePath.'/config/database.php', <<<'PHP'
        <?php

        return [
            'default' => 'testing',
            'connections' => [
                'testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            ],
        ];
        PHP);
    }
}
