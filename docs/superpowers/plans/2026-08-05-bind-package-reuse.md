# BIND Package Reuse Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reuse a compatible, signed stable-channel BIND package pair when
building `os-bind-rp`, while retaining the current pinned Ports build as the
safe cache-miss path.

**Architecture:** Trusted `master` tooling derives a canonical BIND
compatibility identity and creates a small provenance document beside built
packages. The build wrapper first asks a dedicated reuse helper to fetch the
matching package pair through the signed stable `pkg` channel; only a
documented cache miss continues to the existing Ports build. Production
staging carries the provenance document into the fixed GitHub Release.

**Tech Stack:** Python 3 standard library, POSIX shell, FreeBSD `pkg`, GitHub
Actions, GitHub Release assets.

## Global Constraints

- Stable channels remain `pkg-<series>` and contain `bind-tools`, `bind920`,
  and one production `os-bind-rp` package.
- The current version floor remains BIND `9.20.26` and OPNsense
  `26.1.11_10`.
- Reuse is only within one series, FreeBSD release, architecture, and complete
  pinned BIND profile.
- Candidate packages are fetched through a `pkg` configuration using the
  committed Resolver Plugins public key; provenance alone is never trusted.
- A missing stable channel or provenance document is a cache miss; malformed
  provenance or a failed package verification is a hard failure.
- Tests remain under local-only `tools/ci/tests-local/` until the maintainer
  testing policy changes; do not commit them.

---

### Task 1: Canonical compatibility and provenance tooling

**Files:**
- Modify: `tools/ci/bind920_profile.py`
- Create (local only): `tools/ci/tests-local/test_bind920_reuse.py`

**Interfaces:**
- Consumes: validated `bind920.json`, `series`, `freebsd_release`, and
  architecture.
- Produces: `compatibility_fingerprint(profile, series, freebsd_release,
  architecture) -> str`; `write_provenance(path, ...) -> None`; and
  `load_provenance(path) -> dict[str, object]`.

- [ ] **Step 1: Write failing local tests**

```python
def test_fingerprint_changes_when_series_or_profile_changes():
    profile = valid_profile()
    assert fingerprint(profile, "26.1", "14.3", "x86_64") != \
        fingerprint(profile, "26.7", "14.3", "x86_64")

def test_provenance_requires_the_exact_bind_package_identities(tmp_path):
    write_provenance(tmp_path / "bind920-provenance.json", valid_arguments())
    provenance = load_provenance(tmp_path / "bind920-provenance.json")
    assert provenance["packages"]["bind920"]["origin"] == "dns/bind920"
```

- [ ] **Step 2: Run the test and verify it fails**

Run: `python3 tools/ci/tests-local/test_bind920_reuse.py`

Expected: failure because the compatibility and provenance interfaces do not
exist.

- [ ] **Step 3: Implement the minimal Python interfaces**

Use `json.dumps(..., sort_keys=True, separators=(",", ":"))` and SHA-256 over
UTF-8 bytes. Require schema `1`, exact series/freebsd-release/architecture,
and package identity objects with `name`, `version`, `origin`, and `filename`.
Expose CLI commands `fingerprint` and `provenance` so shell wrappers do not
duplicate Python validation.

- [ ] **Step 4: Run local tests and static checks**

Run:

```sh
python3 tools/ci/tests-local/test_bind920_reuse.py
python3 -m py_compile tools/ci/bind920_profile.py
```

Expected: both exit 0.

- [ ] **Step 5: Commit the tracked implementation**

```sh
git add tools/ci/bind920_profile.py
git commit -m "feat(ci): identify reusable BIND packages"
```

### Task 2: Signed stable-channel reuse helper

**Files:**
- Create: `tools/ci/reuse_bind920.py`
- Modify: `tools/ci/build-bind920.sh`
- Create (local only): `tools/ci/tests-local/test_reuse_bind920.py`

**Interfaces:**
- Consumes: `<series> <artifact-directory>`, `RP_BIND920_METADATA`,
  `RP_UPSTREAM_METADATA`, `RP_BIND920_CHANNEL_URL`, and the committed
  `docs/package-repository/resolver-plugins.pub`.
- Produces: exit `0` after installing and copying a verified compatible BIND
  pair plus provenance; exit `3` only for a missing compatible cache; nonzero
  non-`3` for invalid provenance or package verification errors.

- [ ] **Step 1: Write failing local wrapper tests**

```python
def test_missing_provenance_is_a_cache_miss(fake_pkg_environment):
    result = run_reuse_helper(fake_pkg_environment)
    assert result.returncode == 3

def test_matching_provenance_fetches_only_the_declared_bind_packages(fake_pkg_environment):
    result = run_reuse_helper(fake_pkg_environment, matching_provenance())
    assert result.returncode == 0
    assert fake_pkg_environment.fetches == ["bind-tools", "bind920"]
```

- [ ] **Step 2: Run the wrapper tests and verify they fail**

Run: `python3 tools/ci/tests-local/test_reuse_bind920.py`

Expected: failure because `reuse_bind920.py` does not exist.

- [ ] **Step 3: Implement the Python reuse helper**

The helper must:

```python
def reuse(series: str, output: Path, channel_url: str, pkg: str) -> int:
    """Return 0 on reuse, 3 on an ordinary miss, or raise on distrust."""
```

