# BIND Package Reuse Design

## Goal

Allow `os-bind-rp` development and production builds to reuse the current
series' already-published BIND packages when the BIND build inputs are
unchanged. A full BIND build remains the safe fallback for a new or changed
input set.

## Scope

The change affects only the GitHub Actions package-release workflow and its
maintainer tooling. It does not change the package repository URL, package
names, BIND version floor, signing key, or the three-package stable-channel
contract.

## Compatibility identity

`tools/ci/bind920_profile.py` will produce a canonical compatibility
fingerprint. It is the SHA-256 digest of canonical JSON containing:

- schema version;
- OPNsense series;
- FreeBSD release used by the runner;
- target architecture (`x86_64`); and
- the complete pinned `.resolver-plugins/bind920.json` object.

This identity deliberately excludes plugin source and OPNsense plugin/core
commits. A plugin-only or unrelated OPNsense source update may reuse BIND.
Different OPNsense series, FreeBSD releases, architectures, Ports commits,
recipe hashes, BIND versions, or BIND port revisions cannot reuse it.

## Published provenance

Each stable `pkg-<series>` Release will include
`bind920-provenance.json` in addition to the existing packages, catalogue,
and public key. It records the schema version, compatibility fingerprint,
series, FreeBSD release, architecture, and exact identities and filenames of
the `bind-tools` and `bind920` packages.

The provenance file is release metadata rather than part of the `pkg`
catalogue. It selects a reuse candidate only; it never establishes package
trust. The build VM fetches the candidate from the normal stable package URL
using the repository public key committed on `master`, so `pkg` verifies the
signed catalogue and package checksums before installation.

## Build flow

1. The workflow materializes the selected release source as it does today and
   derives the compatibility fingerprint from trusted control-plane tooling on
   `master` plus the selected series profile.
2. `build-bind920.sh` invokes a focused reuse helper before any Ports work.
   The helper retrieves the fixed stable-channel provenance asset for the
   current series and compares its fingerprint and package identities with the
   current build inputs.
3. On a match, the helper configures a temporary, signed `pkg` repository for
   `https://github.com/resolver-plugins/plugins/releases/download/pkg-<series>`,
   fetches the two BIND packages, verifies their identities, installs them in
   the disposable build VM, and copies them plus their provenance file to the
   artifact directory.
4. On a missing Release/provenance file or an incompatibility, the helper
   returns a documented cache-miss status. `build-bind920.sh` performs the
   existing pinned Ports build and writes fresh provenance into the artifact
   directory.
5. `build-os-bind-rp.sh` runs unchanged from its perspective: BIND is already
   installed and it records the exact local `bind920` dependency in the plugin
   package.
6. Production signing copies the provenance file beside the generated flat
   repository before publication. The existing Release-asset replacement
   behavior preserves it while removing obsolete assets.

Development pre-releases use the same decision and attach the reused BIND
packages alongside the devel plugin artifact. They remain unsigned review
artifacts and are not promoted to stable channels.

## Failure handling

- A cache miss is normal and falls back to the existing build.
- An invalid provenance document, failed signed-catalogue verification,
  missing expected package, manifest identity mismatch, or installation
  failure is a hard error. CI must not silently build or install an
  unverified candidate after a matching provenance file has been selected.
- No GitHub Release is required for the first build of a series; it is a cache
  miss and the resulting production publication establishes the reusable
  provenance.

## Verification

Local tests will cover canonical fingerprint generation, compatible and
incompatible provenance decisions, and required provenance publication.
Shell syntax and Python compilation remain required checks. GitHub Actions
verification will use two manual development runs for an established series:
the first may build BIND if its provenance is absent; the next must log reuse,
produce all three expected package artifacts, and avoid the Ports BIND build.
A production run must publish the same stable three-package channel plus the
provenance asset, with a valid signed catalogue consumable by HA-2.
