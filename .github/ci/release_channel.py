#!/usr/bin/env python3
"""Stage and publish os-bind-rp repositories as GitHub Release assets."""

from __future__ import annotations

import argparse
import functools
import hashlib
import json
import shutil
import re
import subprocess
import tempfile
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


SERIES_PATTERN = re.compile(r"[0-9]+\.[0-9]+")
PACKAGE_PATTERNS = {
    "bind-tools": re.compile(r"bind-tools-9\.20\.26_1\.pkg"),
    "bind920": re.compile(r"bind920-9\.20\.26_1\.pkg"),
    "os-bind-rp": re.compile(r"os-bind-rp-(?!devel-).+\.pkg"),
}
PLUGIN_PATTERN = PACKAGE_PATTERNS["os-bind-rp"]
PROVENANCE_NAME = "bind920-provenance.json"
ARCHIVE_REPOSITORY_NAME = "resolver-plugins-archive"
SOURCE_REPOSITORY_NAME = "resolver-plugins-source"
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


def archive_channel_tag(series: str) -> str:
    """Return the rollback catalogue tag for a numeric OPNsense series."""
    return f"{channel_tag(series)}-archive"


def bind920_channel_tag(series: str) -> str:
    """Return the disabled-by-default Resolver BIND fallback tag."""
    return f"{channel_tag(series)}-bind920"


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


def select_plugin_packages(directory: Path) -> list[Path]:
    """Return production plugin archives supplied for one signed catalogue."""
    packages = sorted(
        path for path in directory.glob("os-bind-rp-*.pkg") if PLUGIN_PATTERN.fullmatch(path.name)
    )
    if not packages:
        if list(directory.glob("os-bind-rp-devel-*.pkg")):
            raise ValueError("development os-bind-rp package is not a production package")
        raise ValueError("missing production os-bind-rp package")
    return packages


def select_bind920_packages(directory: Path) -> list[Path]:
    """Return the exact Resolver BIND fallback pair and no plugin archive."""
    selected = []
    for family in ("bind-tools", "bind920"):
        packages = sorted(
            path for path in directory.glob(f"{family}-*.pkg")
            if PACKAGE_PATTERNS[family].fullmatch(path.name)
        )
        if not packages:
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


def read_package_manifest(package: Path, pkg_command: str) -> dict[str, object]:
    """Read the full archive manifest needed to validate solver formulas."""
    result = subprocess.run(
        [pkg_command, "info", "-F", str(package), "-R", "--raw-format", "json"],
        check=True,
        capture_output=True,
        text=True,
    )
    manifest = json.loads(result.stdout)
    if not isinstance(manifest, dict):
        raise ValueError(f"cannot read package manifest: {package.name}")
    return manifest


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


def validate_plugin_package_manifests(packages: list[Path], pkg_command: str) -> None:
    """Reject plugin rollback archives with an unexpected identity or ABI."""
    common_abi = None
    for package in packages:
        identity, _ = query_package(package, pkg_command)
        name, _, origin, abi = identity
        if (name, origin) != ("os-bind-rp", "opnsense/os-bind-rp"):
            raise ValueError("unexpected os-bind-rp package manifest")
        if common_abi is None:
            common_abi = abi
        elif abi != common_abi:
            raise ValueError("plugin package ABI does not match the archive")
        if read_package_manifest(package, pkg_command).get("dep_formula") != "bind920 >= 9.20.26":
            raise ValueError("plugin package does not declare the required BIND dependency formula")


def plugin_package_version(package: Path, pkg_command: str) -> str:
    """Read one validated plugin version for archive ordering."""
    identity, _ = query_package(package, pkg_command)
    name, version, origin, _ = identity
    if (name, origin) != ("os-bind-rp", "opnsense/os-bind-rp"):
        raise ValueError("unexpected os-bind-rp package manifest")
    return version


def newest_plugin_packages(packages: list[Path], pkg_command: str) -> list[Path]:
    """Keep no more than five package versions using pkg's native ordering."""
    versions = {package: plugin_package_version(package, pkg_command) for package in packages}
    if len(set(versions.values())) != len(versions):
        raise ValueError("plugin archive contains duplicate package versions")

    def compare(left: Path, right: Path) -> int:
        result = subprocess.run(
            [pkg_command, "version", "-t", versions[left], versions[right]],
            check=True,
            capture_output=True,
            text=True,
        ).stdout.strip()
        if result not in {"<", "=", ">"}:
            raise ValueError("cannot order plugin package versions")
        return {"<": -1, "=": 0, ">": 1}[result]

    return sorted(packages, key=functools.cmp_to_key(compare), reverse=True)[:5]