The helper downloads `bind920-provenance.json` from
`RP_BIND920_CHANNEL_URL`, compares it with the current Python-derived
fingerprint, writes a temporary `pkg` configuration using
`signature_type: pubkey` and
`docs/package-repository/resolver-plugins.pub`, then invokes `pkg` to
update, fetch, and install only the declared `bind-tools` and `bind920`
files. It queries both package files and installed records for exact name,
version, and origin before copying the packages and provenance to the artifact
directory.

Use a `mktemp -d` directory with a trap for all temporary files. Return `3`
only for HTTP 404 provenance or a fingerprint mismatch before any package
fetch. Any malformed JSON, unknown package identity, failed `pkg update`,
failed `pkg fetch`, failed package query, or failed installation exits with a
diagnostic nonzero status other than `3`.

Modify `build-bind920.sh` to invoke the helper before cloning Ports. On exit
`0`, exit successfully; on `3`, run its existing build unchanged, then invoke
the Python provenance command using the two produced packages; otherwise stop.

- [ ] **Step 4: Run wrapper and shell checks**

Run:

```sh
python3 tools/ci/tests-local/test_reuse_bind920.py
python3 -m py_compile tools/ci/reuse_bind920.py
sh -n tools/ci/build-bind920.sh
```

Expected: both exit 0.

- [ ] **Step 5: Commit the tracked implementation**

```sh
git add tools/ci/reuse_bind920.py tools/ci/build-bind920.sh
git commit -m "feat(ci): reuse signed BIND package artifacts"
```

### Task 3: Carry provenance through release publication

**Files:**
- Modify: `tools/ci/release_channel.py`
- Modify: `.github/workflows/package-release.yml`
- Modify: `docs/building.md`
- Modify: `docs/package-repository.md`
- Create (local only): `tools/ci/tests-local/test_release_channel_provenance.py`

**Interfaces:**
- Consumes: a completed artifact directory containing the three packages and
  `bind920-provenance.json`.
- Produces: a stable Release asset named `bind920-provenance.json` alongside
  the signed package channel.

- [ ] **Step 1: Write a failing local staging test**

```python
def test_stage_requires_and_copies_bind_provenance(tmp_path):
    with pytest.raises(ValueError, match="BIND provenance"):
        stage_repository(package_directory_without_provenance, output, key, "pkg")
```

- [ ] **Step 2: Run the test and verify it fails**

Run: `python3 tools/ci/tests-local/test_release_channel_provenance.py`

Expected: failure because staging currently ignores provenance.

- [ ] **Step 3: Implement publication and workflow wiring**

Require provenance during production staging, copy it to the staged Release
directory after `pkg repo` generates the signed catalogue, and retain it in
asset ordering. Pass the stable channel URL and source metadata required by
the helper into the FreeBSD VM build command. Update maintainer documentation
to describe cache hit, miss, hard failure, and the one-build bootstrap for a
new series.

- [ ] **Step 4: Run the focused verification set**

Run:

```sh
python3 tools/ci/tests-local/test_release_channel_provenance.py
python3 -m py_compile tools/ci/bind920_profile.py tools/ci/release_channel.py
python3 -m py_compile tools/ci/reuse_bind920.py
sh -n tools/ci/build-bind920.sh tools/ci/build-os-bind-rp.sh
git diff --check
```

Expected: every command exits 0.

- [ ] **Step 5: Commit the tracked implementation and documentation**

```sh
git add .github/workflows/package-release.yml tools/ci/release_channel.py \
  docs/building.md docs/package-repository.md
git commit -m "feat(ci): publish BIND provenance"
```

### Task 4: Hosted bootstrap and proof of package reuse

**Files:**
- Modify: no additional tracked files.

**Interfaces:**
- Consumes: the merged workflow and the existing `pkg-26.1` Release.
- Produces: a bootstrap production Release containing BIND provenance, then a
  successful development build whose logs show the reuse path and whose
  pre-release contains `bind-tools`, `bind920`, and an `os-bind-rp-devel`
  package.

- [ ] **Step 1: Push the implementation branch and open a PR**

Run:

```sh
git push -u origin agent/bind-package-reuse
gh pr create --repo resolver-plugins/plugins --base master \
  --head agent/bind-package-reuse --title "Reuse published BIND packages in CI"
```

- [ ] **Step 2: Merge the reviewed PR and bootstrap stable provenance**

Run:

```sh
gh workflow run package-release.yml --repo resolver-plugins/plugins --ref master \
  -f mode=production -f series=26.1
```

Expected: because the existing channel has no provenance yet, this run takes
the current full BIND build path and publishes `bind920-provenance.json` with
the refreshed stable channel.

- [ ] **Step 3: Dispatch a development build that must reuse BIND**

Run:

```sh
gh workflow run package-release.yml --repo resolver-plugins/plugins --ref master \
  -f mode=development -f series=26.1 -f pull_number=<merged-pr-number>
```

Expected: build log states it reused `pkg-26.1`; it does not clone FreeBSD
Ports or invoke either BIND Ports `make package` command.

- [ ] **Step 4: Verify the pre-release artifacts and release asset**

Run:

```sh
gh run view <production-run-id> --repo resolver-plugins/plugins --log
gh run view <development-run-id> --repo resolver-plugins/plugins --log
gh release view pr-<merged-pr-number>-26.1 --repo resolver-plugins/plugins
gh release view pkg-26.1 --repo resolver-plugins/plugins
```

Expected: the production release retains `bind920-provenance.json`; the
development pre-release contains all three packages; and its build log names
the reuse path without a Ports BIND build.

- [ ] **Step 5: Commit final verification notes only if documentation changed**

No source edit is required for the hosted proof. Do not commit downloaded
packages, logs, caches, or test fixtures.
