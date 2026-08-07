#!/usr/bin/env python3
"""Read and validate pinned FreeBSD Ports input for bundled BIND."""

from __future__ import annotations

import argparse
import hashlib
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
SERIES_PATTERN = re.compile(r"[0-9]+\.[0-9]+")
FREEBSD_RELEASE_PATTERN = re.compile(r"[0-9]+\.[0-9]+")
ARCHITECTURE_PATTERN = re.compile(r"[a-z0-9_]+")
PROVENANCE_SCHEMA = 1
PACKAGE_ORIGINS = {
    "bind-tools": "dns/bind-tools",
    "bind920": "dns/bind920",
}


def validate_profile(profile: object) -> dict[str, str | int]:
    """Validate one pinned BIND profile object."""
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
    if (
        not isinstance(profile["portrevision"], int)
        or isinstance(profile["portrevision"], bool)
        or profile["portrevision"] <= 0
    ):
        raise ValueError("BIND profile has an invalid portrevision")
    return profile


def load_profile(path: Path) -> dict[str, str | int]:
    try:
        profile = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError(f"cannot read BIND profile: {error}") from error
    return validate_profile(profile)


def package_version(profile: object) -> str:
    """Return the package version implied by a validated Ports profile."""
    profile = validate_profile(profile)
    return f"{profile['distversion']}_{profile['portrevision']}"


def compatibility_fingerprint(
    profile: object, series: str, freebsd_release: str, architecture: str
) -> str:
    """Hash all inputs that determine whether BIND packages are reusable."""
    profile = validate_profile(profile)
    if SERIES_PATTERN.fullmatch(series) is None:
        raise ValueError("invalid OPNsense series")
    if FREEBSD_RELEASE_PATTERN.fullmatch(freebsd_release) is None:
        raise ValueError("invalid FreeBSD release")
    if ARCHITECTURE_PATTERN.fullmatch(architecture) is None:
        raise ValueError("invalid target architecture")
    inputs = {
        "schema": PROVENANCE_SCHEMA,
        "series": series,
        "freebsd_release": freebsd_release,
        "architecture": architecture,
        "bind_profile": profile,
    }
    encoded = json.dumps(inputs, sort_keys=True, separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(encoded).hexdigest()


def build_provenance(
    profile: object,
    series: str,
    freebsd_release: str,
    architecture: str,
    packages: object,
) -> dict[str, object]:
    """Describe the exact BIND pair available for one compatibility identity."""
    profile = validate_profile(profile)
    fingerprint = compatibility_fingerprint(profile, series, freebsd_release, architecture)
    if not isinstance(packages, dict) or set(packages) != set(PACKAGE_ORIGINS):
        raise ValueError("BIND provenance has an invalid package set")
    expected_version = package_version(profile)
    validated_packages: dict[str, dict[str, str]] = {}
    for package_name, origin in PACKAGE_ORIGINS.items():
        package = packages[package_name]
        if not isinstance(package, dict) or set(package) != {"name", "version", "origin", "filename"}:
            raise ValueError(f"{package_name} package has an invalid schema")
        expected = {
            "name": package_name,
            "version": expected_version,
            "origin": origin,
            "filename": f"{package_name}-{expected_version}.pkg",
        }
        if package != expected:
            raise ValueError(f"{package_name} package does not match the BIND profile")
        validated_packages[package_name] = package
    return {
        "schema": PROVENANCE_SCHEMA,
        "fingerprint": fingerprint,
        "series": series,
        "freebsd_release": freebsd_release,
        "architecture": architecture,
        "packages": validated_packages,
    }


def write_provenance(
    output: Path,
    profile: object,
    series: str,
    freebsd_release: str,
    architecture: str,
    bind_tools: Path,
    bind920: Path,
) -> None:
    """Write canonical provenance for two finished BIND package archives."""
    if not bind_tools.is_file() or not bind920.is_file():
        raise ValueError("BIND package archive does not exist")
    packages = {
        "bind-tools": {
            "name": "bind-tools",
            "version": package_version(profile),
            "origin": "dns/bind-tools",
            "filename": bind_tools.name,
        },
        "bind920": {
            "name": "bind920",
            "version": package_version(profile),
            "origin": "dns/bind920",
            "filename": bind920.name,
        },
    }
    provenance = build_provenance(profile, series, freebsd_release, architecture, packages)
    output.write_text(json.dumps(provenance, sort_keys=True, indent=2) + "\n", encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("metadata_path", type=Path)
    parser.add_argument("command", choices=(*FIELDS, "package_version", "fingerprint", "provenance"))
    parser.add_argument("series", nargs="?")
    parser.add_argument("freebsd_release", nargs="?")
    parser.add_argument("--architecture", default="x86_64")
    parser.add_argument("--bind-tools", type=Path)
    parser.add_argument("--bind920", type=Path)
    parser.add_argument("--output", type=Path)
    arguments = parser.parse_args()
    profile = load_profile(arguments.metadata_path)
    if arguments.command in FIELDS:
        print(profile[arguments.command])
        return
    if arguments.command == "package_version":
        print(package_version(profile))
        return
    if arguments.series is None or arguments.freebsd_release is None:
        parser.error(f"{arguments.command} requires series and freebsd_release")
    if arguments.command == "fingerprint":
        print(compatibility_fingerprint(profile, arguments.series, arguments.freebsd_release, arguments.architecture))
        return
    if arguments.bind_tools is None or arguments.bind920 is None or arguments.output is None:
        parser.error("provenance requires --bind-tools, --bind920, and --output")
    write_provenance(
        arguments.output,
        profile,
        arguments.series,
        arguments.freebsd_release,
        arguments.architecture,
        arguments.bind_tools,
        arguments.bind920,
    )


if __name__ == "__main__":
    main()
