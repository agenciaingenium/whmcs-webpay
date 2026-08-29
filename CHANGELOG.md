# Changelog

All notable changes to **whmcs-webpay** are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Timed HMAC signature validation for callback (`X-Clevers-Timestamp` header,
  configurable replay window via `callbackReplayWindow`). Closes CLE-82,
  CLE-88, CLE-106.
- Per-token rate limiting for callback, configurable via `callbackRateLimitMax`
  and `callbackRateLimitWindow`. Closes CLE-132.
- Explicit schema installer `bin/install.php` exposing
  `WebpayDirecto\TransactionStore::install()` as the canonical entrypoint for
  provisioning the gateway tables. Closes CLE-89.
- Structured logger helper `PaymentProcessor::logStructured($severity, $event,
  $context)` with `debug|info|warn|error` severity routing. Closes CLE-83.
- `TransbankClientInterface` so the create/commit flow can be exercised with
  end-to-end mocks. Closes CLE-80, CLE-142.
- New tests:
  - `tests/Unit/RateLimitTest.php`
  - `tests/Integration/CreateCommitFlowTest.php`
- CI matrix on `7.4 / 8.1 / 8.2 / 8.3` with PHPStan level 3 on `lib/`.
  Closes CLE-81, CLE-111, CLE-114, CLE-115, CLE-116, CLE-139.
- `CHANGELOG.md`, `CONTRIBUTING.md`, `CODEOWNERS`, and GitHub issue templates
  (`bug_report.md`, `feature_request.md`, `payment_incident.md`). Closes
  CLE-129, CLE-130, CLE-131, CLE-140, CLE-141, CLE-143.

### Changed
- README aligned with the actual PHP matrix in CI.
- Consolidated CI workflows into a single `.github/workflows/ci.yml`.

## [1.0.0] - 2026-06-26

### Added
- Initial public release with Webpay Directo integration for WHMCS 8.x.
