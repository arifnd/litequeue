# ADR 0001 — Horizon & Illuminate Interop Strategy

- **Status:** accepted
- **Date:** 2026-09-29
- **Deciders:** arifnd
- **Context task:** 00 (amended)

## Context

LiteQueue (`arifnd/litequeue`) reimplements a Laravel queue core and ships a standalone `lq`
runner so it can operate **without a full Laravel application**. It must interoperate with
Laravel's queue system and with **Laravel Horizon**, which may be installed in the *same* or a
*different* project. Horizon's supervisors are config-driven and its dashboard reads supervisor
state from Redis, so appearing in Horizon requires writing that state. We freeze the
compatibility surface here before implementing.

## Decision

1. **Interfaces, not forks.** LiteQueue depends on `illuminate/*` components (contracts,
   support, console, container, events, pipeline, redis, database, filesystem, queue) but owns
   its queue implementation. Supported Laravel: `^12 || ^13`.
2. **Standalone runner.** The `lq` binary boots a thin container (no Laravel kernel, no
   `php artisan`) and exposes commands such as `make:job` and `work`.
3. **Horizon is optional.** LiteQueue never requires Horizon. When Horizon is present, LiteQueue
   can appear in its dashboard as a supervisor.
4. **Own config, no `horizon.php` mutation.** LiteQueue's supervisor configuration lives in
   `config/litequeue.php` using **Horizon-shaped keys**. LiteQueue must not read or write
   `config/horizon.php`, so the two can later coexist in one project.
5. **Freeze the Redis queue schema to Laravel's `RedisQueue` layout** (below). Non-negotiable
   for Horizon and for cross-consumption with `Illuminate\Queue\RedisQueue`.
6. **Freeze the Horizon supervisor state schema** (below). LiteQueue's standalone supervisor
   writes Horizon-compatible `supervisors`/`masters` records into the shared Redis (same host +
   prefix) so a Horizon dashboard in another project lists it. v1 emulates **presence and
   status**; recording per-job history into Horizon's job repository is a follow-up.
7. **Freeze the event surface** to Laravel's `Illuminate\Queue\Events\*` names and constructor
   shapes so Horizon listeners and third-party code accept them.
8. **Freeze the job payload shape** to `Queue::createPayloadArray()` output for Laravel 12/13.

## Frozen Redis queue schema (Laravel `RedisQueue`)

Given queue name `Q` and connection prefix `P` (e.g. `laravel_database_`):

| Key | Type | Meaning |
| --- | --- | --- |
| `Pqueues:Q` | list | pending jobs — **RPUSH** to push, **LPOP** to pop |
| `Pqueues:Q:delayed` | zset | delayed jobs, score = available-at (unix seconds) |
| `Pqueues:Q:reserved` | zset | in-flight jobs, score = reserve deadline; member = the **JSON payload** with incremented `attempts` |
| `Pqueues:Q:notify` | list/pub-sub | wake blocked workers on push |

There are **no** `:migrate` or `:retry` keys (an earlier draft of this ADR was incorrect).
Expired reserved jobs are migrated back by score using `retry_after` (default 90s).

## Frozen Horizon supervisor state schema

Written on the **Horizon Redis prefix** (same host/prefix as Horizon):
`supervisors` (zset, score = heartbeat), `supervisor:{name}` (hash: `name`, `master`, `pid`,
`status`, `processes` JSON, `options` JSON, TTL 30s), `masters` (zset), `master:{name}` (hash:
`name`, `environment`, `pid`, `status`, `supervisors` JSON, TTL 15s). Supervisor names follow
`{master}:{supervisor}`. Heartbeat is refreshed every loop (~1s).

## Frozen event surface

`JobPopping`, `JobPopped`, `JobProcessing`, `JobProcessed`, `JobAttempted`, `JobFailed`,
`JobExceptionOccurred`, `JobReleasedAfterException`, `JobRetryRequested`, `JobTimedOut`,
`WorkerStopping`, `QueueBusy`, `Looping`.

## Compatibility matrix

| Feature | v1 status | Notes |
| --- | --- | --- |
| `dispatch()` / Bus | Supported | Own dispatcher bound to Illuminate contracts. |
| `ShouldQueue`, `SerializesModels` | Supported | Reimplemented, Eloquent-aware. |
| Redis driver | Supported | Schema-identical to Laravel. |
| sync / null drivers | Supported | Reference implementations. |
| Failed jobs (`failed_jobs`) | Supported | Schema-identical. |
| `lq work` worker | Supported | Own worker; signal + timeout aware. |
| Horizon supervisor listing | Supported | Emulated state on shared Redis. |
| Horizon metrics / waits | Supported | Standard queue schema + `creationTimeOfOldestPendingJob`. |
| Horizon recent/completed job history | Deferred | Jobs run remotely; needs job-repository writes. |
| Config in same project as Horizon | Supported | LiteQueue uses its own namespace only. |
| Batches | Deferred | Chains/unique jobs in scope. |
| SQS / database queue drivers | Not in v1 | Later MINOR. |

## Consequences

- LiteQueue runs standalone and still shows up in a remote Horizon dashboard.
- The queue schema, supervisor state schema, event surface and payload shape are public
  contracts: changing them is a MAJOR bump.
- Discoverability of Illuminate classes remains conditional (`class_exists`).

## Alternatives considered

- **Fork `illuminate/queue`.** Rejected: heavy, defeats "lightweight".
- **Require full Laravel + Horizon.** Rejected: contradicts "run without a Laravel project".
- **Mutate `config/horizon.php` from LiteQueue.** Rejected: conflicts on co-installation.
- **Invent a new payload/schema.** Rejected: breaks Horizon and external tooling.