def validate_bind920_package_manifests(packages: list[Path], pkg_command: str) -> None:
    """Validate only the Resolver BIND fallback package chain."""
    manifests = {}
    common_abi = None
    for family, package in zip(("bind-tools", "bind920"), packages, strict=True):
        identity, dependencies = query_package(package, pkg_command)
        name, version, origin, abi = identity
        expected_name, expected_version, expected_origin = EXPECTED_PACKAGES[family]
        if (name, version, origin) != (expected_name, expected_version, expected_origin):
            raise ValueError(f"unexpected {family} package manifest")
        if common_abi is None:
            common_abi = abi
        elif abi != common_abi:
            raise ValueError("package ABI does not match the bundled BIND packages")
        manifests[family] = dependencies
    if ("bind-tools", "dns/bind-tools", "9.20.26_1") not in manifests["bind920"]:
        raise ValueError("bind920 does not depend on bundled bind-tools")


def stage_selected_repository(
    packages: list[Path],
    output: Path,
    private_key: Path,
    pkg_command: str,
    metadata: list[Path],
) -> list[Path]:
    """Create one signed catalogue from already validated package inputs."""
    if not private_key.is_file():
        raise ValueError("private signing key does not exist")
    output.mkdir(parents=True, exist_ok=True)
    if any(output.iterdir()):
        raise ValueError("repository output directory must be empty")
    copied = [output / package.name for package in packages]
    for package, destination in zip(packages, copied, strict=True):
        shutil.copy2(package, destination)
    for source in metadata:
        shutil.copy2(source, output / source.name)
    subprocess.run([pkg_command, "repo", str(output), f"rsa:{private_key}"], check=True)
    assets = sorted(path for path in output.iterdir() if path.is_file())
    if any(package not in assets for package in copied) or any(
        output / source.name not in assets for source in metadata
    ) or not any(path.name.startswith("meta") for path in assets):
        raise ValueError("pkg repo did not produce a repository catalog")
    return assets


def stage_plugin_repository(
    packages_directory: Path, output: Path, private_key: Path, pkg_command: str
) -> list[Path]:
    """Create a latest or rollback catalogue containing plugin packages only."""
    packages = select_plugin_packages(packages_directory)
    metadata = packages_directory / "build-metadata.txt"
    if not metadata.is_file():
        raise ValueError("plugin build metadata does not exist")
    validate_plugin_package_manifests(packages, pkg_command)
    packages = newest_plugin_packages(packages, pkg_command)
    return stage_selected_repository(packages, output, private_key, pkg_command, [metadata])


def stage_bind920_repository(
    packages_directory: Path, output: Path, private_key: Path, pkg_command: str
) -> list[Path]:
    """Create the separate signed Resolver BIND fallback catalogue."""
    packages = select_bind920_packages(packages_directory)
    provenance = packages_directory / PROVENANCE_NAME
    if not provenance.is_file():
        raise ValueError("BIND provenance does not exist")
    validate_bind920_package_manifests(packages, pkg_command)
    return stage_selected_repository(packages, output, private_key, pkg_command, [provenance])


class ArchiveMissing(Exception):
    """A previous signed channel is absent during its first publication."""


def require_repository_metadata(channel_url: str) -> None:
    """Distinguish an expected first-publication 404 from a broken signed channel."""
    try:
        with urlopen(Request(f"{channel_url.rstrip('/')}/meta.conf")) as response:
            if response.status != 200:
                raise RuntimeError(f"cannot read signed package catalogue: HTTP {response.status}")
    except HTTPError as error:
        if error.code == 404:
            raise ArchiveMissing("signed package channel is absent") from error
        raise RuntimeError(f"cannot read signed package catalogue: HTTP {error.code}") from error
    except URLError as error:
        raise RuntimeError(f"cannot read signed package catalogue: {error}") from error


def write_signed_repository_config(
    directory: Path, repository_name: str, channel_url: str, public_key: Path
) -> Path:
    """Create an isolated pkg configuration for an already signed source channel."""
    if not public_key.is_file():
        raise ValueError("Resolver Plugins public key does not exist")
    directory.mkdir(parents=True, exist_ok=True)
    config = directory / f"{repository_name}.conf"
    config.write_text(
        f"{repository_name}: {{\n"
        f"  url: \"{channel_url.rstrip('/')}\",\n"
        "  mirror_type: \"none\",\n"
        "  signature_type: \"pubkey\",\n"
        f"  pubkey: \"{public_key}\",\n"
        "  enabled: yes\n"
        "}\n",
        encoding="utf-8",
    )
    return directory


