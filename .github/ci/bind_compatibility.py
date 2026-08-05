#!/usr/bin/env python3
"""Validate and apply the static BIND compatibility policy."""

from __future__ import annotations

import argparse
import json
from collections.abc import Callable
from pathlib import Path


IDENTITY_FIELDS = {"name", "origin"}
POLICY_FIELDS = {"schema", "minimum_version", "bind920", "bind_tools", "series"}


def validate_policy(policy: object) -> dict[str, object]:
    """Return a strict, reviewable compatibility policy or reject it."""
    if not isinstance(policy, dict) or set(policy) != POLICY_FIELDS:
        raise ValueError("BIND compatibility policy has an invalid schema")
    if policy["schema"] != 1 or not isinstance(policy["minimum_version"], str):
        raise ValueError("BIND compatibility policy has an invalid version")
    for field in ("bind920", "bind_tools"):
        identity = policy[field]
        if not isinstance(identity, dict) or set(identity) != IDENTITY_FIELDS or not all(
            isinstance(identity[value], str) and identity[value] for value in IDENTITY_FIELDS
        ):
            raise ValueError(f"BIND compatibility policy has an invalid {field} identity")
    series = policy["series"]
    if not isinstance(series, dict) or not series or not all(
        isinstance(name, str) and isinstance(release, str) and name and release
        for name, release in series.items()
    ):
        raise ValueError("BIND compatibility policy has invalid series metadata")
    return policy


def freebsd_release(policy: dict[str, object], series: str) -> str:
    """Return the policy's expected FreeBSD release for one supported series."""
    releases = policy["series"]
    assert isinstance(releases, dict)
    release = releases.get(series)
    if not isinstance(release, str):
        raise ValueError("unsupported OPNsense series")
    return release


def is_eligible(
    policy: dict[str, object],
    bind920: tuple[str, str, str],
    bind_tools: tuple[str, str, str],
    compare_versions: Callable[[str, str], str],
) -> bool:
    """Return whether an installed OPNsense BIND satisfies every requirement."""
    bind920_policy = policy["bind920"]
    bind_tools_policy = policy["bind_tools"]
    minimum_version = policy["minimum_version"]
    assert isinstance(bind920_policy, dict)
    assert isinstance(bind_tools_policy, dict)
    assert isinstance(minimum_version, str)
    if (bind920[0], bind920[2]) != (bind920_policy["name"], bind920_policy["origin"]):
        return False
    if (bind_tools[0], bind_tools[2]) != (bind_tools_policy["name"], bind_tools_policy["origin"]):
        return False
    return compare_versions(bind920[1], minimum_version) in {"=", ">"}


def load_policy(path: Path) -> dict[str, object]:
    """Load a policy file without accepting a partial or implicit contract."""
    return validate_policy(json.loads(path.read_text(encoding="utf-8")))


def main() -> None:
    parser = argparse.ArgumentParser()
    commands = parser.add_subparsers(dest="command", required=True)
    minimum = commands.add_parser("minimum-version")
    minimum.add_argument("policy", type=Path)
    minimum.add_argument("series")
    identity = commands.add_parser("identity")
    identity.add_argument("policy", type=Path)
    identity.add_argument("series")
    identity.add_argument("package", choices=("bind920", "bind_tools"))
    arguments = parser.parse_args()
    policy = load_policy(arguments.policy)
    if arguments.command == "minimum-version":
        freebsd_release(policy, arguments.series)
        print(policy["minimum_version"])
    else:
        freebsd_release(policy, arguments.series)
        identity = policy[arguments.package]
        assert isinstance(identity, dict)
        print(f"{identity['name']}\t{identity['origin']}")


if __name__ == "__main__":
    main()
