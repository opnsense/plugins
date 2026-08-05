# BIND 9.20 GSSAPI Selection on FreeBSD 15

## Problem

The shared `build-bind920.sh` wrapper builds the pinned BIND 9.20.26 Ports
recipe with only `DOCS` disabled. On the 26.7 FreeBSD 15.1 builder, the
recipe defaults to `GSSAPI_BASE` while the configured package environment
selects OpenSSL from Ports. FreeBSD Ports rejects that combination before
building `bind-tools`.

The same wrapper successfully produced the 26.1 packages on FreeBSD 14.3,
where the recipe's OS-version-dependent default is `GSSAPI_NONE`.

## Decision

Build both `bind-tools` and `bind920` with `OPTIONS_SET=GSSAPI_NONE` and
`OPTIONS_UNSET='DOCS GSSAPI_BASE'`, while retaining the existing pinned Ports
recipe, OpenSSL selection, package identities, and `DOCS` exclusion.

`GSSAPI_NONE` maps to BIND's `--without-gssapi` configure option. It avoids
mixing base GSSAPI with Ports OpenSSL and makes the build deterministic across
the supported FreeBSD 14.3 and 15.1 builders.

## Alternatives considered

- Select base OpenSSL: rejected because the OPNsense build environment uses
  linked libraries from Ports and this could change package linkage.
- Select a Ports GSSAPI implementation: rejected because it adds an unrelated
  Kerberos dependency and changes the runtime package contract.
- Rely on the Ports default: rejected because it differs between FreeBSD 14
  and 15 and already fails for 26.7.

## Verification

A local ignored regression harness will assert that both BIND package commands
select `GSSAPI_NONE`. The focused local checks will validate shell syntax and
Python helpers. A manual development package workflow for PR #28 will then
build the 26.7 source in its declared FreeBSD 15.1 VM. Only a successful
workflow permits merging the PR and triggering the production release channel.
