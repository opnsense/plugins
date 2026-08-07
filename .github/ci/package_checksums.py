#!/usr/bin/env python3
"""Verify that a target pkg parser retains every archive file checksum."""

from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path


class PackageChecksumError(ValueError):
    """A package archive has no complete target-readable file checksum set."""


def archive_file_checksums(
    pkg_command: str, archive: Path
) -> tuple[tuple[str, str], ...]:
    """Return immutable file/checksum rows emitted by the selected pkg parser."""
    result = subprocess.run(
        [pkg_command, "query", "-F", str(archive), "%Fp|%Fs"],
        check=True,
        text=True,
        capture_output=True,
    )
    rows = []
    for line in result.stdout.splitlines():
        fields = line.split("|", 1)
        if len(fields) != 2:
            raise PackageChecksumError(f"malformed file checksum metadata: {archive.name}")
        rows.append((fields[0], fields[1]))
    return tuple(rows)


def verify_archive(
    pkg_command: str, archive: Path
) -> tuple[tuple[str, str], ...]:
    """Require a non-empty, non-null checksum for every packaged file."""
    rows = archive_file_checksums(pkg_command, archive)
    if not rows or any(not path or checksum in {"", "(null)"} for path, checksum in rows):
        raise PackageChecksumError(
            f"package has incomplete target-readable file checksums: {archive.name}"
        )
    return rows


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--pkg-command", default="pkg")
    parser.add_argument("archives", nargs="+", type=Path)
    arguments = parser.parse_args()
    try:
        for archive in arguments.archives:
            verify_archive(arguments.pkg_command, archive)
    except (PackageChecksumError, OSError, subprocess.CalledProcessError) as error:
        print(f"package checksum verification failed: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
