#!/usr/bin/env python3
"""Stage and publish os-bind-rp repositories as GitHub Release assets."""

from __future__ import annotations

import argparse
import shutil
import re
import subprocess
from pathlib import Path


SERIES_PATTERN = re.compile(r"[0-9]+\.[0-9]+")
PACKAGE_PATTERNS = {
    "bind-tools": re.compile(r"bind-tools-9\.20\.26_1\.pkg"),
    "bind920": re.compile(r"bind920-9\.20\.26_1\.pkg"),
    "os-bind-rp": re.compile(r"os-bind-rp-(?!devel-).+\.pkg"),
}
EXPECTED_PACKAGES = {
    "bind-tools": ("bind-tools", "9.20.26_1", "dns/bind-tools"),
    "bind920": ("bind920", "9.20.26_1", "dns/bind920"),
    "os-bind-rp": ("os-bind-rp", None, "opnsense/os-bind-rp"),
}


def channel_tag(series: str) -> str:
    """Return the fixed GitHub Release tag for a numeric OPNsense series."""
    if SERIES_PATTERN.fullmatch(series) is None:
        raise ValueError("invalid series")
    return f"pkg-{series}"


def select_packages(directory: Path) -> list[Path]:
    """Select exactly one production package from every required family."""
    selected = []
    for family, pattern in PACKAGE_PATTERNS.items():
        packages = sorted(
            path for path in directory.glob(f"{family}-*.pkg")
            if pattern.fullmatch(path.name)
        )
        if not packages:
            if list(directory.glob(f"{family}-devel-*.pkg")):
                raise ValueError(f"development {family} package is not a production package")
            raise ValueError(f"missing production {family} package")
        if len(packages) != 1:
            raise ValueError(f"expected exactly one production {family} package")
        selected.extend(packages)
    return selected


def query_package(package: Path, pkg_command: str) -> tuple[tuple[str, str, str, str], set[tuple[str, str, str]]]:
    """Read the manifest identity and dependency edges from one package file."""
    identity = subprocess.run(
        [pkg_command, "query", "-F", str(package), "%n\t%v\t%o\t%q"],
        check=True,
        capture_output=True,
        text=True,
    ).stdout.strip().split("\t")
    if len(identity) != 4 or not all(identity):
        raise ValueError(f"cannot read package identity: {package.name}")
    dependencies = subprocess.run(
        [pkg_command, "query", "-F", str(package), "%dn\t%do\t%dv"],
        check=True,
        capture_output=True,
        text=True,
    ).stdout.splitlines()
    edges = set()
    for dependency in dependencies:
        fields = dependency.split("\t")
        if len(fields) != 3 or not all(fields):
            raise ValueError(f"cannot read package dependency: {package.name}")
        edges.add(tuple(fields))
    return tuple(identity), edges


def validate_package_manifests(packages: list[Path], pkg_command: str) -> None:
    """Reject archives whose manifests do not form the required package chain."""
    common_abi = None
    manifests = {}
    for family, package in zip(PACKAGE_PATTERNS, packages, strict=True):
        identity, dependencies = query_package(package, pkg_command)
        name, version, origin, abi = identity
        expected_name, expected_version, expected_origin = EXPECTED_PACKAGES[family]
        if (name, origin) != (expected_name, expected_origin) or (
            expected_version is not None and version != expected_version
        ):
            raise ValueError(f"unexpected {family} package manifest")
        if common_abi is None:
            common_abi = abi
        elif abi != common_abi:
            raise ValueError("package ABI does not match the bundled BIND packages")
        manifests[family] = (version, dependencies)
    if ("bind-tools", "dns/bind-tools", "9.20.26_1") not in manifests["bind920"][1]:
        raise ValueError("bind920 does not depend on bundled bind-tools")
    if ("bind920", "dns/bind920", "9.20.26_1") not in manifests["os-bind-rp"][1]:
        raise ValueError("os-bind-rp does not depend on bundled bind920")


