# GitHub CI Helper Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move all committed CI helpers from `tools/ci/` to `.github/ci/` and preserve a separate ignored home for temporary local test harnesses.

**Architecture:** GitHub workflows will invoke committed helpers from `.github/ci/`, co-locating executable implementation with workflow configuration. `.github/ci-local/` remains entirely ignored and is reserved only for disposable test harnesses used while modifying CI; it cannot be committed or consumed by a workflow.

**Tech Stack:** GitHub Actions YAML, POSIX shell, Python 3, Git path tracking, `.gitignore`.

## Global Constraints

- Move every tracked member of `tools/ci/`, including `patches/bind920-portrevision.patch`, without changing helper behavior or executable mode.
- Add `/.github/ci-local/` to `.gitignore`; no file below it may be staged or committed.
- Update every live workflow, overlay manifest, active maintainer instruction, and active build document to use `.github/ci/`.
- Leave historical plans and design records unchanged because they record their original repository state.
- Verify Python and shell syntax, workflow and documentation references, ignored-local behavior, and Git path tracking before commit.

---

### Task 1: Create a disposable layout contract harness

**Files:**
- Create locally only: `.github/ci-local/test-ci-helper-layout.sh`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: the repository root as the current directory.
- Produces: exit status 0 only when tracked CI helpers live under `.github/ci/`, no tracked helper remains below `tools/ci`, workflows do not reference `tools/ci`, and `.github/ci-local/` is ignored.

- [ ] **Step 1: Write the failing local-only test**

Create `.github/ci-local/test-ci-helper-layout.sh` with:

```sh
#!/bin/sh
set -eu
test -f .github/ci/build-bind920.sh
test -f .github/ci/patches/bind920-portrevision.patch
test ! -e tools/ci
! git ls-files -- tools/ci | grep -q .
! git grep -n 'tools/ci' -- .github/workflows
git check-ignore -q .github/ci-local/test-ci-helper-layout.sh
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `sh .github/ci-local/test-ci-helper-layout.sh`

Expected: FAIL because `.github/ci/build-bind920.sh` does not yet exist.

- [ ] **Step 3: Add the ignore rule before relying on the local-only directory**

Add this exact rule to `.gitignore`:

```gitignore
/.github/ci-local/
```

- [ ] **Step 4: Verify the test harness is ignored**

Run: `git check-ignore -v .github/ci-local/test-ci-helper-layout.sh`

Expected: `.gitignore` reports the `/.github/ci-local/` rule.

### Task 2: Relocate tracked CI helpers and rewire consumers

**Files:**
- Move: `tools/ci/` → `.github/ci/`
- Modify: `.github/workflows/package-release.yml`
- Modify: `.github/workflows/upstream-sync.yml`
- Modify: `.resolver-plugins/overlay-paths.txt`
- Modify: `AGENTS.md`
- Modify: `docs/building.md`

**Interfaces:**
- Consumes: helper script names and relative resource layout preserved from `tools/ci/`.
- Produces: `.github/ci/` with identical helper entry points and resource-relative paths; all live consumers invoke the new location.

- [ ] **Step 1: Move the complete tracked helper tree**

Run:

```sh
mkdir -p .github
git mv tools/ci .github/ci
```

- [ ] **Step 2: Rewrite live consumers only**

Replace `tools/ci` with `.github/ci` in the two workflow files, the overlay
manifest, `AGENTS.md`, and `docs/building.md`. Do not rewrite dated plans or
specifications.

- [ ] **Step 3: State the local-only policy in agent guidance**

In `AGENTS.md`, describe `.github/ci-local/` as an ignored temporary-harness
directory. Agents may use it while changing CI, must remove obsolete harnesses
when practical, and must never stage or commit its contents.

- [ ] **Step 4: Run the layout contract to verify it passes**

Run: `sh .github/ci-local/test-ci-helper-layout.sh`

Expected: PASS.

### Task 3: Verify the relocated implementation and commit it

**Files:**
- Verify: `.github/ci/*.py`, `.github/ci/*.sh`, `.github/ci/patches/bind920-portrevision.patch`
- Verify: `.github/workflows/package-release.yml`, `.github/workflows/upstream-sync.yml`, `.resolver-plugins/overlay-paths.txt`, `AGENTS.md`, `docs/building.md`, `.gitignore`

**Interfaces:**
- Consumes: the relocated helpers and updated consumer paths.
- Produces: an evidence-backed relocation commit that excludes local-only harnesses.

- [ ] **Step 1: Run full syntax and reference verification**

Run:

```sh
PYTHONPYCACHEPREFIX=/tmp/ci-helper-relocation-pycache python3 -m py_compile .github/ci/*.py
sh -n .github/ci/*.sh
! git grep -n 'tools/ci' -- .github/workflows .resolver-plugins/overlay-paths.txt AGENTS.md docs/building.md
git diff --check
git check-ignore -q .github/ci-local/test-ci-helper-layout.sh
```

Expected: all commands exit 0.

- [ ] **Step 2: Inspect Git tracking and executable modes**

Run:

```sh
git diff --summary -- .github/ci
git ls-files --error-unmatch .github/ci/build-bind920.sh .github/ci/build-os-bind-rp.sh .github/ci/setup-opnsense-repository.sh
! git ls-files --error-unmatch .github/ci-local/test-ci-helper-layout.sh
```

Expected: helpers are tracked as renames, executable helpers retain mode `100755`, and the local test is untracked.

- [ ] **Step 3: Commit tracked relocation files only**

Run:

```sh
git add .gitignore .github/ci .github/workflows .resolver-plugins/overlay-paths.txt AGENTS.md docs/building.md
git commit -m "refactor(ci): colocate helpers with workflows"
```

- [ ] **Step 4: Confirm the commit excludes the harness**

Run: `git show --name-only --format=HEAD`

Expected: no `.github/ci-local/` path appears.
