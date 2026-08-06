# Package Release Titles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply concise purpose-first display titles to current and archive package releases without changing their tags, URLs, or contents.

**Architecture:** Add one validated title-derivation function to `release_channel.py` and make publication edit every created or existing package Release to that title. Existing production publication remains the deployment mechanism and supplies the public FreeBSD installation acceptance test.

**Tech Stack:** Python 3, GitHub CLI, GitHub Actions, pytest/unittest, FreeBSD `pkg`.

## Global Constraints

- `pkg-<series>` displays as `<series>-latest`.
- `pkg-<series>-os-bind-rp-<version>` displays as `<series>-archive-<version>`.
- Git tags, repository URLs, assets, signed catalogues, retention, and rollback behavior remain unchanged.
- Package distribution releases must not claim GitHub's singular `Latest` badge.
- OPNsense 26.1 and 26.7 must install successfully from the public distribution repository after deployment.

---

### Task 1: Derive and converge package Release titles

**Files:**
- Modify: `.github/ci/release_channel.py`
- Test: `.github/ci/ci-tests/test_release_channel_archive.py`
- Modify: `docs/package-repository.md`

**Interfaces:**
- Produces: `package_release_title(tag: str) -> str`
- Updates: `publish(repository: str, tag: str, directory: Path, prerelease: bool) -> None`

- [ ] **Step 1: Write failing title tests**

Add literal expectations for `pkg-26.1` → `26.1-latest` and
`pkg-26.1-os-bind-rp-1.36_9` → `26.1-archive-1.36_9`. Add invalid-tag cases
that cannot be confused with production channel tags.

- [ ] **Step 2: Run the focused tests and verify RED**

Run: `python3 -m pytest -q .github/ci/ci-tests/test_release_channel_archive.py -k package_release_title`

Expected: failure because `package_release_title` does not exist.

- [ ] **Step 3: Implement validated title derivation**

Parse only exact current and snapshot tag forms using the existing series and
package-version validators. Return the two approved literal title forms and
raise `ValueError("invalid package release tag")` for every other tag.

- [ ] **Step 4: Write a failing existing-release convergence test**

Exercise `publish()` with an already-existing Release fixture and assert that
the GitHub boundary receives `release edit <tag> --title <derived-title>
--latest=false` before asset publication.

- [ ] **Step 5: Run the convergence test and verify RED**

Run: `python3 -m pytest -q .github/ci/ci-tests/test_release_channel_archive.py -k existing_package_release_title`

Expected: failure because existing releases are not edited.

- [ ] **Step 6: Make publication converge the display title**

After the create-or-existing result, call `gh release edit` with the derived
title and `--latest=false`. Use the same derived title for initial creation.

- [ ] **Step 7: Document the display names and verify GREEN**

Document current versus archive display names while emphasizing stable tags
and URLs.

Run: `python3 -m pytest -q .github/ci/ci-tests`

Expected: all tests pass.

Run: `python3 -m py_compile .github/ci/release_channel.py && git diff --check`

Expected: exit 0.

### Task 2: Review and deploy

**Files:**
- No additional source files.

**Interfaces:**
- Consumes the package-release workflow on merged `master`.

- [ ] **Step 1: Request exact-head review and pass PR checks**

Review title parsing, preservation of tags/URLs, retry behavior, GitHub CLI
arguments, tests, and documentation. Fix every critical or important finding.

- [ ] **Step 2: Merge the reviewed PR**

Merge only after exact-head checks and final review approve it.

- [ ] **Step 3: Dispatch both production series**

Run `package-release.yml` from merged `master` in production mode for series
`26.1` and `26.7`. Require every build, signing/reuse, publication, public
FreeBSD installation, and source-release job to succeed.

- [ ] **Step 4: Verify live titles and stable identities**

Assert the four GitHub Release titles are exactly `26.1-latest`,
`26.1-archive-1.36_9`, `26.7-latest`, and `26.7-archive-1.36_2`. Assert the
existing four tags remain and extract the installed package identities from
both public FreeBSD verification logs.
