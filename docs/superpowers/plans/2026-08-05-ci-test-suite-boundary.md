# CI Test Suite Boundary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Track the CI control-plane regression suite under `.github/ci/ci-tests/` and run it before workflow build or publication work.

**Architecture:** Existing CI harnesses move into `.github/ci/ci-tests/`, where tests locate the repository root three parents above and helpers under `.github/ci/`. BIND plugin behavior remains outside this suite and belongs in `dns/bind/tests/`; `.github/ci-local/` remains ignored for throwaway work.

**Tech Stack:** Python 3, pytest, POSIX shell fixtures, GitHub Actions.

## Global Constraints

- Commit CI helper/workflow tests and fixtures only under `.github/ci/ci-tests/`.
- Do not move BIND plugin behavior tests; future such tests belong under `dns/bind/tests/`.
- Keep `.github/ci-local/` ignored and never stage its contents.
- Update tests to target `.github/ci/` and the active `package-release.yml` workflow.
- Use local Git and command fixtures only; tests must not contact GitHub, OPNsense, FreeBSD package servers, or signing infrastructure.

---

### Task 1: Establish the tracked test tree and migrate helper coverage

**Files:**
- Create: `.github/ci/ci-tests/test_*.py`
- Create: `.github/ci/ci-tests/*-fixture.sh`
- Modify: `AGENTS.md`

- [ ] Copy the existing CI-only tests and fixtures into `.github/ci/ci-tests/`.
- [ ] Change every helper path from `tools/ci/` to `.github/ci/` and every fixture path to `.github/ci/ci-tests/`.
- [ ] Retain root discovery as `Path(__file__).resolve().parents[3]`.
- [ ] Run `pytest -q .github/ci/ci-tests` and repair only path/layout failures.

### Task 2: Align workflow-contract coverage with active workflows

**Files:**
- Modify: `.github/ci/ci-tests/test_proof_build_workflow.py`
- Modify: `.github/ci/ci-tests/test_upstream_sync_workflow.py`
- Modify: `.github/workflows/package-release.yml`
- Modify: `.github/workflows/upstream-sync.yml`

- [ ] Replace obsolete `proof-build.yml` assertions with `package-release.yml` assertions for pinned actions, selected immutable release input, non-persistent checkout credentials, and production signing/publication boundaries.
- [ ] Update synchronization workflow assertions to `.github/ci/` helper paths.
- [ ] Add a Linux pytest job to each stateful workflow and make build/reconciliation depend on it.
- [ ] Run the focused workflow-contract tests, then `pytest -q .github/ci/ci-tests`.

### Task 3: Verify policy boundaries and commit

**Files:**
- Modify: `AGENTS.md`
- Verify: `.github/ci/ci-tests/`, `.github/ci-local/`, `dns/bind/tests/`

- [ ] State in `AGENTS.md` that durable CI tests belong in `.github/ci/ci-tests/`, BIND plugin tests belong in `dns/bind/tests/`, and `.github/ci-local/` is disposable only.
- [ ] Run `pytest -q .github/ci/ci-tests`, Python compilation, shell syntax checks, `git diff --check`, and `git check-ignore -q .github/ci-local/`.
- [ ] Confirm `git diff --name-only --cached` contains no `.github/ci-local/` path, then commit the tracked suite and workflow changes.
