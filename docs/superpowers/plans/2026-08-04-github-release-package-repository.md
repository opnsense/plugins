# GitHub Release Package Repository Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build, sign, publish, and verify `os-bind-rp` `pkg` repositories as GitHub Release assets.

**Architecture:** A small Python helper stages and validates a flat signed `pkg` repository. Master-controlled Actions workflows run the existing profiled FreeBSD build, invoke that helper, and create stable per-series Releases or short-lived PR pre-releases.

**Tech Stack:** Python 3 standard library, POSIX shell, FreeBSD `pkg repo`, GitHub Actions, GitHub CLI/API, GitHub Releases and Pages.

## Global Constraints

- Preserve package name `os-bind-rp`, `os-bind` conflict, and OPNsense floor `26.1.11_10`.
- Derive every channel from a numeric series: `pkg-<major>.<minor>`.
- Do not commit `tools/ci/tests/`; retain the regression harness locally.
- Only production workflows obtain `RP_PKG_SIGNING_KEY`.
- Never store package binaries in a Git branch or Pages deployment.

---

### Task 1: Add a testable release-channel helper

**Files:** Create `tools/ci/release_channel.py`; local test
`tools/ci/tests/test_release_channel.py`.

- [ ] Write tests first for `channel_tag("27.1") == "pkg-27.1"`, strict numeric
  series rejection, a single non-development `os-bind-rp-*.pkg` selection, and
  rejection of ambiguous or `-devel` files.
- [ ] Run `pytest -q tools/ci/tests/test_release_channel.py`; confirm it fails
  because the helper is absent.
- [ ] Implement `channel_tag()`, `select_package()`, and an argparse `validate`
  command using `pathlib`, `re`, and no shell interpolation.
- [ ] Run the focused tests and `python3 -m py_compile tools/ci/release_channel.py`.
- [ ] Commit `feat(ci): validate release package channels`.

### Task 2: Stage and sign a flat pkg repository

**Files:** Modify `tools/ci/release_channel.py`; local test
`tools/ci/tests/test_release_channel.py`.

- [ ] Write a failing test for `stage`: copy exactly one selected package into an
  empty directory, call `pkg repo <directory> rsa:<key>`, and return only files
  directly below the staged directory.
- [ ] Run the test; confirm staging is absent.
- [ ] Implement a `stage` command with `subprocess.run([...], check=True)`,
  validate that generated catalog files and the package exist, and reject a key
  outside the supplied temporary directory.
- [ ] Run the local tests and a real stage using an ephemeral key in the matching
  FreeBSD VM.
- [ ] Commit `feat(ci): stage signed pkg repositories`.

### Task 3: Publish stable and development GitHub Releases

**Files:** Modify `tools/ci/release_channel.py`; local test
`tools/ci/tests/test_release_channel.py`.

- [ ] Write failing tests for idempotent `gh release create`, package-before-
  catalog upload order, `--clobber` replacement, and generated UCL bootstrap
  configuration with `signature_type: pubkey`.
- [ ] Run the focused test and confirm it fails before publishing exists.
- [ ] Implement `publish` and `bootstrap` commands. `publish` accepts
  `--repository`, `--series`, `--directory`, and `--prerelease`; it creates or
  updates exactly `pkg-<series>` or `pr-<number>-<series>`.
- [ ] Run all local tests and Python compilation.
- [ ] Commit `feat(ci): publish GitHub Release package channels`.

### Task 4: Add master-controlled workflow entry points

**Files:** Create `.github/workflows/package-release.yml`; modify
`.github/workflows/proof-build.yml`; local workflow-structure tests.

- [ ] Write failing tests that require a manual development dispatch, a merged
  release-branch production event, fixed Action SHAs, series validation, and no
  signing secret in development jobs.
- [ ] Run the tests and confirm the workflow is absent.
- [ ] Add a workflow that materializes only profiled release source, builds in
  the declared FreeBSD VM, stages/signs only production repositories, and uploads
  development packages as replaceable pre-releases.
- [ ] Preserve the existing manual artifact build and make it reusable by the
  production workflow rather than duplicating build logic.
- [ ] Run local tests, `python3 -m py_compile tools/ci/*.py`, `sh -n tools/ci/*.sh`,
  and YAML parsing.
- [ ] Commit `feat(ci): publish os-bind-rp release channels`.

### Task 5: Extend dynamic upstream bootstrap publication

**Files:** Modify `.github/workflows/upstream-sync.yml`; local sync workflow test.

- [ ] Write a failing test that a discovered `27.1` `bootstrap-build` forwards
  its planner series to publication and does not hard-code an existing channel.
- [ ] Run it and confirm current bootstrap only uploads a temporary artifact.
- [ ] Add the production publication handoff after a successful bootstrap build;
  pass only the planner's numeric series and a validated artifact directory.
- [ ] Run all local checks.
- [ ] Commit `feat(ci): publish dynamic bootstrap channels`.

### Task 6: Document the released repository

**Files:** Create `docs/package-repository.md`; modify `docs/README.md`,
`docs/building.md`, `docs/fork-model.md`, `README.md`, and `AGENTS.md`.

- [ ] Write local documentation assertions for channel naming, the version policy,
  the OPNsense floor, the official-package conflict, and development-release
  cleanup.
- [ ] Run them and confirm the repository guide is absent.
- [ ] Add end-user bootstrap instructions and maintainer secret/recovery guidance.
- [ ] Run local tests and `git diff --check`.
- [ ] Commit `docs(bind-rp): document GitHub Release repository`.

### Task 7: Transfer, prove, and launch

**Files:** No source edits after the preceding commits.

- [ ] Transfer `bryanwieg/plugins` to `resolver-plugins/plugins`, update `origin`,
  and push the implementation branch.
- [ ] Build 26.7 on the FreeBSD development VM, stage it with an ephemeral RSA key,
  and upload a disposable `test-pkg-26.7` pre-release.
- [ ] Configure the development VM with its test public key and UCL repository URL;
  verify `pkg update -r resolver-plugins` then `pkg install -y os-bind-rp`.
- [ ] Remove the disposable Release and test key only after recording the outcome.
- [ ] Set the production `RP_PKG_SIGNING_KEY` secret without printing it, merge the
  reviewed implementation, and manually publish `pkg-26.1` and `pkg-26.7`.
