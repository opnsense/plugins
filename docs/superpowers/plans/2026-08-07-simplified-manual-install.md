# Simplified Manual Installation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Simplify the root README's manual `os-bind-rp` installation commands by making `series=26.1` an explicit operator input.

**Architecture:** Keep the existing signed-repository installation flow and replace only its automatic OPNsense version parsing and shell validation. Use a short-term assertion during implementation instead of committing a brittle wording-specific documentation test.

**Tech Stack:** Markdown, POSIX shell examples, pytest.

## Global Constraints

- The manual installation block starts with the exact assignment `series=26.1`.
- The manual block does not invoke `opnsense-version` or `pkg version -t` and does not contain shell `case` validation.
- Prose continues to tell operators to select the series matching their installed OPNsense release.
- The existing OPNsense `26.1.11_10` minimum-version warning remains in the README.
- The interactive installer and its automatic checks remain unchanged.

---

### Task 1: Simplify and verify the manual installation block

**Files:**
- Modify: `README.md:34-75`

**Interfaces:**
- Consumes: the `Installing os-bind-rp` README section and its `Or install` boundary.
- Produces: a manual shell block driven by the explicit `series` variable.

- [ ] **Step 1: Run the short-term check and verify it detects the old block**

```python
from pathlib import Path

text = Path("README.md").read_text(encoding="utf-8")
manual = text.split("From an OPNsense root shell", 1)[1].split(
    "Or install the signed repository", 1
)[0]
assert "series=26.1" in manual
assert "opnsense-version" not in manual
assert "pkg version -t" not in manual
assert 'case "$series" in' not in manual
```

Expected: the assertion exits nonzero because the current block derives `series` with
`opnsense-version` instead of containing `series=26.1`.

- [ ] **Step 2: Simplify the README manual directions**

Change the introduction to:

```markdown
From an OPNsense root shell, set the supported `major.minor` release series and
configure its current channel:
```

Replace the automatic detection and nested `case` commands at the start of the
shell block with:

```sh
series=26.1
channel="pkg-$series"
```

Leave the public-key verification, repository configuration, `pkg update`,
`pkg install`, interactive-installer section, and minimum-version warning
unchanged.

- [ ] **Step 3: Run short-term and complete verification**

Run:

```sh
python3 - <<'PY'
from pathlib import Path

text = Path("README.md").read_text(encoding="utf-8")
manual = text.split("From an OPNsense root shell", 1)[1].split(
    "Or install the signed repository", 1
)[0]
assert "series=26.1" in manual
assert "opnsense-version" not in manual
assert "pkg version -t" not in manual
assert 'case "$series" in' not in manual
PY
python3 -m pytest -q .github/ci/ci-tests/test_package_documentation.py
python3 -m pytest -q .github/ci/ci-tests
git diff --check
```

Expected: all tests pass and `git diff --check` exits 0.

- [ ] **Step 4: Commit the implementation**

```sh
git add README.md \
  docs/superpowers/specs/2026-08-07-simplified-manual-install-design.md \
  docs/superpowers/plans/2026-08-07-simplified-manual-install.md
git commit -m "Simplify manual package installation"
```