def downloaded_archive(directory: Path, filename: str) -> Path:
    """Locate a pkg fetch result across the layouts supported by pkg."""
    for candidate in (directory / filename, directory / "All" / filename):
        if candidate.is_file():
            return candidate
    raise RuntimeError(f"pkg did not fetch {filename}")


def collect_signed_packages(
    channel_url: str,
    public_key: Path,
    output: Path,
    repository_name: str,
    package_names: set[str],
    pkg_command: str,
) -> list[Path]:
    """Fetch named packages only through a verified prior signed catalogue."""
    require_repository_metadata(channel_url)
    with tempfile.TemporaryDirectory() as temporary_directory:
        config = write_signed_repository_config(
            Path(temporary_directory) / "repos", repository_name, channel_url, public_key
        )
        options = [pkg_command, "-o", f"REPOS_DIR={config}"]
        subprocess.run([*options, "update", "-f"], check=True)
        result = subprocess.run(
            [*options, "rquery", "-r", repository_name, "%n\t%v"],
            check=True,
            capture_output=True,
            text=True,
        )
        records = []
        for line in result.stdout.splitlines():
            fields = line.split("\t")
            if len(fields) != 2 or not all(fields):
                raise RuntimeError("cannot read signed repository package identities")
            if fields[0] in package_names:
                records.append((fields[0], fields[1]))
        if not records:
            raise ArchiveMissing("signed package channel has no requested packages")
        output.mkdir(parents=True, exist_ok=True)
        copied = []
        for name, version in records:
            requested = f"{name}-{version}"
            subprocess.run(
                [*options, "fetch", "-y", "-r", repository_name, "-o", str(output), requested],
                check=True,
            )
            archive = downloaded_archive(output, f"{requested}.pkg")
            destination = output / archive.name
            if archive != destination:
                shutil.copy2(archive, destination)
            copied.append(destination)
        return copied


def collect_plugin_archive(
    channel_url: str, public_key: Path, output: Path, pkg_command: str
) -> list[Path]:
    """Retrieve retained plugin versions from the previous signed archive only."""
    return collect_signed_packages(
        channel_url, public_key, output, ARCHIVE_REPOSITORY_NAME, {"os-bind-rp"}, pkg_command
    )


def collect_bind920_fallback(
    channel_url: str, public_key: Path, output: Path, pkg_command: str
) -> list[Path]:
    """Move the prior signed BIND pair into its separate fallback channel."""
    packages = collect_signed_packages(
        channel_url, public_key, output, SOURCE_REPOSITORY_NAME,
        {"bind-tools", "bind920"}, pkg_command,
    )
    try:
        with urlopen(Request(f"{channel_url.rstrip('/')}/{PROVENANCE_NAME}")) as response:
            provenance = response.read()
    except (HTTPError, URLError) as error:
        raise RuntimeError(f"cannot preserve BIND provenance: {error}") from error
    if not provenance:
        raise RuntimeError("cannot preserve empty BIND provenance")
    (output / PROVENANCE_NAME).write_bytes(provenance)
    return packages


def stage_repository(packages_directory: Path, output: Path, private_key: Path, pkg_command: str) -> list[Path]:
    """Create a signed flat pkg repository containing all required packages."""
    if not private_key.is_file():
        raise ValueError("private signing key does not exist")
    output.mkdir(parents=True, exist_ok=True)
    if any(output.iterdir()):
        raise ValueError("repository output directory must be empty")
    packages = select_packages(packages_directory)
    provenance = packages_directory / PROVENANCE_NAME
    if not provenance.is_file():
        raise ValueError("BIND provenance does not exist")
    validate_package_manifests(packages, pkg_command)
    copied = [output / package.name for package in packages]
    for package, destination in zip(packages, copied, strict=True):
        shutil.copy2(package, destination)
    subprocess.run(
        [pkg_command, "repo", str(output), f"rsa:{private_key}"], check=True
    )
    shutil.copy2(provenance, output / PROVENANCE_NAME)
    assets = sorted(path for path in output.iterdir() if path.is_file())
    if (
        any(package not in assets for package in copied)
        or output / PROVENANCE_NAME not in assets
        or not any(path.name.startswith("meta") for path in assets)
    ):
        raise ValueError("pkg repo did not produce a repository catalog")
    return assets


