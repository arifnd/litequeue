# Changelog

All notable changes to LiteQueue are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Standalone `lq` CLI: `make:job`, `work`, `supervisor`, `install`, `failed`, `retry`, `forget`,
  `flush`.
- Queue manager with `litequeue_redis`, `sync` and `null` drivers.
- Laravel-compatible job payloads and `SerializesModels` support.
- Dispatcher, `PendingDispatch`, `Dispatchable`/`Queueable` traits, and `dispatch()` helpers.
- Worker with retries, backoff, max exceptions, signals and events.
- Failed-job providers: database and null, plus a `failed_jobs` migration.
- Unique-job locking.
- Horizon-compatible supervisor state emulation on shared Redis.
- Testing fakes: `QueueFake`, `JobFake`.
- Supported: PHP 8.2–8.4, Laravel 11–13.

[Unreleased]: https://github.com/arifnd/litequeue/commits/main
