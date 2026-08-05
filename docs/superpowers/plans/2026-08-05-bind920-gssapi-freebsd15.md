# BIND 9.20 GSSAPI Selection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the shared BIND 9.20 package builder select a FreeBSD-15-compatible GSSAPI option while preserving the existing 26.1 build contract.

**Architecture:** The BIND Ports recipe exposes `GSSAPI_NONE`, which maps to BIND's `--without-gssapi` configuration. Pass that option explicitly to the two existing Ports package commands so their option resolution no longer depends on the FreeBSD major version. Retain the pinned recipe, Ports OpenSSL selection, package names, and metadata validation.

**Tech Stack:** POSIX shell, FreeBSD Ports options, GitHub Actions FreeBSD VM.

## Global Constraints

- Preserve `bind-tools` and `bind920` package identities at `9.20.26_1`.
- Preserve the pinned Ports commit and all profile/hash validation.
- Keep the OPNsense `26.1.11_10` floor and `os-bind` conflict unchanged.
- Keep release provenance on `release/bind-rp/<series>` immutable; this is a `master` control-plane change.
- The local regression harness belongs only under ignored `.github/ci-local/`.

---

### Task 1: Make the BIND package option deterministic

**Files:**
- Create: `.github/ci-local/test-build-bind920-gssapi.sh`
- Modify: `.github/ci/build-bind920.sh:78-84`

**Interfaces:**
- Consumes: the two existing `make ... package` commands for `dns/bind-tools` and `dns/bind920`.
- Produces: both commands receive `GSSAPI_NONE=on` in addition to their existing build flags.

- [ ] **Step 1: Write the failing regression harness**

```sh
#!/bin/sh
set -eu

builder=.github/ci/build-bind920.sh
count=$(grep -Fc 'GSSAPI_NONE=on' "$builder")
[ "$count" -eq 2 ] || {
    printf '%s\n' 'both BIND package commands must select GSSAPI_NONE' >&2
    exit 1
}
```

- [ ] **Step 2: Run the harness to verify it fails**

Run: `sh .github/ci-local/test-build-bind920-gssapi.sh`

Expected: exit 1 with `both BIND package commands must select GSSAPI_NONE`.

- [ ] **Step 3: Add the explicit FreeBSD Ports option to both package commands**

```sh
ALLOW_UNSUPPORTED_SYSTEM=yes BATCH=yes NO_DEPENDS=yes OPTIONS_UNSET=DOCS \
    GSSAPI_NONE=on "$make_command" -C "$ports_directory/dns/bind-tools" \
    PORTSDIR="$ports_directory" package

ALLOW_UNSUPPORTED_SYSTEM=yes BATCH=yes NO_DEPENDS=yes OPTIONS_UNSET=DOCS \
    GSSAPI_NONE=on "$make_command" -C "$ports_directory/dns/bind920" \
    PORTSDIR="$ports_directory" package
```

- [ ] **Step 4: Run focused verification**

Run:

```sh
sh .github/ci-local/test-build-bind920-gssapi.sh
sh -n .github/ci/build-bind920.sh .github/ci/build-os-bind-rp.sh \
  .github/ci/setup-opnsense-repository.sh
python3 -m py_compile .github/ci/*.py
git diff --check
```

Expected: every command exits 0.

- [ ] **Step 5: Commit the control-plane fix**

```sh
git add .github/ci/build-bind920.sh \
  docs/superpowers/specs/2026-08-05-bind920-gssapi-freebsd15-design.md \
  docs/superpowers/plans/2026-08-05-bind920-gssapi-freebsd15.md
git commit -m "fix(ci): select portable BIND GSSAPI option"
```

The ignored local harness is deliberately not staged.

### Task 2: Prove the 26.7 package build and release port

**Files:** No source edits unless Task 1 verification finds a regression.

**Interfaces:**
- Consumes: `master` at the Task 1 commit and PR #28 (`port/bind-rp-26.7`).
- Produces: a successful `pr-28-26.7` development release containing the BIND package pair and `os-bind-rp` package.

- [ ] **Step 1: Dispatch the documented development package workflow**

```sh
gh workflow run package-release.yml --repo resolver-plugins/plugins --ref master \
  --field mode=development --field series=26.7 --field pull_number=28
```

- [ ] **Step 2: Verify the workflow conclusion and development release assets**

Run:

```sh
run_id=$(gh run list --repo resolver-plugins/plugins --workflow package-release.yml \
  --branch master --event workflow_dispatch --limit 1 --json databaseId \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["databaseId"])')
gh run view "$run_id" --repo resolver-plugins/plugins --json status,conclusion,url,jobs
gh release view pr-28-26.7 --repo resolver-plugins/plugins --json tagName,isPrerelease,assets,url
```

Expected: a successful build and exactly the development artifacts required by the package workflow.

- [ ] **Step 3: Mark PR #28 ready and merge it**

```sh
gh pr ready 28 --repo resolver-plugins/plugins
gh pr merge 28 --repo resolver-plugins/plugins --merge --delete-branch=false
```

- [ ] **Step 4: Verify the production 26.7 publication workflow**

Run:

```sh
gh run list --repo resolver-plugins/plugins --workflow package-release.yml --limit 10
gh release view pkg-26.7 --repo resolver-plugins/plugins --json tagName,assets,url
```

Expected: the merge-triggered production workflow completes successfully and `pkg-26.7` contains current BIND and `os-bind-rp` package assets.
