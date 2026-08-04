#!/usr/bin/env python3
"""Read and validate immutable os-bind-rp upstream build metadata."""

from __future__ import annotations

import argparse
import json
from pathlib import Path


REQUIRED_FIELDS = (
    "series",
    "upstream_branch",
    "upstream_commit",
    "freebsd_release",
    "core_commit",
    "core_archive_url",
    "core_archive_sha256",
)


def fail(message: str) -> None:
    raise SystemExit(message)


def load_profile(metadata_path: Path, series: str) -> dict[str, str]:
    try:
        metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        fail(f"cannot read upstream metadata: {error}")
    if not isinstance(metadata, dict):
        fail("upstream metadata must be a JSON object")

    profile: dict[str, str] = {}
    for field in REQUIRED_FIELDS:
        value = metadata.get(field)
        if not isinstance(value, str) or not value:
            fail(f"upstream metadata has an invalid {field}")
        profile[field] = value
    if profile["series"] != series:
        fail(f"upstream metadata series {profile['series']} does not match {series}")
    expected_url = (
        "https://github.com/opnsense/core/archive/"
        f"{profile['core_commit']}.tar.gz"
    )
    if profile["core_archive_url"] != expected_url:
        fail("upstream metadata core archive URL is not immutable")
    return profile


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("metadata_path", type=Path)
    parser.add_argument("series")
    parser.add_argument("field", choices=REQUIRED_FIELDS)
    arguments = parser.parse_args()
    print(load_profile(arguments.metadata_path, arguments.series)[arguments.field])


if __name__ == "__main__":
    main()
