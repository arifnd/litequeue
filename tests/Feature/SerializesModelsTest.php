<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Feature;

use Arifnd\LiteQueue\Tests\Fixtures\TestJob;
use Arifnd\LiteQueue\Tests\Fixtures\User;
use Arifnd\LiteQueue\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class SerializesModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function test_model_is_stored_as_identifier_and_restored(): void
    {
        $user = User::create(['name' => 'Arif']);

        $serialized = serialize(new TestJob('hi', $user));

        $this->assertStringContainsString('ModelIdentifier', $serialized);

        $job = unserialize($serialized);

        $this->assertInstanceOf(User::class, $job->model);
        $this->assertSame($user->id, $job->model->id);
        $this->assertSame('Arif', $job->model->name);
    }

    public function test_scalar_properties_are_preserved(): void
    {
        $job = unserialize(serialize(new TestJob('payload')));

        $this->assertSame('payload', $job->message);
    }
}