def asset_order(directory: Path) -> list[Path]:
    """Order package assets before catalog assets, with meta last."""
    assets = sorted(path for path in directory.iterdir() if path.is_file())
    packages = [path for path in assets if path.suffix == ".pkg" and not path.name.startswith("data")]
    catalogs = [path for path in assets if path not in packages and not path.name.startswith("meta")]
    metadata = [path for path in assets if path.name.startswith("meta")]
    return packages + catalogs + metadata


def run_gh(arguments: list[str]) -> None:
    subprocess.run(["gh", *arguments], check=True)


def sha256(path: Path) -> str:
    """Return the checksum used to verify a preserved Release asset."""
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


class ReleaseSnapshot:
    """Verified local bytes required to restore one mutable GitHub Release."""

    def __init__(self, tag: str, existed: bool, directory: Path, manifest: Path) -> None:
        self.tag = tag
        self.existed = existed
        self.directory = directory
        self.manifest = manifest


def snapshot_release(repository: str, tag: str, recovery: Path) -> ReleaseSnapshot:
    """Download and checksum every pre-promotion asset before changing a Release."""
    result = subprocess.run(
        ["gh", "release", "view", tag, "--repo", repository, "--json", "assets"],
        capture_output=True,
        text=True,
    )
    directory = recovery / tag
    manifest = recovery / f"{tag}.json"
    if result.returncode:
        if "not found" in result.stderr.lower():
            return ReleaseSnapshot(tag, False, directory, manifest)
        raise RuntimeError(result.stderr.strip() or f"cannot inspect GitHub Release {tag}")
    payload = json.loads(result.stdout)
    assets = payload.get("assets")
    if not isinstance(assets, list) or not all(
        isinstance(asset, dict) and isinstance(asset.get("name"), str) and asset["name"]
        for asset in assets
    ):
        raise RuntimeError(f"cannot read GitHub Release assets for {tag}")
    directory.mkdir(parents=True, exist_ok=False)
    checksums = {}
    for asset in assets:
        name = asset["name"]
        run_gh([
            "release", "download", tag, "--repo", repository, "--pattern", name,
            "--dir", str(directory),
        ])
        downloaded = directory / name
        if not downloaded.is_file():
            raise RuntimeError(f"cannot preserve GitHub Release asset {tag}/{name}")
        checksums[name] = sha256(downloaded)
    manifest.write_text(json.dumps(checksums, sort_keys=True) + "\n", encoding="utf-8")
    return ReleaseSnapshot(tag, True, directory, manifest)


def restore_release(repository: str, snapshot: ReleaseSnapshot) -> None:
    """Restore one channel exclusively from its verified local recovery bytes."""
    if not snapshot.existed:
        run_gh(["release", "delete", snapshot.tag, "--yes", "--repo", repository])
        return
    checksums = json.loads(snapshot.manifest.read_text(encoding="utf-8"))
    if not isinstance(checksums, dict) or {
        path.name for path in snapshot.directory.iterdir() if path.is_file()
    } != set(checksums) or any(
        not isinstance(digest, str) or sha256(snapshot.directory / name) != digest
        for name, digest in checksums.items()
    ):
        raise RuntimeError(f"recovery assets for {snapshot.tag} do not match their manifest")
    publish(repository, snapshot.tag, snapshot.directory, False)


def publish_channels(
    repository: str, channels: list[tuple[str, Path]], recovery: Path
) -> None:
    """Publish related channels or restore all mutable releases from local snapshots."""
    recovery.mkdir(parents=True, exist_ok=False)
    snapshots = [snapshot_release(repository, tag, recovery) for tag, _ in channels]
    try:
        for tag, directory in channels:
            publish(repository, tag, directory, False)
    except Exception:
        restore_errors = []
        for snapshot in reversed(snapshots):
            try:
                restore_release(repository, snapshot)
            except Exception as error:  # pragma: no cover - exercised against GitHub, not a fixture.
                restore_errors.append(f"{snapshot.tag}: {error}")
        if restore_errors:
            raise RuntimeError("promotion failed and recovery failed: " + "; ".join(restore_errors))
        raise


