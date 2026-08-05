# Package-channel distribution design

## Goal

Separate human-facing `os-bind-rp` releases from operational `pkg`
repositories while keeping every supported OPNsense series easy to install
and able to roll back five plugin versions.

This is the approved target design. It supersedes the current convention of
using source-repository GitHub Releases as the per-series package channels.

## Decisions

- `resolver-plugins/plugins` remains the source and control-plane repository.
  It owns source, release-source branches, CI, documentation, and public
  signing-key material.
- `https://github.com/resolver-plugins/repository` is the dedicated
  distribution repository. It contains generated signed package-channel
  assets only; it is not a source or development repository.
- Each OPNsense series has one self-contained, rolling channel: `pkg-26.1`,
  `pkg-26.7`, and so on.
- Source-repository GitHub Releases are ordinary, immutable, human-facing
  `os-bind-rp` version releases. Their assets are limited to the plugin
  package and small release metadata; they never contain a `pkg` catalogue,
  BIND package pair, signing material, or repository bootstrap files.
- Each distribution channel retains the five newest `os-bind-rp` versions for
  its series and every BIND package version required by one of those five
  packages.

## Repository boundaries

### Source repository: `resolver-plugins/plugins`

`master` remains the CI and documentation control plane. Each
`release/bind-rp/<series>` branch remains a reviewed, immutable build source
with matching `.resolver-plugins/upstream.json` provenance. Neither a
release-source branch nor `master` contains generated package catalogues or
package-repository release assets.

The source repository owns the trusted publication workflow. Its build job
selects a release source but cannot read the signing key. A separate trusted
signing job receives only built artifacts, generates the signed repository,
and publishes it to the distribution repository. The source repository also
creates an immutable human-facing release record for the newly published
`os-bind-rp` version.

### Distribution repository: `resolver-plugins/repository`

The distribution repository exposes stable GitHub Release tags used directly
as OPNsense package base URLs:

```text
https://github.com/resolver-plugins/repository/releases/download/pkg-<series>
```

These tags are operational channels rather than product releases. Updating a
tag replaces its signed package-repository snapshot, but each snapshot is
fully self-contained for its OPNsense series.

## Channel contents

For example, `pkg-26.7` contains:

```text
os-bind-rp-1.36_3.pkg
os-bind-rp-1.36_4.pkg
os-bind-rp-1.36_5.pkg
os-bind-rp-1.36_6.pkg
os-bind-rp-1.36_7.pkg
bind920-9.20.26_1.pkg
bind-tools-9.20.26_1.pkg
packagesite/meta catalogue files
resolver-plugins.pub
channel.json
```

The exact names of the `packagesite` and `meta` files are produced by `pkg
repo`; the workflow does not invent them independently.

`channel.json` is a human- and automation-readable audit record. It contains
the series, current plugin version, retained plugin versions, each retained
package's SHA-256, selected release-source commit, BIND compatibility
fingerprint, and upstream/tools/FreeBSD/core provenance identity. It is
supplementary information: the signed `pkg` catalogue and package manifests
remain the installation authority.

Normal package operations choose the newest `os-bind-rp`. An administrator
can select a retained older version from the same channel to roll back.

## BIND baseline policy

The BIND pair is a compatibility payload, not a per-plugin-release product.
The workflow reuses the signed pair already present in the target channel when
its compatibility fingerprint matches. It builds a new BIND pair only when
the pinned BIND version/profile, OPNsense series, FreeBSD release,
architecture, or an explicit security or maintenance decision requires it.

When a new BIND pair is introduced, prior BIND packages remain present while
any of the five retained plugin packages has an exact dependency on them.
They are pruned only after no retained plugin depends on them.

## Publication and retention

1. A reviewed change lands on `release/bind-rp/<series>`.
2. CI validates immutable source provenance and builds a production
   `os-bind-rp` package from that exact source. It reuses a compatible BIND
   pair or performs the pinned BIND build only on an expected cache miss.
3. The trusted signing job downloads the current distribution-channel
   snapshot, validates its manifests and provenance, and constructs the
   intended retained package set.
4. It keeps the five newest production `os-bind-rp` versions, computes the
   exact BIND dependency closure of those packages, writes `channel.json`, and
   runs `pkg repo` with the private key over the complete set.
5. It verifies that the generated catalogue, public key, manifest checksums,
   and package dependency graph exactly match the intended set.
6. The final publication job writes the staged assets to
   `resolver-plugins/repository` under `pkg-<series>` and creates the
   immutable source-release record.

The publisher fails before changing the distribution repository if the
existing channel is malformed, package checksums differ unexpectedly, source
provenance is invalid, a dependency is unavailable, or the generated
catalogue is incomplete.

No rollback package is removed until it falls outside the newest-five set and
is absent from the dependency closure of all retained plugin packages.

## Migration

Migration is additive and reversible:

1. Establish and verify `resolver-plugins/repository` channels for every
   supported series.
2. Validate installation, upgrade, and explicit rollback from a disposable
   OPNsense/FreeBSD test environment using the new channel URL.
3. Change installer/bootstrap documentation and generated configuration to
   use the distribution-repository URL.
4. Continue serving existing source-repository channels for an explicitly
   chosen transition window.
5. Remove old operational channel assets from the source repository only
   after the new channels are verified and the transition window has elapsed.

The migration does not change the installed package name, the explicit
`os-bind` conflict, the minimum OPNsense version of `26.1.11_10`, or the
trusted public-key contract unless a separately reviewed key-rotation change
is approved.

## Verification requirements

Durable CI regression tests must cover:

- retaining exactly five plugin versions and their complete BIND dependency
  closure;
- safely pruning a sixth plugin version and an unreferenced older BIND pair;
- retaining two BIND baselines during a compatibility transition;
- refusing malformed prior channels, absent provenance, unexpected checksums,
  invalid dependency edges, and incomplete catalogues before publication;
- emitting correct `channel.json` content and a matching signed `pkg`
  catalogue;
- publishing only the narrow, immutable plugin-plus-metadata assets in the
  source release; and
- installing the current package and selecting a retained rollback package
  through a single per-series channel.

## Non-goals

- Hosting a general package repository service or introducing object storage.
- Rebuilding BIND for every plugin release.
- Letting release branches publish or sign packages directly.
- Altering signing keys, end-user trust configuration, package names, or the
  official `os-bind` conflict policy.
