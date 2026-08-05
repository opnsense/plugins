# Fork model

## Purpose

Resolver Plugins maintains this fork to develop a small number of BIND
features outside the official OPNsense plugin release process while retaining
the ability to incorporate upstream OPNsense plugin updates.

The package produced by this fork is named `os-bind-rp`. It is intentionally
distinct from OPNsense's official `os-bind` package and declares a package
conflict with it. A system must have one or the other installed, never both.

The package build requires OPNsense `26.1.11_10` or newer. That floor includes
the BIND version carrying the named DoT-related fix relied on by this fork.

## Branch responsibilities

`master` is the control plane. It holds shared CI scripts, GitHub Actions
workflows, documentation, and the canonical BIND behavior suite in
`dns/bind/tests`. Pull requests run that suite against every active release
source discovered from `release/bind-rp/<series>` branches, so a new supported
series needs no workflow-matrix edit. It should stay aligned with upstream
OPNsense plugins except for the fork's deliberate control-plane and
package-identity changes.

`release/bind-rp/<series>` branches are the per-OPNsense-series build sources.
Their `.resolver-plugins/upstream.json` records immutable provenance for that
series. Treat a release branch as a stable, reviewed input to a package build;
do not force-push or casually rewrite it. Each release branch carries a thin
`.github/workflows/bind-tests.yml` caller that invokes the canonical read-only
test workflow from `master`; new release bootstraps inherit it through the
workflow overlay.

The synchronizer creates `sync/bind/<series>/<commit>` and
`sync/bootstrap/<series>/<commit>` branches when human review is necessary.
They are generated review inputs, not places for unrelated development.

## Current distribution boundary

The signed package repository is active and approved. The publication workflow
signs packages and publishes the approved release through GitHub Release
publication and the repository, with an established end-user installation
contract. A release-branch build remains a reviewed build input; it does not
independently authorize publication or a change to the installation contract.

Do not alter signing, GitHub Release publication, package-repository
publication, tokens, secrets, or the end-user installation contract without
explicit maintainer authorization.
