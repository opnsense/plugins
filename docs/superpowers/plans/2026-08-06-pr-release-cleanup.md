# Pull Request Release Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete every development GitHub Release and tag when its pull request closes, including safe handling of in-flight publication.

**Architecture:** Extend `release_channel.py` with exact development-tag selection and idempotent release/tag deletion. A trusted `pull_request_target` close workflow invokes pull-wide cleanup, while the development publisher checks PR state before and after upload and invokes exact-tag cleanup when closed.

**Tech Stack:** Python 3, GitHub CLI, GitHub Actions YAML, pytest/unittest.

## Global Constraints

- Cleanup runs for every closed pull request, merged or unmerged.
- Only exact `pr-<positive integer>-<major>.<minor>` tags may be deleted.
- The close workflow must never check out or execute pull request head code.
- GitHub API/authentication errors must fail; only a confirmed missing release or tag is idempotent success.
- Production source releases and distribution channels are out of scope.

---

### Task 1: Tested cleanup helper

**Files:**
- Modify: `.github/ci/release_channel.py`
- Test: `.github/ci/ci-tests/test_release_channel_archive.py`

**Interfaces:**
- Produces: `cleanup_development_release(repository: str, tag: str) -> None`
- Produces: `cleanup_pull_request_releases(repository: str, pull_number: str) -> None`
- Produces CLI: `cleanup-tag --repository OWNER/REPO --tag TAG`
- Produces CLI: `cleanup-pull-request --repository OWNER/REPO --pull-number N`

- [ ] **Step 1: Write failing tests**

Add tests proving that only complete tags for the requested pull number are selected, each selected release is deleted with `--cleanup-tag`, near matches are preserved, an empty match is successful, and invalid pull numbers or exact tags fail before a GitHub mutation. Test exact-tag cleanup independently for use by the publisher.

- [ ] **Step 2: Verify RED**

Run: `python3 -m pytest -q .github/ci/ci-tests/test_release_channel_archive.py -k pull_request_release`

Expected: failures because the cleanup function and CLI do not exist.

- [ ] **Step 3: Implement the minimal helper**

List releases with paginated `gh api`, flatten the response, require the exact regular expression `pr-<pull-number>-[0-9]+\.[0-9]+`, then call the exact-tag helper for each match. The exact-tag helper validates the complete development tag, deletes the Release, and independently deletes `git/refs/tags/<tag>` so an orphaned tag is also removed. Only confirmed missing Release and tag responses are idempotent successes. Validate inputs before listing or deleting.

- [ ] **Step 4: Verify GREEN**

Run: `python3 -m pytest -q .github/ci/ci-tests/test_release_channel_archive.py -k pull_request_release`

Expected: all selected tests pass.

### Task 2: Close-event workflow and publication race guard

**Files:**
- Create: `.github/workflows/pr-release-cleanup.yml`
- Modify: `.github/workflows/package-release.yml`
- Create: `.github/ci/ci-tests/test_pr_release_cleanup_workflow.py`
- Modify: `.github/ci/ci-tests/test_package_release_workflow.py`
- Modify: `docs/package-repository.md`

**Interfaces:**
- Consumes: `release_channel.py cleanup-pull-request`
- Consumes: `release_channel.py cleanup-tag --repository OWNER/REPO --tag TAG`

- [ ] **Step 1: Write failing workflow tests**

Require `pull_request_target: types: [closed]`, job-level `contents: write`, checkout pinned to `${{ github.workflow_sha }}`, `persist-credentials: false`, trusted `${{ github.event.pull_request.number }}`, and absence of PR-head fields. Require the development publisher to have `pull-requests: read`, query PR state before and after publication, and clean its exact tag when closed.

- [ ] **Step 2: Verify RED**

Run: `python3 -m pytest -q .github/ci/ci-tests/test_pr_release_cleanup_workflow.py .github/ci/ci-tests/test_package_release_workflow.py`

Expected: failures because the close workflow and race guards do not exist.

- [ ] **Step 3: Implement the workflows and documentation**

Create the close workflow with no PR-controlled shell inputs beyond the numeric event field. Add `pull_number` to select-job outputs, query the pull request through `gh api` before and after development publication, and invoke exact-tag cleanup if state is not `open`. Document that development releases disappear on every PR close.

- [ ] **Step 4: Verify GREEN and the full suite**

Run: `python3 -m pytest -q .github/ci/ci-tests`

Expected: all tests pass.

Run: `python3 -m py_compile .github/ci/release_channel.py && git diff --check`

Expected: exit 0.

### Task 3: Review, deploy, and clean existing releases

**Files:**
- No additional source files.

**Interfaces:**
- Consumes the reviewed branch and GitHub Actions close event.

- [ ] **Step 1: Request exact-head review and pass branch checks**

Review the complete base-to-head diff for tag-scope safety, token permissions, untrusted-code execution, race handling, and tests. Fix all critical and important findings.

- [ ] **Step 2: Merge the PR**

Merge only after exact-head checks and review pass.

- [ ] **Step 3: Remove existing merged-PR releases**

Delete `pr-48-26.1`, `pr-48-26.7`, `pr-51-26.1`, and `pr-51-26.7` with `--cleanup-tag`.

- [ ] **Step 4: Verify deployment**

Confirm the cleanup workflow ran successfully for the merged implementation PR. Confirm no `pr-*` releases remain, the two immutable source releases remain, and the four distribution releases remain unchanged.
