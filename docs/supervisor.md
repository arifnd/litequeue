# Supervisor & Horizon

LiteQueue can appear as a **Horizon supervisor**. It uses its own configuration and never edits
`config/horizon.php`, and it emulates Horizon's Redis supervisor state so a dashboard — even in
another project — can list it.

## Run

```bash
lq supervisor redis
lq supervisor redis --queue=default --name=litequeue --max-jobs=0
lq supervisor --dry-run          # in-memory state, no Redis
```

## Requirements

- The same Redis host and database as Horizon.
- The same Horizon **prefix** as Horizon. LiteQueue derives it the same way Horizon does:

  ```
  env('HORIZON_PREFIX') ?: Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
  ```

  So for `APP_NAME=Laravel` the prefix is `laravel_horizon:`. Override it with `HORIZON_PREFIX`
  or `litequeue.supervisor.horizon.prefix`.

Supervisor state is written through a dedicated `horizon` Redis connection that carries the Horizon
prefix, so it stays independent of the queue connection prefix (`REDIS_PREFIX`). You can point it
elsewhere with `litequeue.supervisor.horizon.redis_connection`.

## What is written

| Key | Type | Fields |
| --- | --- | --- |
| `supervisors` | zset | member = supervisor name, score = heartbeat |
| `supervisor:{name}` | hash | `name`, `master`, `pid`, `status`, `processes`, `options` (TTL 30s) |
| `masters` | zset | member = master name, score = heartbeat |
| `master:{name}` | hash | `name`, `environment`, `pid`, `status`, `supervisors` (TTL 15s) |

Supervisor names follow `{master}:{supervisor}` (e.g. `my-host-a1b2:litequeue`). The state is
refreshed on every loop (Horizon treats a supervisor as alive within ~29s; master ~14s).

## Scope

v1 emulates **supervisor/master presence and status**. Horizon's per-job history and metrics for
jobs processed remotely are a follow-up (jobs run in the LiteQueue process, not Horizon's).

## Notes

- Multiple supervisors of the same master name are not merged; each `lq supervisor` process uses
  a generated master name unless configured.
- If Horizon is installed in the **same** project, you can also point a Horizon supervisor at a
  LiteQueue connection; LiteQueue's driver is registered with `Queue::extend('litequeue_redis')`.
