# Simplified Manual Installation Design

## Goal

Make the root README's manual `os-bind-rp` installation directions shorter by
having the operator explicitly select the OPNsense series.

## Scope

Replace the shell commands that derive and validate the installed OPNsense
version with this explicit input:

```sh
series=26.1
```

The remaining commands continue to derive `channel="pkg-$series"`, install the
repository public key, configure the signed package repository, refresh its
catalogue, and install `os-bind-rp`.

The surrounding prose continues to tell operators to choose the series that
matches their installed OPNsense release and retains the existing minimum
OPNsense-version warning. The interactive installer and its automatic checks
are outside this change.

## Verification

A short-term implementation check confirms that the manual block contains the
explicit assignment and no longer contains `opnsense-version`, `pkg version
-t`, or the shell `case` validation. This wording-specific check is not
committed. The existing documentation tests, complete CI helper suite, and
whitespace checks must pass.
