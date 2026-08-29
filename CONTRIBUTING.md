# Contributing to whmcs-webpay

Thanks for contributing. This document captures the operational rules for
working on the `agenciaingenium/whmcs-webpay` repository.

## Branch protection on `main`

The `main` branch is protected. Direct pushes are rejected; the following
checks must pass before a PR can merge:

1. CI matrix on `7.4 / 8.1 / 8.2 / 8.3` is green.
2. PHPStan level 3 on `modules/gateways/webpaydirecto/lib` has no new errors.
3. The PR has at least one review approval from a CODEOWNER.
4. The PR title follows Conventional Commits.

If you are a maintainer configuring GitHub settings, mirror the policy above in
**Settings → Branches → Branch protection rules → main**.

## Local development

```bash
# Install PHP 7.4+ (any version from the matrix).
# Download PHPUnit once into the repo root:
wget -q -O phpunit.phar https://phar.phpunit.de/phpunit-9.phar

# Run the unit + integration suite:
php phpunit.phar -c phpunit.xml.dist

# Skip the smoke tests locally (they spin up a sandboxed HTTP server):
SKIP_SMOKE_TESTS=1 php phpunit.phar -c phpunit.xml.dist --testsuite integration

# Run PHPStan locally (level 3 on lib/):
wget -q -O phpstan.phar https://github.com/phpstan/phpstan/releases/download/1.10.67/phpstan.phar
php phpstan.phar analyse modules/gateways/webpaydirecto/lib --level=3 --no-progress
```

## Schema migrations

The gateway tables are created at request time via
`WebpayDirecto\TransactionStore::ensureTable()` for backwards compatibility,
but the canonical path is:

```bash
WHMCS_ROOT=/path/to/whmcs php bin/install.php
```

Run this script after deploying a new release so the schema is provisioned
explicitly rather than implicitly.

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short summary>

<body explaining the why>

Closes CLE-123
```

Common types: `feat`, `fix`, `chore`, `docs`, `test`, `refactor`, `ci`.
Common scopes: `callback`, `db`, `observability`, `ci`, `docs`.

## Releasing

1. Bump the version in `modules/gateways/webpaydirecto.php` (`ApiVersion`).
2. Move the `[Unreleased]` section of `CHANGELOG.md` into a dated release.
3. Tag the commit with `vX.Y.Z`.
4. Push the tag — `.github/workflows/release.yml` will draft the GitHub
   release with the changelog excerpt.

## Reporting issues

Use the appropriate template under `.github/ISSUE_TEMPLATE/`:

- `bug_report.md` for general defects.
- `feature_request.md` for enhancements.
- `payment_incident.md` for production payment failures (highest priority).
