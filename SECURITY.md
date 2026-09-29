# Security Policy

## Supported versions

| Version | Supported | PHP | Laravel |
| --- | --- | --- | --- |
| `0.1.x` | ✅ | 8.2–8.5 | 12–13 |
| `< 0.1` | ❌ | — | — |

## Reporting a vulnerability

Please report suspected vulnerabilities privately using GitHub's
**Report a vulnerability** / security advisory form on the package repository. Do not open a public
issue.

Include: affected version, a description, reproduction steps, and impact. You can expect an
acknowledgement within a few days and a fix or mitigation as soon as is practical. Please allow time
for a release before public disclosure.

## Trust model

LiteQueue processes **serialized PHP objects** from the queue and writes queue/supervisor state to
Redis. Treat the following as trusted infrastructure:

- **Redis** is a trusted boundary. Queue payloads contain serialized job classes and are
  deserialized by the worker (`Jobs/CallQueuedHandler`). Anyone able to write to the queue Redis can
  cause arbitrary PHP class instantiation, so Redis must be protected with authentication, a private
  network, TLS, or all three. Never expose it to untrusted networks.
- **Configuration files** (`config/litequeue.php`, `config/database.php`) are PHP and are executed.
  Only load them from directories the application controls.
- **Environment variables** (`REDIS_*`, `DB_*`) and, for the standalone runner, the current working
  directory are trusted inputs.

### Hardening recommendations

- Enable authentication and TLS on Redis and avoid sharing it with untrusted workloads.
- Enable Redis key prefixes per environment to avoid cross-environment key collisions.
- For jobs that carry sensitive data, implement `ShouldBeEncrypted` on the job so the command is
  encrypted with the application key before it is written to Redis.
- Run the worker as an unprivileged user and keep the process manager's `stopwaitsecs`/grace period
  longer than the longest job timeout.

### Optional payload signing

Deployments that need integrity verification beyond network isolation can enable payload signing
via `litequeue.security.sign_payloads` (env `LITEQUEUE_SIGN_PAYLOADS`). When enabled, the serialized
command is signed with an HMAC-SHA256 using `litequeue.security.signing_key` (defaults to
`APP_KEY`) and the worker rejects payloads whose signature does not match. Signing is **off** by
default to stay byte-compatible with Laravel/Horizon; every producer and consumer sharing a queue
must use the same setting and key.

## Dependency auditing

CI runs `composer audit` on every push and pull request. Dependency version updates are proposed by
Dependabot. Please report any advisory that is not caught automatically.
