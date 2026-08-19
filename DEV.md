# Developer Guide

## Setup

```bash
composer install
```

Copy `.env.example` to `.env` and fill in your sandbox credentials (required for integration tests):

```bash
cp .env.example .env
```

Required `.env` keys:

```
NAV2M2M_CLIENT_ID=
NAV2M2M_CLIENT_SECRET=
NAV2M2M_USER_TEMPORARY_API_KEY=   # format: username-password
NAV2M2M_USER_SIGNATUREKEY=
```

---

## Running Tests

### Unit tests only

```bash
composer test
```

This runs all tests under `tests/` via PHPUnit. The suite currently contains:

| File | Description |
|------|-------------|
| `tests/NavM2mTest.php` | Unit tests (no network calls) |
| `tests/SandboxIntegrationTest.php` | Integration tests against the NAV sandbox API (requires `.env`) |

### With HTML coverage report

```bash
composer test:coverage
```

Output is written to `coverage/index.html`. Requires Xdebug or PCOV.

### Watch mode (re-run on file change)

```bash
composer test:watch
```

Requires [`phpunit-watcher`](https://github.com/spatie/phpunit-watcher) to be installed globally or as a dev dependency.

---

## Releasing a New Version

Releases are automated via `release.sh`. The script:

1. Runs the full test suite — aborts on failure
2. Bumps the version in `composer.json`
3. Commits the change
4. Creates an annotated git tag with auto-generated release notes from conventional commits
5. Pushes the branch and tag to `origin/main`

Packagist picks up the new tag automatically via the GitHub webhook.

### Bump a patch version (`x.y.Z`)

```bash
composer release:patch
```

### Bump a minor version (`x.Y.0`)

```bash
composer release:minor
```

### Bump a major version (`X.0.0`)

```bash
sh ./release.sh major
```

> There is no `composer` shortcut for major releases to avoid accidental bumps.

### Commit message convention

Release notes are generated from commits between the previous tag and `HEAD`. Use [Conventional Commits](https://www.conventionalcommits.org/) so they are included:

```
feat(token): add refresh token support
fix(signature): correct timestamp format
```

Commits prefixed with `chore:` are excluded from release notes.
