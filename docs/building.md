# Building os-bind-rp

## Build inputs

Build a release branch in the FreeBSD release declared by that branch's
`.resolver-plugins/upstream.json`. The profile contains exactly these immutable
inputs:

- `series` and `upstream_branch`: the OPNsense stable series being built.
- `upstream_commit`: the OPNsense plugins source commit.
- `tools_tag`: the matching numeric `opnsense/tools` release tag.
- `freebsd_release`: the value of `OS?=` in
  `config/<series>/build.conf` at that `tools_tag`.
- `core_commit`, `core_archive_url`, and `core_archive_sha256`: the exact
  OPNsense core archive and checksum used to configure the package repository.

Do not substitute a moving branch, a current tools checkout, or an unverified
core archive for these values. `tools/ci/metadata_profile.py` rejects profiles
that do not meet the required schema and provenance checks.

## Local build

The GitHub Actions workflow is the canonical build path. It keeps the CI
scripts checked out from `master`, fetches the selected immutable release
commit, then materializes only that commit's `dns/bind` source and
`.resolver-plugins/upstream.json` and `Mk` build framework. This matters for
legacy release branches, which intentionally do not carry the control-plane
scripts. In particular, the release `Mk` files prevent a development-branch
marker from adding an unintended `-devel` package suffix.

Reproduce that split in a disposable worktree when building locally. Start
from `master`, fetch the selected release branch, and overlay only its release
inputs before entering the matching FreeBSD environment:

```sh
series=26.7
release_ref="refs/heads/release/bind-rp/$series"
git fetch --no-tags origin "$release_ref:refs/remotes/origin/build-source"
source_commit=$(git rev-parse refs/remotes/origin/build-source)
git checkout "$source_commit" -- .resolver-plugins/upstream.json Mk dns/bind
git cat-file -e "$source_commit:Mk/devel.mk" 2>/dev/null || rm -f Mk/devel.mk
```

The runner configures the official OPNsense package repository from the pinned
core archive, verifies its fingerprints, installs `bind920`, verifies the
OPNsense version floor, and packages the materialized `dns/bind` source.

The runner installs `python3` first when the clean FreeBSD environment does
not provide it; Python is required to validate the immutable metadata before
any OPNsense package repository configuration is used.

For example, after preparing the selected release source:

```sh
RP_UPSTREAM_METADATA=.resolver-plugins/upstream.json \
SOURCE_COMMIT="$source_commit" \
tools/ci/build-os-bind-rp.sh "$series" "artifacts/$series"
```

The package and `build-metadata.txt` are written below the output directory.
The metadata records the source commit, BIND package version, OPNsense package
version, ABI, provenance values, and FreeBSD environment used for the build.

`tools/ci/setup-opnsense-repository.sh <series>` is normally called by the
build runner. Use it directly only when diagnosing repository setup; it changes
the FreeBSD VM's package repository configuration.

## Before approving a build change

Check that the metadata is valid before running a full VM build:

```sh
python3 tools/ci/metadata_profile.py \
  .resolver-plugins/upstream.json "$series" freebsd_release
sh -n tools/ci/build-os-bind-rp.sh tools/ci/setup-opnsense-repository.sh
python3 -m py_compile tools/ci/*.py
git diff --check
```

Inspect the resulting package and `build-metadata.txt` before treating a build
artifact as suitable for later package-repository publication.
