# Contributing

Thanks for considering a contribution to LiteQueue.

## Setup

```bash
composer install
```

## Checks

Run the full gate before opening a pull request:

```bash
composer check   # pint --test + phpstan + phpunit
```

Individual commands:

```bash
composer format  # fix code style
composer analyse # static analysis
composer test    # test suite
```

## Guidelines

- One class per file, PSR-4, `declare(strict_types=1);`.
- Add no comments unless they explain non-obvious behaviour.
- Public APIs should accept/return `Illuminate\Contracts\Queue\*` types where one exists.
- Add tests for new behaviour; Redis integration tests must skip when Redis is unavailable.
- Use [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `test:`,
  `chore:`, `docs:`). Do not reference internal task numbers in commit messages.

## Pull requests

- Keep PRs focused; describe the change and how it was verified.
- Update `CHANGELOG.md` under `Unreleased`.
- Ensure CI is green across the PHP/Laravel matrix.
