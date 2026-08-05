# os-bind-rp Repository Guide for Coding Agents

This repository is an OPNsense plugins fork maintained by Resolver Plugins.
Treat the upstream OPNsense source as the compatibility baseline and keep this
fork's changes narrow and reviewable.

## Design at a glance

The fork exists to maintain `os-bind-rp`, a community-maintained BIND plugin
that can follow upstream OPNsense plugin releases while carrying a small,
reviewed feature set. `os-bind-rp` intentionally replaces official `os-bind`:
the packages conflict and must never be installed together.

`master` is the control plane. It contains documentation and CI code that
discovers compatible upstream releases. Each `release/bind-rp/<series>` branch
is a reviewed build source for one OPNsense series. The release branch records
immutable plugin, tools, FreeBSD, core archive, and checksum provenance in
`.resolver-plugins/upstream.json`.

Automation is deliberately conservative. An upstream BIND change becomes a
review PR; it does not silently advance a release branch. An unchanged BIND
tree for a new series can receive a temporary build artifact. No package
repository, GitHub Release, Pages site, signing, or end-user installation
mechanism exists yet.

## Repository map

- `dns/bind/`: the `os-bind-rp` plugin package definition and fork-specific
  plugin changes.
- `.resolver-plugins/`: release build metadata and the synchronization overlay
  manifest.
- `.github/ci/`: metadata validation, OPNsense repository setup, package build,
  synchronization planning, and safe GitHub publication helpers.
- `.github/workflows/`: the daily/manual synchronizer and signed package
  publication workflows.
- `docs/`: maintainer reference material. Start with
  [docs/README.md](docs/README.md) before changing build, synchronization, or
  package-related code.

## Non-negotiable rules

- `os-bind-rp` is a replacement for official `os-bind`, not a companion
  package. Keep `PLUGIN_NAME=bind-rp` and `PLUGIN_CONFLICTS=bind` intact unless
  the maintainer explicitly changes the package policy.
- Preserve the documented minimum OPNsense version of `26.1.11_10`. It is the
  minimum needed for the BIND/DoT fix used by this fork.
- Keep fork-specific plugin changes small and isolated under `dns/bind`.
  Do not take unrelated upstream plugin changes into this fork.
- `master` contains the CI control plane. Per-release source and immutable
  build metadata belong on `release/bind-rp/<series>` branches. Never rewrite
  those branches or generated `sync/bind/*` and `sync/bootstrap/*` refs.
- Treat every field in `.resolver-plugins/upstream.json` as immutable build
  provenance. A profile must use a matching `opnsense/tools` numeric tag and
  the `OS?=` value from `config/<series>/build.conf`.
- Do not weaken provenance checks, pin checks, package fingerprint checks, or
  the explicit `os-bind` conflict to make a build pass.
- Keep durable CI helper and workflow regression tests in
  `.github/ci/ci-tests/`; use local Git and command fixtures rather than live
  services. Keep the canonical BIND behavior suite in `dns/bind/tests/` on
  `master`; its PR workflow materializes and tests every active
  `release/bind-rp/<series>` source. Use the ignored `.github/ci-local/`
  directory only for temporary CI discovery and investigation harnesses;
  never stage or commit anything under it.
- The signed package repository is an approved system. Do not alter its
  GitHub Release publication, signing boundary, tokens, secrets, or end-user
  installation contract without explicit maintainer authorization.
- Verify CI changes with the focused local checks in
  [docs/building.md](docs/building.md), and run the affected workflow manually
  only when authorized. Report the workflow URL and its actual outcome.
- A code review agent must be run on pr's when they are "ready", as a fianl step
  befor merge. High priority items must be resolved before a pr can be merged.
  Perform a remeditation and review cycle util the items are resolved and the
  code-review agent approve the pr as ready, when no high priority items are
  observed.

- An agent must be used to review generated plans, and high priority items must be
  resolved before a plan can be approved and implemented.

## Documentation updates

Update the relevant maintainer guide in the same change when behavior or a
workflow contract changes. Keep the root README user-oriented; operational
detail belongs under `docs/`. The focused references are:

- [Fork model](docs/fork-model.md) for package and branch policy.
- [Building](docs/building.md) for local build inputs and verification.
- [Upstream synchronization](docs/upstream-sync.md) for CI decisions and
  operational recovery.
