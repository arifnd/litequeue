<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Supervisor;

use Arifnd\LiteQueue\Supervisor\Contracts\SupervisorStore;

class HorizonStateRepository
{
    public function __construct(
        protected SupervisorStore $store,
        protected int $supervisorTtl = 30,
        protected int $masterTtl = 15,
    ) {}

    public function update(SupervisorState $state, ?int $now = null): void
    {
        $now ??= time();

        $this->store->put('supervisor:'.$state->name, $state->supervisorHash());
        $this->store->addToSet('supervisors', $now, $state->name);
        $this->store->expire('supervisor:'.$state->name, $this->supervisorTtl);

        $this->store->put('master:'.$state->master, $state->masterHash());
        $this->store->addToSet('masters', $now, $state->master);
        $this->store->expire('master:'.$state->master, $this->masterTtl);
    }

    public function forget(SupervisorState $state): void
    {
        $this->store->remove('supervisor:'.$state->name);
        $this->store->removeFromSet('supervisors', [$state->name]);

        $this->store->remove('master:'.$state->master);
        $this->store->removeFromSet('masters', [$state->master]);
    }
}
