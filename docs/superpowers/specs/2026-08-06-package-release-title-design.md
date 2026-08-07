# Package Release Title Design

## Goal

Give package-distribution releases short display titles that communicate their
purpose while preserving every existing Git tag and package repository URL.

## Title mapping

The publisher derives display titles exclusively from validated package
channel tags:

| Tag form | Display title form |
| --- | --- |
| `pkg-<series>` | `<series>-latest` |
| `pkg-<series>-os-bind-rp-<version>` | `<series>-archive-<version>` |

The four current releases therefore become:

- `26.1-latest`
- `26.1-archive-1.36_9`
- `26.7-latest`
- `26.7-archive-1.36_2`

Here `series` is a numeric OPNsense series such as `26.7`, and `version` is a
validated package version such as `1.36_2`.

## Publication behavior

Creation uses the derived title. Publication also edits the title after the
create-or-existing step, so retries and already-existing releases converge on
the required display name. Package assets, tags, signatures, URLs, retention,
and rollback behavior do not change.

GitHub requires one repository-wide `Latest` release. Publication assigns that
badge to the current channel for the highest numeric OPNsense series, while
archive releases are never eligible. Series-specific tags remain authoritative
because the global badge cannot represent every supported series.

## Verification

Unit tests cover current and archive title derivation, invalid tags, and title
editing for an existing Release. After merge, production publication runs for
OPNsense 26.1 and 26.7 must succeed through their public FreeBSD installation
jobs. The live release titles must match the four approved names, while the
four Git tags and public repository URLs remain unchanged.
The repository-wide `Latest` badge must identify `pkg-26.7`, not an archive.
