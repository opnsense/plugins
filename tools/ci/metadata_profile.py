#!/usr/bin/env python3
"""Read and validate immutable os-bind-rp upstream build metadata."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
import re


REQUIRED_FIELDS = (
    "series",
    "upstream_branch",
    "upstream_commit",
    "tools_tag",
    "freebsd_release",
    "core_commit",
    "core_archive_url",
    "core_archive_sha256",
)
COMMIT_PATTERN = re.compile(r"[0-9a-f]{40}")
SHA256_PATTERN = re.compile(r"[0-9a-f]{64}")
SERIES_PATTERN = re.compile(r"\d+\.\d+")
FREEBSD_RELEASE_PATTERN = re.compile(r"[1-9]\d*(?:\.\d+)?")


def tools_tag_matches_series(tools_tag: str, series: str) -> bool:
    return re.fullmatch(
        rf"{re.escape(series)}(?:\.(?:0|[1-9]\d*))?",
        tools_tag,
    ) is not None


def fail(message: str) -> None:
    raise SystemExit(message)


def validate_core_archive(
    core_commit: str,
    core_archive_url: str,
    core_archive_sha256: str,
) -> None:
    if COMMIT_PATTERN.fullmatch(core_commit) is None:
        raise ValueError("upstream metadata core_commit is not an immutable commit hash")
    if SHA256_PATTERN.fullmatch(core_archive_sha256) is None:
        raise ValueError(
            "upstream metadata core_archive_sha256 is not an immutable SHA-256 hash"
        )
    expected_url = (
        "https://github.com/opnsense/core/archive/"
        f"{core_commit}.tar.gz"
    )
    if core_archive_url != expected_url:
        raise ValueError("upstream metadata core archive URL is not immutable")


def validate_profile(metadata: object, series: str) -> dict[str, str]:
    if not isinstance(metadata, dict):
        raise ValueError("upstream metadata must be a JSON object")
    if set(metadata) != set(REQUIRED_FIELDS):
        raise ValueError("upstream metadata does not match the required schema")
    if SERIES_PATTERN.fullmatch(series) is None:
        raise ValueError(f"invalid upstream metadata series {series}")

    profile: dict[str, str] = {}
    for field in REQUIRED_FIELDS:
        value = metadata.get(field)
        if not isinstance(value, str) or not value:
            raise ValueError(f"upstream metadata has an invalid {field}")
        profile[field] = value
    if profile["series"] != series:
        raise ValueError(
            f"upstream metadata series {profile['series']} does not match {series}"
        )
    if profile["upstream_branch"] != f"stable/{series}":
        raise ValueError("upstream metadata branch does not match its series")
    if COMMIT_PATTERN.fullmatch(profile["upstream_commit"]) is None:
        raise ValueError(
            "upstream metadata upstream_commit is not an immutable commit hash"
        )
    if not tools_tag_matches_series(profile["tools_tag"], series):
        raise ValueError("upstream metadata tools_tag does not match its series")
    if FREEBSD_RELEASE_PATTERN.fullmatch(profile["freebsd_release"]) is None:
        raise ValueError("upstream metadata has an invalid freebsd_release")
    validate_core_archive(
        profile["core_commit"],
        profile["core_archive_url"],
        profile["core_archive_sha256"],
    )
    return profile


def load_profile(metadata_path: Path, series: str) -> dict[str, str]:
    try:
        metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        fail(f"cannot read upstream metadata: {error}")
    try:
        return validate_profile(metadata, series)
    except ValueError as error:
        fail(str(error))


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("metadata_path", type=Path)
    parser.add_argument("series")
    parser.add_argument("field", choices=REQUIRED_FIELDS)
    arguments = parser.parse_args()
    print(load_profile(arguments.metadata_path, arguments.series)[arguments.field])


if __name__ == "__main__":
    main()