def parse_channel(value: str) -> tuple[str, Path]:
    """Parse one exact mutable channel tag and its staged repository path."""
    tag, separator, directory = value.partition("=")
    if not separator or not tag or not directory:
        raise argparse.ArgumentTypeError("channel must be TAG=DIRECTORY")
    return tag, Path(directory)


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
    assets = asset_order(directory)
    existing = subprocess.run(
        [
            "gh", "release", "view", tag, "--repo", repository,
            "--json", "assets", "--jq", ".assets[].name",
        ],
        capture_output=True,
        text=True,
    )
    if existing.returncode:
        raise RuntimeError(existing.stderr.strip() or "cannot list GitHub Release assets")
    asset_names = {asset.name for asset in assets}
    for asset_name in existing.stdout.splitlines():
        if asset_name not in asset_names:
            run_gh([
                "release", "delete-asset", tag, asset_name, "--yes",
                "--repo", repository,
            ])
    run_gh([
        "release", "upload", tag, *(str(path) for path in assets),
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
    stage_plugin = commands.add_parser("stage-plugin")
    stage_plugin.add_argument("--packages-directory", type=Path, required=True)
    stage_plugin.add_argument("--output", type=Path, required=True)
    stage_plugin.add_argument("--private-key", type=Path, required=True)
    stage_plugin.add_argument("--pkg-command", default="pkg")
    stage_bind920 = commands.add_parser("stage-bind920")
    stage_bind920.add_argument("--packages-directory", type=Path, required=True)
    stage_bind920.add_argument("--output", type=Path, required=True)
    stage_bind920.add_argument("--private-key", type=Path, required=True)
    stage_bind920.add_argument("--pkg-command", default="pkg")
    collect_archive = commands.add_parser("collect-plugin-archive")
    collect_archive.add_argument("--channel-url", required=True)
    collect_archive.add_argument("--public-key", type=Path, required=True)
    collect_archive.add_argument("--output", type=Path, required=True)
    collect_archive.add_argument("--pkg-command", default="pkg")
    collect_bind920 = commands.add_parser("collect-bind920")
    collect_bind920.add_argument("--channel-url", required=True)
    collect_bind920.add_argument("--public-key", type=Path, required=True)
    collect_bind920.add_argument("--output", type=Path, required=True)
    collect_bind920.add_argument("--pkg-command", default="pkg")
    publish_parser = commands.add_parser("publish")
    publish_parser.add_argument("--repository", required=True)
    publish_parser.add_argument("--series", required=True)
    publish_parser.add_argument("--directory", type=Path, required=True)
    publish_parser.add_argument("--prerelease", action="store_true")
    publish_tag = commands.add_parser("publish-tag")
    publish_tag.add_argument("--repository", required=True)
    publish_tag.add_argument("--tag", required=True)
    publish_tag.add_argument("--directory", type=Path, required=True)
    publish_channels_parser = commands.add_parser("publish-channels")
    publish_channels_parser.add_argument("--repository", required=True)
    publish_channels_parser.add_argument("--recovery", type=Path, required=True)
    publish_channels_parser.add_argument("--channel", type=parse_channel, action="append", required=True)
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
    elif arguments.command == "stage-plugin":
        for asset in stage_plugin_repository(
            arguments.packages_directory, arguments.output, arguments.private_key, arguments.pkg_command
        ):
            print(asset)
    elif arguments.command == "stage-bind920":
        for asset in stage_bind920_repository(
            arguments.packages_directory, arguments.output, arguments.private_key, arguments.pkg_command
        ):
            print(asset)
    elif arguments.command == "collect-plugin-archive":
        try:
            for package in collect_plugin_archive(
                arguments.channel_url, arguments.public_key, arguments.output, arguments.pkg_command
            ):
                print(package)
        except ArchiveMissing as error:
            print(error)
            return 3
    elif arguments.command == "collect-bind920":
        try:
            for package in collect_bind920_fallback(
                arguments.channel_url, arguments.public_key, arguments.output, arguments.pkg_command
            ):
                print(package)
        except ArchiveMissing as error:
            print(error)
            return 3
    elif arguments.command == "publish":
        publish(arguments.repository, channel_tag(arguments.series), arguments.directory, arguments.prerelease)
    elif arguments.command == "publish-tag":
        publish(arguments.repository, arguments.tag, arguments.directory, False)
    elif arguments.command == "publish-channels":
        publish_channels(arguments.repository, arguments.channel, arguments.recovery)
    else:
        write_bootstrap(
            arguments.output, arguments.base_url, arguments.series, arguments.public_key_path
        )


if __name__ == "__main__":
    raise SystemExit(main())
