#!/usr/bin/env python3
"""Stage and publish os-bind-rp repositories as GitHub Release assets."""

from __future__ import annotations

import argparse
import shutil
import re
import subprocess
from pathlib import Path


SERIES_PATTERN = re.compile(r"[0-9]+\.[0-9]+")
PACKAGE_PATTERN = re.compile(r"os-bind-rp-(?!devel-).+\.pkg")


def channel_tag(series: str) -> str:
    """Return the fixed GitHub Release tag for a numeric OPNsense series."""
    if SERIES_PATTERN.fullmatch(series) is None:
        raise ValueError("invalid series")
    return f"pkg-{series}"


def select_package(directory: Path) -> Path:
    """Select exactly one production os-bind-rp package in *directory*."""
    packages = sorted(
        path for path in directory.glob("os-bind-rp-*.pkg")
        if PACKAGE_PATTERN.fullmatch(path.name)
    )
    if not packages:
        if list(directory.glob("os-bind-rp-devel-*.pkg")):
            raise ValueError("development package is not a production package")
        raise ValueError("missing production os-bind-rp package")
    if len(packages) != 1:
        raise ValueError("expected exactly one production os-bind-rp package")
    return packages[0]


def stage_repository(package: Path, output: Path, private_key: Path, pkg_command: str) -> list[Path]:
    """Create a signed flat pkg repository containing one production package."""
    if not package.is_file():
        raise ValueError("package does not exist")
    if not private_key.is_file():
        raise ValueError("private signing key does not exist")
    output.mkdir(parents=True, exist_ok=True)
    if any(output.iterdir()):
        raise ValueError("repository output directory must be empty")
    copied = output / package.name
    shutil.copy2(package, copied)
    subprocess.run(
        [pkg_command, "repo", str(output), f"rsa:{private_key}"], check=True
    )
    assets = sorted(path for path in output.iterdir() if path.is_file())
    if copied not in assets or not any(path.name.startswith("meta") for path in assets):
        raise ValueError("pkg repo did not produce a repository catalog")
    return assets


def asset_order(directory: Path) -> list[Path]:
    """Order package assets before catalog assets, with meta last."""
    assets = sorted(path for path in directory.iterdir() if path.is_file())
    select_package(directory)
    packages = [path for path in assets if path.suffix == ".pkg" and path.name.startswith("os-bind-rp-")]
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
    stage.add_argument("--package", type=Path, required=True)
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
        print(select_package(arguments.directory))
    elif arguments.command == "stage":
        for asset in stage_repository(
            arguments.package, arguments.output, arguments.private_key, arguments.pkg_command
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
