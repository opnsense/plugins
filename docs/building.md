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
- `core_commit`: the exact OPNsense core Git commit used to configure the
  package repository. The build checks out and verifies this commit directly;
  it does not trust the bytes of a GitHub-generated archive.
- `core_archive_url` and `core_archive_sha256`: legacy provenance fields kept
  in existing release profiles. They are not the build-time trust anchor.

Do not substitute a moving branch, a current tools checkout, or an unverified
core commit for these values. `tools/ci/metadata_profile.py` rejects profiles
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

The runner checks out the pinned OPNsense core commit and configures its
package repository and fingerprints. It then checks out the exact FreeBSD
Ports recipe pinned in `.resolver-plugins/bind920.json`, verifies its hashes,
and builds `bind-tools` followed by `bind920` at `9.20.26_1`. Documentation is
excluded because it is not needed at runtime; this keeps the source build from
pulling in the large Sphinx documentation toolchain. Those packages
are installed in the build VM before `os-bind-rp` is packaged, which records
the exact BIND dependency in the plugin manifest. The plugin builder rejects
any BIND version below `9.20.26` and verifies the OPNsense version floor.
It clears only `dns/bind/work` before packaging; do not invoke the inherited
`make clean` target after materializing a release source, because that target
resets `dns/bind/src` to the control-plane checkout.

The disposable FreeBSD 14.3 GitHub Actions image may need
`IGNORE_OSVERSION=yes` to install current builder tools after the public
FreeBSD catalogue advances. Current FreeBSD Ports also requires
`ALLOW_UNSUPPORTED_SYSTEM=yes` for its end-of-life release; the BIND wrapper
scopes that flag, along with `BATCH=yes`, to the two Ports package builds. None
of these builder-only compatibility overrides alter the target ABI or the
OPNsense packages used by `os-bind-rp`.

The wrapper explicitly installs the pinned recipe's ordinary build and linked
library dependencies from the configured OPNsense repository, then invokes the
two BIND builds with `NO_DEPENDS=yes`. It therefore compiles only the pinned
BIND source instead of recursively rebuilding ordinary Ports dependencies,
while retaining OPNsense-compatible linked libraries.

The runner installs `python3` first when the clean FreeBSD environment does
not provide it; Python is required to validate the immutable metadata before
any OPNsense package repository configuration is used.

For example, after preparing the selected release source:

```sh
RP_UPSTREAM_METADATA=.resolver-plugins/upstream.json \
SOURCE_COMMIT="$source_commit" \
tools/ci/build-bind920.sh "$series" "artifacts/$series"
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
sh -n tools/ci/build-bind920.sh tools/ci/build-os-bind-rp.sh \
  tools/ci/setup-opnsense-repository.sh
python3 -m py_compile tools/ci/*.py
git diff --check
```

Inspect the resulting package and `build-metadata.txt` before treating a build
artifact as suitable for later package-repository publication.