def stage_repository(packages_directory: Path, output: Path, private_key: Path, pkg_command: str) -> list[Path]:
    """Create a signed flat pkg repository containing all required packages."""
    if not private_key.is_file():
        raise ValueError("private signing key does not exist")
    output.mkdir(parents=True, exist_ok=True)
    if any(output.iterdir()):
        raise ValueError("repository output directory must be empty")
    packages = select_packages(packages_directory)
    validate_package_manifests(packages, pkg_command)
    copied = [output / package.name for package in packages]
    for package, destination in zip(packages, copied, strict=True):
        shutil.copy2(package, destination)
    subprocess.run(
        [pkg_command, "repo", str(output), f"rsa:{private_key}"], check=True
    )
    assets = sorted(path for path in output.iterdir() if path.is_file())
    if any(package not in assets for package in copied) or not any(path.name.startswith("meta") for path in assets):
        raise ValueError("pkg repo did not produce a repository catalog")
    return assets


def asset_order(directory: Path) -> list[Path]:
    """Order package assets before catalog assets, with meta last."""
    assets = sorted(path for path in directory.iterdir() if path.is_file())
    packages = select_packages(directory)
    catalogs = [path for path in assets if path not in packages and not path.name.startswith("meta")]
    metadata = [path for path in assets if path.name.startswith("meta")]
    return packages + catalogs + metadata


def run_gh(arguments: list[str]) -> None:
    subprocess.run(["gh", *arguments], check=True)


def publish(repository: str, tag: str, directory: Path, prerelease: bool) -> None:
    """Create (if needed) and replace the assets of one GitHub Release."""
    title = f"os-bind-rp package repository {tag.removeprefix('pkg-')}"
    create = ["release", "create", tag, "--repo", repository, "--title", title]
    if prerelease:
        create.append("--prerelease")
    else:
        create.append("--latest=false")
    result = subprocess.run(["gh", *create], capture_output=True, text=True)
    if result.returncode and "already exists" not in result.stderr.lower():
        raise RuntimeError(result.stderr.strip() or "cannot create GitHub Release")
    run_gh([
        "release", "upload", tag, *(str(path) for path in asset_order(directory)),
        "--clobber", "--repo", repository,
    ])


def write_bootstrap(output: Path, base_url: str, series: str, public_key_path: str) -> None:
    """Write the UCL configuration a client needs for a signed channel."""
    tag = channel_tag(series)
    output.write_text(
        "resolver-plugins: {\n"
        f"  url: \"{base_url.rstrip('/')}/{tag}\",\n"
        "  mirror_type: \"none\",\n"
        "  signature_type: \"pubkey\",\n"
        f"  pubkey: \"{public_key_path}\",\n"
        "  enabled: yes\n"
        "}\n",
        encoding="utf-8",
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    commands = parser.add_subparsers(dest="command", required=True)
    validate = commands.add_parser("validate")
    validate.add_argument("series")
    validate.add_argument("directory", type=Path)
    stage = commands.add_parser("stage")
    stage.add_argument("--packages-directory", type=Path, required=True)
    stage.add_argument("--output", type=Path, required=True)
    stage.add_argument("--private-key", type=Path, required=True)
    stage.add_argument("--pkg-command", default="pkg")
    publish_parser = commands.add_parser("publish")
    publish_parser.add_argument("--repository", required=True)
    publish_parser.add_argument("--series", required=True)
    publish_parser.add_argument("--directory", type=Path, required=True)
    publish_parser.add_argument("--prerelease", action="store_true")
    bootstrap = commands.add_parser("bootstrap")
    bootstrap.add_argument("--output", type=Path, required=True)
    bootstrap.add_argument("--base-url", required=True)
    bootstrap.add_argument("--series", required=True)
    bootstrap.add_argument("--public-key-path", required=True)
    arguments = parser.parse_args()
    if arguments.command == "validate":
        channel_tag(arguments.series)
        for package in select_packages(arguments.directory):
            print(package)
    elif arguments.command == "stage":
        for asset in stage_repository(
            arguments.packages_directory, arguments.output, arguments.private_key, arguments.pkg_command
        ):
            print(asset)
    elif arguments.command == "publish":
        publish(arguments.repository, channel_tag(arguments.series), arguments.directory, arguments.prerelease)
    else:
        write_bootstrap(
            arguments.output, arguments.base_url, arguments.series, arguments.public_key_path
        )


if __name__ == "__main__":
    main()
