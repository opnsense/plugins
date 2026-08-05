# CI Test Suite Boundary Design

## Purpose

Give the CI control-plane code a tracked, durable regression suite without
mixing it with BIND plugin behavior tests or disposable investigation harnesses.

## Test locations

- `.github/ci/ci-tests/` contains tracked Python tests and shell fixtures for
  `.github/ci/` helpers and GitHub workflow contracts.
- `dns/bind/tests/` contains tracked tests of BIND plugin configuration,
  templates, runtime scripts, and other plugin behavior.
- `.github/ci-local/` remains ignored and is only for temporary experiments or
  one-off diagnostic harnesses. Its contents must never be staged or committed.

## Migration scope

Migrate the existing control-plane tests and fixtures from the historical
`tools/ci/tests/` suite, together with the recent BIND package reuse and
release-provenance tests. Update their repository-root discovery and imports
to the `.github/ci/` layout. Do not move or change BIND plugin tests as part of
this migration.

## Execution model

The tracked suite must run with `pytest -q .github/ci/ci-tests` on a regular
Linux runner. Tests must use local Git repositories and command fixtures rather
than real GitHub, OPNsense, FreeBSD package, signing, or network operations.
The workflows add a dedicated test job before any state-changing build or
publication job proceeds.

## Agent guidance

Agent instructions will distinguish durable CI tests from disposable local
harnesses and direct plugin-behavior tests to `dns/bind/tests/`.

## Verification

The migration is complete when all copied tests pass from the new path, no
tracked test remains under `tools/ci/tests`, the existing CI helpers compile,
and the workflow test job invokes the tracked suite before release work.
