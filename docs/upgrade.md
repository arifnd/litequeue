# Upgrade Guide

LiteQueue follows [Semantic Versioning](https://semver.org). See
[versioning.md](versioning.md) for the release plan.

## Unreleased (0.1.0)

Initial development release.

- Standalone `lq` CLI (`make:job`, `work`, `supervisor`, failed-job commands).
- Drivers: `litequeue_redis`, `sync`, `null`.
- Worker with retries, backoff, events, and failed jobs.
- Horizon-compatible supervisor state emulation.
- Supported: PHP 8.2–8.5, Laravel 12–13.

### Breaking changes

None yet.
