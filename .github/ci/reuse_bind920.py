#!/usr/bin/env python3
"""Reuse the signed stable-channel BIND package pair in a build VM."""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


sys.path.insert(0, str(Path(__file__).resolve().parent))
import bind920_profile
import package_checksums
import target_pkg


CACHE_MISS = 3
PROVENANCE_NAME = "bind920-provenance.json"
REPOSITORY_NAME = "resolver-plugins"
PACKAGE_FIELDS = {"name", "version", "origin", "filename"}
PROVENANCE_FIELDS = {
    "schema", "fingerprint", "series", "freebsd_release", "architecture",
    "package_creator", "packages",
}


class CacheMiss(Exception):
    """The stable channel has no package pair compatible with this build."""


def select_candidate(
    provenance: object,
    profile: object,
    series: str,
    freebsd_release: str,
    architecture: str,
    package_creator: object,
) -> dict[str, dict[str, str]]:
    """Validate and select a compatible BIND package pair from provenance."""
    if not isinstance(provenance, dict):
        raise ValueError("BIND provenance has an invalid schema")
    if (
        provenance.get("schema") != bind920_profile.PROVENANCE_SCHEMA
        or provenance.get("package_creator") != package_creator
    ):
        raise CacheMiss("stable BIND package creator differs")
    if not isinstance(provenance, dict) or set(provenance) != PROVENANCE_FIELDS:
        raise ValueError("BIND provenance has an invalid schema")
    if provenance["schema"] != bind920_profile.PROVENANCE_SCHEMA:
        raise ValueError("BIND provenance has an unsupported schema")
    if not isinstance(provenance["fingerprint"], str):
        raise ValueError("BIND provenance has an invalid fingerprint")
    if not all(isinstance(provenance[field], str) for field in ("series", "freebsd_release", "architecture")):
        raise ValueError("BIND provenance has invalid compatibility fields")
    if (
        provenance["series"] != series
        or provenance["freebsd_release"] != freebsd_release
        or provenance["architecture"] != architecture
    ):
        raise CacheMiss("stable BIND package compatibility fields differ")
    fingerprint = bind920_profile.compatibility_fingerprint(
        profile, series, freebsd_release, architecture, package_creator
    )
    if provenance["fingerprint"] != fingerprint:
        raise CacheMiss("stable BIND package fingerprint differs")
    packages = provenance["packages"]
    if not isinstance(packages, dict) or any(
        not isinstance(package, dict) or set(package) != PACKAGE_FIELDS
        for package in packages.values()
    ):
        raise ValueError("BIND provenance has invalid package records")
    expected = bind920_profile.build_provenance(
        profile, series, freebsd_release, architecture, package_creator, packages
    )
    if provenance != expected:
        raise ValueError("BIND provenance does not match the current profile")
    return packages


def download_provenance(channel_url: str) -> object:
    """Download metadata; a missing asset is the expected bootstrap miss."""
    request = Request(f"{channel_url.rstrip('/')}/{PROVENANCE_NAME}")
    try:
        with urlopen(request) as response:
            return json.loads(response.read().decode("utf-8"))
    except HTTPError as error:
        if error.code == 404:
            raise CacheMiss("stable BIND provenance asset is absent") from error
        raise RuntimeError(f"cannot download BIND provenance: HTTP {error.code}") from error
    except (OSError, URLError, UnicodeDecodeError, json.JSONDecodeError) as error:
        raise RuntimeError(f"cannot read BIND provenance: {error}") from error


def run(command: list[str], **kwargs: object) -> subprocess.CompletedProcess[str]:
    """Run one external command with text diagnostics available on failure."""
    return subprocess.run(command, check=True, text=True, **kwargs)


def package_identity(pkg_command: str, package: Path) -> tuple[str, str, str]:
    """Read the identity recorded inside one package archive."""
    result = run(
        [pkg_command, "query", "-F", str(package), "%n\t%v\t%o"], capture_output=True
    )
    fields = result.stdout.strip().split("\t")
    if len(fields) != 3 or not all(fields):
        raise RuntimeError(f"cannot read package identity: {package.name}")
    return fields[0], fields[1], fields[2]


def installed_package_identity(pkg_command: str, package_name: str) -> tuple[str, str, str]:
    """Read one installed package identity without relying on compound predicates."""
    result = run(
        [pkg_command, "query", "-e", f"%n = {package_name}", "%n\t%v\t%o"],
        capture_output=True,
    )
    lines = result.stdout.strip().splitlines()
    if len(lines) != 1:
        raise RuntimeError(f"cannot read installed package identity: {package_name}")
    fields = lines[0].split("\t")
    if len(fields) != 3 or not all(fields):
        raise RuntimeError(f"cannot read installed package identity: {package_name}")
    return fields[0], fields[1], fields[2]


def verify_archive_compatibility(pkg_static_command: str, archive: Path) -> None:
    """Turn an old target parser's missing file sums into an ordinary rebuild."""
    try:
        package_checksums.verify_archive(pkg_static_command, archive)
    except package_checksums.PackageChecksumError as error:
        raise CacheMiss("stable BIND package lacks target-readable checksums") from error


