# LiteQueue — Release Versioning Plan

Plan for introducing semantic-versioned releases (`vX.Y.Z`) for `arifnd/litequeue`, driven by
git tags and published to Packagist. This is a **plan**, not yet implemented.

## Goals

- Deterministic, reproducible releases consumers can pin (`^1.2`).
- One source of truth for version = **git tag** (no hand-edited version constants).
- Automated changelog + GitHub Release generation from commit history.
- Clear breaking-change policy and support window.
- Low-maintenance release flow that works without paid tooling.

## Versioning policy (SemVer)

| Change | Bump | Examples |
| --- | --- | --- |
| Breaking change | **MAJOR** | Change `Queue` contract signature, drop a driver, rename config keys, raise min PHP/Laravel. |
| New feature, backwards compatible | **MINOR** | New driver, new command, new job option, new event. |
| Bugfix / internal | **PATCH** | Worker fix, payload fix, docs, deps bump within range. |

### Package-specific bump rules

- **MAJOR** when: min PHP or Laravel support is raised; a public API (`Arifnd\LiteQueue\*`)
  becomes incompatible; the Redis schema changes in a way old/new workers can't share;
  a command is renamed/removed; a config key is removed.
- **MINOR** when: new config key with a safe default; new command; new driver; new optional
  job attribute; adding (not changing) a contract method with a default.
- **PATCH** when: no public surface change.

### Pre-1.0 (`0.y.z`)

- Start at `0.1.0`. While `0.x`, MINOR may contain breaking changes — document them loudly in
  the changelog. Stabilize to `1.0.0` at milestone M6.
- `0.9.x` is the stabilisation line: only deprecations and fixes before `1.0.0`.

### Support window

- Latest MINOR of the latest MAJOR: full support.
- Previous MAJOR: security/critical bugfixes only, until the next MAJOR ships +90 days.
- Document the matrix in `README.md`.

## Source of truth & branches

| Item | Decision |
| --- | --- |
| Version source | Git tag `vX.Y.Z` (annotated) |
| Default branch | `main` (unreleased work) |
| Release branches | `release/x.y` for patching older lines |
| Hotfix branches | `hotfix/x.y.z` branched from the release tag |
| Composer constraint | Consumers pin `^X.Y`; docs recommend minor-pinning for `0.x` |

No `Version::VERSION` constant is required for release versioning. If an in-package version is
wanted later, it is derived from Composer's installed package metadata, not a manual constant.

## Commit convention (feeds the changelog)

Conventional Commits, already the project convention:

```
feat: add redis blocking pop
fix: release reserved job on timeout
perf: reuse lua script sha
docs: document horizon setup
chore: bump pint
feat!: drop PHP 8.2 support          # ! or BREAKING CHANGE: => MAJOR
```

Commit messages must **not** reference task numbers (per project directive). Type + scope is
enough for changelog grouping.

## Changelog

- File: `CHANGELOG.md`, [Keep a Changelog](https://keepachangelog.com/) format.
- Generated from commits at release time with **git-cliff** (single binary, no Node needed).
  Alternative: `release-drafter` (GitHub Action).
- Configuration: `cliff.toml` mapping `feat → Added`, `fix → Fixed`, `perf → Changed`,
  `docs → Documentation`, breaking → `### Breaking Changes` with migration notes.
- `Unreleased` section is maintained by automation; each release moves it under `X.Y.Z — date`.

## Release process

### Normal release (from `main`)

1. CI green on `main`; versioning test suite passes.
2. Freeze: open a PR titled `release: vX.Y.Z` (changelog only).
3. Run the release script (below) → updates `CHANGELOG.md`, commits, creates annotated tag.
4. Push tag → GitHub Action creates the Release with notes; Packagist webhook updates.
5. Announce (release notes / discussions).

### Automated steps

- `.github/workflows/release.yml` triggers on `v*` tag push:
  - verify tag matches `composer.json` constraints and CHANGELOG section exists,
  - create GitHub Release (notes from changelog),
  - Packagist picks it up via its GitHub webhook (no API key needed).
- Optional `version.yml`: a `workflow_dispatch` that computes next version from commits and
  opens the release PR with the changelog diff.

### Hotfix

1. Branch `hotfix/x.y.z` from the affected tag.
2. Fix + test; cherry-pick to `main` if still relevant.
3. Tag `vX.Y.(Z+1)`, same release automation.

## Planned files

| File | Purpose |
| --- | --- |
| `CHANGELOG.md` | Version history (Keep a Changelog). |
| `cliff.toml` | git-cliff config (commit → changelog grouping). |
| `.github/workflows/release.yml` | On tag: verify + create GitHub Release. |
| `.github/workflows/version.yml` | Optional: compute next version, open release PR. |
| `docs/releasing.md` | Maintainer runbook (below, extended with examples). |
| `docs/upgrade.md` | Per-MAJOR migration notes (also linked from changelog). |

## Release script (candidate)

`composer release X.Y.Z` (or a small `bin/release` PHP script) that:

1. Asserts clean working tree and `main`.
2. Runs `composer check` (format, analyse, tests) — must pass.
3. Runs git-cliff to write the `X.Y.Z` section into `CHANGELOG.md`.
4. Commits `chore(release): vX.Y.Z` (message contains no task numbers).
5. Creates annotated tag `vX.Y.Z` with the changelog excerpt.
6. Prints the `git push origin main --follow-tags` command (does not auto-push).

## Compatibility matrix

Maintain and bump deliberately; each row is a MINOR/MAJOR decision:

| LiteQueue | PHP | Laravel | Horizon | Drivers |
| --- | --- | --- | --- | --- |
| 0.1.x | 8.2–8.5 | 12–13 | optional (n/a, 5.x, 6.x) | redis, sync, null |
| 1.0.x | 8.2–8.5 | 12–13 | optional (n/a, 5.x, 6.x) | redis, sync, null |

Horizon is optional: LiteQueue runs standalone (`lq work`) or as a supervisor that appears in a
Horizon dashboard (same or another project). Horizon's exact supported majors are verified in
`task-16`; Laravel 13 support is "allowed if present" (`^12 || ^13`).

## Deprecation policy

- Deprecate in a MINOR, remove in the next MAJOR.
- Use `trigger_error(E_USER_DEPRECATED)` behind a `config('litequeue.deprecations')` toggle
  where a runtime signal is useful.
- Every deprecation: changelog entry under `Deprecated` + upgrade-guide entry with the
  replacement API.

## Acceptance criteria for this plan

- [ ] Policy approved (bump rules, pre-1.0, support window).
- [ ] Tooling chosen (git-cliff confirmed) and `cliff.toml` designed.
- [ ] Release workflow design reviewed (tag → release → Packagist path).
- [ ] `docs/releasing.md` runbook drafted.
- [ ] `CHANGELOG.md` seeded at `0.1.0`.

## Open questions

1. Use git-cliff or release-drafter? (git-cliff chosen tentatively; no Node dependency.)
2. Auto-tag on merge to `main`, or manual tag only? (Tentatively manual tag.)
3. Package `composer.json` `version` field: omit (let VCS drive) — confirm no internal tool
   needs it.
4. Do we ship a `litequeue:version` command? Deferred; not needed for release versioning.
