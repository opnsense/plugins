# GitHub CI Helper Layout Design

## Purpose

Place committed CI implementation assets beside the GitHub workflows that use
them, while preserving a clearly separated location for disposable local test
harnesses.

## Layout

- Move the tracked `tools/ci/` tree to `.github/ci/`, preserving each helper,
  the `patches/` directory, and executable permissions.
- Store temporary, local-only harnesses under `.github/ci-local/`.
- Add `/.github/ci-local/` to `.gitignore`. Nothing under that directory is
  part of the repository contract or may be staged or committed.

## Consumers

GitHub Actions workflows invoke `.github/ci/` helpers directly. The
overlay-path manifest, active maintainer instructions, and current build
documentation use the same path. Historical plans and design records retain
their original paths because they describe the repository at the time they
were written.

## Local-test policy

Agents may create temporary regression harnesses in `.github/ci-local/` while
developing or diagnosing CI changes. They must run those harnesses as needed,
then remove them when they are no longer useful. These files must never be
added, committed, or relied on by workflows.

## Verification

Verification checks that no live workflow or active documentation references
`tools/ci`, that the relocated Python and shell helpers retain syntax validity,
and that `git check-ignore` confirms the local-only directory is ignored.