def write_repository_config(directory: Path, channel_url: str, public_key: Path) -> Path:
    """Write an isolated pkg configuration pinned to the published key."""
    if not public_key.is_file():
        raise RuntimeError("Resolver Plugins public key does not exist")
    config = directory / f"{REPOSITORY_NAME}.conf"
    config.write_text(
        f"{REPOSITORY_NAME}: {{\n"
        f"  url: \"{channel_url.rstrip('/')}\",\n"
        "  mirror_type: \"none\",\n"
        "  signature_type: \"pubkey\",\n"
        f"  pubkey: \"{public_key}\",\n"
        "  enabled: yes\n"
        "}\n",
        encoding="utf-8",
    )
    return config.parent


def fetch_package(pkg_options: list[str], downloads: Path, filename: str) -> None:
    """Fetch one exact archive without allowing an interactive confirmation."""
    run([
        *pkg_options, "fetch", "-y", "-r", REPOSITORY_NAME, "-o", str(downloads),
        filename.removesuffix(".pkg"),
    ])


def downloaded_archive(downloads: Path, filename: str) -> Path:
    """Locate a named pkg fetch result across supported output layouts."""
    candidates = (downloads / filename, downloads / "All" / filename)
    for candidate in candidates:
        if candidate.is_file():
            return candidate
    locations = ", ".join(str(candidate) for candidate in candidates)
    raise RuntimeError(f"pkg did not fetch {filename}; checked {locations}")


def reuse(
    profile_path: Path,
    series: str,
    freebsd_release: str,
    architecture: str,
    output: Path,
    channel_url: str,
    public_key: Path,
    pkg_command: str,
    pkg_static_command: str,
    package_creator: object,
) -> None:
    """Fetch, verify, install, and copy a compatible BIND package pair."""
    profile = bind920_profile.load_profile(profile_path)
    provenance = download_provenance(channel_url)
    packages = select_candidate(
        provenance,
        profile,
        series,
        freebsd_release,
        architecture,
        package_creator,
    )
    with tempfile.TemporaryDirectory() as temporary_directory:
        temporary = Path(temporary_directory)
        repository_directory = temporary / "repos"
        repository_directory.mkdir()
        write_repository_config(repository_directory, channel_url, public_key)
        pkg_options = [pkg_command, "-o", f"REPOS_DIR={repository_directory}"]
        run([*pkg_options, "update", "-f", "-r", REPOSITORY_NAME])
        downloads = temporary / "downloads"
        for package_name in ("bind-tools", "bind920"):
            package = packages[package_name]
            fetch_package(pkg_options, downloads, package["filename"])
        archives = {
            package_name: downloaded_archive(downloads, package["filename"])
            for package_name, package in packages.items()
        }
        for package_name, package in packages.items():
            archive = archives[package_name]
            identity = package_identity(pkg_command, archive)
            expected_identity = (package["name"], package["version"], package["origin"])
            if identity != expected_identity:
                raise RuntimeError(f"downloaded {package_name} package identity does not match provenance")
            verify_archive_compatibility(pkg_static_command, archive)
        for package_name in ("bind-tools", "bind920"):
            run([pkg_command, "add", str(archives[package_name])])
        for package_name, package in packages.items():
            installed_identity = installed_package_identity(pkg_command, package_name)
            expected_identity = (package["name"], package["version"], package["origin"])
            if installed_identity != expected_identity:
                raise RuntimeError(f"installed {package_name} package identity does not match provenance")
        output.mkdir(parents=True, exist_ok=True)
        for archive in archives.values():
            shutil.copy2(archive, output / archive.name)
        (output / PROVENANCE_NAME).write_text(
            json.dumps(provenance, sort_keys=True, indent=2) + "\n", encoding="utf-8"
        )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("profile", type=Path)
    parser.add_argument("series")
    parser.add_argument("freebsd_release")
    parser.add_argument("output", type=Path)
    parser.add_argument("--architecture", default="x86_64")
    parser.add_argument("--channel-url", required=True)
    parser.add_argument("--public-key", type=Path, required=True)
    parser.add_argument("--pkg-command", default="pkg")
    parser.add_argument("--pkg-static-command", default="/usr/local/sbin/pkg-static")
    parser.add_argument("--target-pkg-metadata", type=Path, required=True)
    arguments = parser.parse_args()
    try:
        package_creator = target_pkg.load_target(
            arguments.target_pkg_metadata, arguments.series
        ).record()
        reuse(
            arguments.profile,
            arguments.series,
            arguments.freebsd_release,
            arguments.architecture,
            arguments.output,
            arguments.channel_url,
            arguments.public_key,
            arguments.pkg_command,
            arguments.pkg_static_command,
            package_creator,
        )
    except CacheMiss as error:
        print(f"BIND reuse cache miss: {error}", file=sys.stderr)
        return CACHE_MISS
    except (RuntimeError, ValueError, subprocess.CalledProcessError) as error:
        print(f"BIND reuse failed: {error}", file=sys.stderr)
        return 1
    print("Reused signed stable-channel BIND packages")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
