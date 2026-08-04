# os-bind-rp Repository Guide for Coding Agents

This repository is an OPNsense plugins fork maintained by Resolver Plugins.
Treat the upstream OPNsense source as the compatibility baseline and keep this
fork's changes narrow and reviewable.

Read [docs/README.md](docs/README.md) before changing build, synchronization,
or package-related code.  The focused maintainer guides are the source of truth
for the fork model, local builds, and upstream synchronization.

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
- Keep CI discovery and regression harnesses local unless the maintainer asks
  to commit them. Do not add `tools/ci/tests/` to normal repository commits.
- Do not add package-repository publication, GitHub Pages, releases, signing,
  tokens, secrets, or installation instructions without explicit maintainer
  authorization. Current CI uploads only temporary Actions artifacts.
- Verify CI changes with the focused local checks in
  [docs/building.md](docs/building.md), and run the affected workflow manually
  only when authorized. Report the workflow URL and its actual outcome.

## Documentation updates

Update the relevant maintainer guide in the same change when behavior or a
workflow contract changes. Keep the root README user-oriented; operational
detail belongs under `docs/`.
