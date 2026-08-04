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
workflows, and documentation. It should stay aligned with upstream OPNsense
plugins except for the fork's deliberate control-plane and package-identity
changes.

`release/bind-rp/<series>` branches are the per-OPNsense-series build sources.
Their `.resolver-plugins/upstream.json` records immutable provenance for that
series. Treat a release branch as a stable, reviewed input to a package build;
do not force-push or casually rewrite it.

The synchronizer creates `sync/bind/<series>/<commit>` and
`sync/bootstrap/<series>/<commit>` branches when human review is necessary.
They are generated review inputs, not places for unrelated development.

## Current distribution boundary

The current CI can build `os-bind-rp` and upload a temporary GitHub Actions
artifact. It does not publish a package repository, a GitHub Release, a Pages
site, signatures, or end-user installation instructions. Those are separate
future work and must not be implied by release-branch builds.
