#!/usr/bin/env python3
"""Read and validate pinned FreeBSD Ports input for bundled BIND."""

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path


FIELDS = (
    "ports_repository",
    "ports_commit",
    "makefile_sha256",
    "distinfo_sha256",
    "distversion",
    "portrevision",
)
COMMIT_PATTERN = re.compile(r"[0-9a-f]{40}")
SHA256_PATTERN = re.compile(r"[0-9a-f]{64}")
DISTVERSION_PATTERN = re.compile(r"9\.20\.[0-9]+")


def load_profile(path: Path) -> dict[str, str | int]:
    try:
        profile = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError(f"cannot read BIND profile: {error}") from error
    if not isinstance(profile, dict) or set(profile) != set(FIELDS):
        raise ValueError("BIND profile does not match the required schema")
    if profile["ports_repository"] != "https://github.com/freebsd/freebsd-ports.git":
        raise ValueError("BIND profile has an unexpected ports repository")
    for field in ("ports_commit", "makefile_sha256", "distinfo_sha256", "distversion"):
        if not isinstance(profile[field], str):
            raise ValueError(f"BIND profile has an invalid {field}")
    if COMMIT_PATTERN.fullmatch(profile["ports_commit"]) is None:
        raise ValueError("BIND profile has an invalid ports_commit")
    for field in ("makefile_sha256", "distinfo_sha256"):
        if SHA256_PATTERN.fullmatch(profile[field]) is None:
            raise ValueError(f"BIND profile has an invalid {field}")
    if DISTVERSION_PATTERN.fullmatch(profile["distversion"]) is None:
        raise ValueError("BIND profile has an invalid distversion")
    if tuple(map(int, profile["distversion"].split("."))) < (9, 20, 26):
        raise ValueError("BIND profile is below the required 9.20.26 release")
    if profile["portrevision"] != 1:
        raise ValueError("BIND profile has an invalid portrevision")
    return profile


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("metadata_path", type=Path)
    parser.add_argument("field", choices=FIELDS)
    arguments = parser.parse_args()
    print(load_profile(arguments.metadata_path)[arguments.field])


if __name__ == "__main__":
    main()
