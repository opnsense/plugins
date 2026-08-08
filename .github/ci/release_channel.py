#!/usr/bin/env python3
"""Stage and publish os-bind-rp repositories as GitHub Release assets."""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import re
import subprocess
import sys
import tempfile
from pathlib import Path


sys.path.insert(0, str(Path(__file__).resolve().parent))
import bind920_profile
import target_pkg


SERIES_PATTERN = re.compile(r"[0-9]+\.[0-9]+")
PULL_REQUEST_PATTERN = re.compile(r"[1-9][0-9]*")
DEVELOPMENT_RELEASE_PATTERN = re.compile(r"pr-([1-9][0-9]*)-([0-9]+\.[0-9]+)")
PLUGIN_PATTERN = re.compile(r"os-bind-rp-(?!devel-).+\.pkg")
PROVENANCE_NAME = "bind920-provenance.json"
PACKAGE_VERSION_PATTERN = re.compile(r"[0-9][0-9A-Za-z._-]*")
BUILD_METADATA_FIELDS = {
    "series",
    "uname",
    "pkg_abi",
    "bind920",
    "bind_source",
    "opnsense",
    "opnsense_core_commit",
    "upstream_commit",
    "core_commit",
    "tools_tag",
    "freebsd_release",
    "source_commit",
    "pkg_creator",
    "pkg_creator_sha256",
}
LEGACY_BUILD_METADATA_FIELDS = BUILD_METADATA_FIELDS - {
    "pkg_creator",
    "pkg_creator_sha256",
}
TRUSTED_BUILD_FIELDS = {"series", "upstream_commit", "core_commit", "tools_tag", "freebsd_release"}


def channel_tag(series: str) -> str:
    """Return the fixed GitHub Release tag for a numeric OPNsense series."""
    if SERIES_PATTERN.fullmatch(series) is None:
        raise ValueError("invalid series")
    return f"pkg-{series}"


def snapshot_channel_tag(series: str, version: str) -> str:
    """Return an immutable, self-contained rollback snapshot tag."""
    if PACKAGE_VERSION_PATTERN.fullmatch(version) is None:
        raise ValueError("invalid package version")
    return f"{channel_tag(series)}-os-bind-rp-{version}"


def source_release_tag(series: str, version: str) -> str:
    """Return the immutable, human-facing plugin release tag."""
    if SERIES_PATTERN.fullmatch(series) is None:
        raise ValueError("invalid series")
    if PACKAGE_VERSION_PATTERN.fullmatch(version) is None:
        raise ValueError("invalid package version")
    return f"os-bind-rp-{series}-{version}"


def package_release_title(tag: str) -> str:
    """Return the purpose-first display title for one package channel tag."""
    value = tag.removeprefix("pkg-")
    if value == tag:
        raise ValueError("invalid package release tag")
    if SERIES_PATTERN.fullmatch(value) is not None:
        return f"{value}-latest"
    series, separator, version = value.partition("-os-bind-rp-")
    if (
        separator
        and SERIES_PATTERN.fullmatch(series) is not None
        and PACKAGE_VERSION_PATTERN.fullmatch(version) is not None
    ):
        return f"{series}-archive-{version}"
    raise ValueError("invalid package release tag")


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


def read_bind_package_records(provenance_path: Path) -> dict[str, dict[str, str]]:
    """Read the exact BIND package identities from trusted build provenance."""
    try:
        provenance = json.loads(provenance_path.read_text(encoding="utf-8"))
        records = provenance["packages"]
    except (OSError, json.JSONDecodeError, KeyError, TypeError) as error:
        raise ValueError("BIND provenance is invalid") from error
    if not isinstance(records, dict) or set(records) != {"bind-tools", "bind920"}:
        raise ValueError("BIND provenance is invalid")
    expected_origins = {"bind-tools": "dns/bind-tools", "bind920": "dns/bind920"}
    validated = {}
    for package_name, expected_origin in expected_origins.items():
        record = records[package_name]
        if not isinstance(record, dict) or set(record) != {"name", "version", "origin", "filename"}:
            raise ValueError("BIND provenance is invalid")
        if (
            record["name"] != package_name
            or record["origin"] != expected_origin
            or not all(isinstance(record[field], str) and record[field] for field in record)
            or Path(record["filename"]).name != record["filename"]
        ):
            raise ValueError("BIND provenance is invalid")
        validated[package_name] = record
    return validated


def validate_bind_provenance(
    provenance: object,
    profile: object,
    series: str,
    freebsd_release: str,
    package_creator: object,
) -> dict[str, dict[str, str]]:
    """Accept only the BIND pair implied by trusted control-plane metadata."""
    if not isinstance(provenance, dict):
        raise ValueError("BIND provenance is invalid")
    try:
        expected = bind920_profile.build_provenance(
            profile,
            series,
            freebsd_release,
            "x86_64",
            package_creator,
            provenance["packages"],
        )
    except (KeyError, ValueError) as error:
        raise ValueError("BIND provenance is invalid") from error
    if provenance.get("fingerprint") != expected["fingerprint"]:
        raise ValueError("BIND provenance fingerprint does not match the trusted profile")
    if provenance != expected:
        raise ValueError("BIND provenance does not match the trusted profile")
    return expected["packages"]


def select_channel_packages(directory: Path) -> list[Path]:
    """Select a plugin and the exact BIND archive names recorded in provenance."""
    records = read_bind_package_records(directory / PROVENANCE_NAME)
    selected = []
    for package_name in ("bind-tools", "bind920"):
        filename = records[package_name]["filename"]
        package = directory / filename
        if not package.is_file():
            raise ValueError(f"missing production {package_name} package")
        selected.append(package)
    return [*selected, *select_plugin_packages(directory)]


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


def validate_channel_package_manifests(
    packages: list[Path], provenance_path: Path, pkg_command: str
) -> None:
    """Validate a self-contained package set against its BIND provenance."""
    if len(packages) != 3:
        raise ValueError("channel does not contain one BIND pair and one plugin")
    records = read_bind_package_records(provenance_path)
    common_abi = None
    identities = {}
    dependencies = {}
    for expected_name, package in zip(("bind-tools", "bind920", "os-bind-rp"), packages, strict=True):
        identity, package_dependencies = query_package(package, pkg_command)
        name, version, origin, abi = identity
        if name != expected_name:
            raise ValueError("channel package has an unexpected identity")
        if expected_name in records:
            expected = records[expected_name]
            if (name, version, origin, package.name) != (
                expected["name"], expected["version"], expected["origin"], expected["filename"]
            ):
                raise ValueError(f"{expected_name} package does not match BIND provenance")
        elif origin != "opnsense/os-bind-rp":
            raise ValueError("channel plugin has an unexpected origin")
        if common_abi is None:
            common_abi = abi
        elif abi != common_abi:
            raise ValueError("channel package ABI does not match the BIND pair")
        identities[name] = (version, origin)
        dependencies[name] = package_dependencies
    bind_tools_version, _ = identities["bind-tools"]
    if ("bind-tools", "dns/bind-tools", bind_tools_version) not in dependencies["bind920"]:
        raise ValueError("bind920 does not depend on the channel bind-tools package")
    if any(dependency[0] == "bind920" for dependency in dependencies["os-bind-rp"]):
        raise ValueError("plugin package records an exact BIND dependency")
    if read_package_manifest(packages[2], pkg_command).get("dep_formula") != "bind920 >= 9.20.26":
        raise ValueError("plugin package does not declare the required BIND dependency formula")


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


def read_build_metadata(path: Path, *, allow_legacy: bool = False) -> dict[str, str]:
    """Read the small, line-oriented build identity used by channel.json."""
    if not path.is_file():
        raise ValueError("plugin build metadata does not exist")
    fields = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        key, separator, value = line.partition("=")
        if not separator or not key or not value or key in fields:
            raise ValueError("plugin build metadata is invalid")
        fields[key] = value
    accepted_fields = {frozenset(BUILD_METADATA_FIELDS)}
    if allow_legacy:
        accepted_fields.add(frozenset(LEGACY_BUILD_METADATA_FIELDS))
    if (
        frozenset(fields) not in accepted_fields
        or SERIES_PATTERN.fullmatch(fields.get("series", "")) is None
        or not all(fields.values())
    ):
        raise ValueError("plugin build metadata is invalid")
    return fields


def validate_build_metadata(
    metadata_path: Path,
    upstream_path: Path,
    provenance_path: Path,
    target_pkg_metadata: Path,
    series: str,
    source_commit: str,
) -> dict[str, str]:
    """Match every security-relevant build field to trusted release inputs."""
    metadata = read_build_metadata(metadata_path)
    try:
        upstream = json.loads(upstream_path.read_text(encoding="utf-8"))
        provenance = json.loads(provenance_path.read_text(encoding="utf-8"))
        bind_version = provenance["packages"]["bind920"]["version"]
    except (OSError, json.JSONDecodeError, KeyError, TypeError) as error:
        raise ValueError("trusted release metadata is invalid") from error
    if not isinstance(upstream, dict) or any(
        not isinstance(upstream.get(field), str) or not upstream[field]
        for field in TRUSTED_BUILD_FIELDS
    ):
        raise ValueError("trusted release metadata is invalid")
    expected = {field: upstream[field] for field in TRUSTED_BUILD_FIELDS}
    package_creator = target_pkg.load_target(target_pkg_metadata, series).record()
    if (
        {field: metadata[field] for field in TRUSTED_BUILD_FIELDS} != expected
        or metadata["series"] != series
        or metadata["source_commit"] != source_commit
        or metadata["opnsense_core_commit"] != metadata["core_commit"]
        or metadata["bind920"] != bind_version
        or metadata["bind_source"] != "resolver"
        or provenance.get("series") != series
        or provenance.get("freebsd_release") != metadata["freebsd_release"]
        or provenance.get("package_creator") != package_creator
        or metadata["pkg_creator"] != package_creator["version"]
        or metadata["pkg_creator_sha256"] != package_creator["sha256"]
    ):
        raise ValueError("build artifact does not match trusted release metadata")
    return metadata


def stage_channel_repository(
    packages_directory: Path,
    output: Path,
    private_key: Path,
    pkg_command: str,
    target_pkg_metadata: Path,
) -> list[Path]:
    """Create one signed, self-contained current or rollback channel."""
    provenance = packages_directory / PROVENANCE_NAME
    if not provenance.is_file():
        raise ValueError("BIND provenance does not exist")
    packages = select_channel_packages(packages_directory)
    build_metadata = packages_directory / "build-metadata.txt"
    metadata = read_build_metadata(build_metadata)
    required_build_fields = {"upstream_commit", "core_commit", "tools_tag", "freebsd_release"}
    try:
        bind_metadata = json.loads(provenance.read_text(encoding="utf-8"))
        bind_identity = {
            field: bind_metadata[field]
            for field in ("fingerprint", "freebsd_release", "architecture")
        }
    except (OSError, json.JSONDecodeError, KeyError, TypeError) as error:
        raise ValueError("BIND provenance is invalid") from error
    if bind_metadata.get("series") != metadata["series"]:
        raise ValueError("BIND provenance series does not match plugin build metadata")
    package_creator = target_pkg.load_target(
        target_pkg_metadata, metadata["series"]
    ).record()
    records = read_bind_package_records(provenance)
    if (
        bind_metadata.get("freebsd_release") != metadata["freebsd_release"]
        or records["bind920"]["version"] != metadata["bind920"]
        or metadata["opnsense_core_commit"] != metadata["core_commit"]
        or bind_metadata.get("package_creator") != package_creator
        or metadata["pkg_creator"] != package_creator["version"]
        or metadata["pkg_creator_sha256"] != package_creator["sha256"]
    ):
        raise ValueError("BIND provenance does not match plugin build metadata")
    validate_channel_package_manifests(packages, provenance, pkg_command)
    stage_selected_repository(
        packages, output, private_key, pkg_command, [provenance, build_metadata]
    )
    channel_manifest = {
        "schema": 2,
        "series": metadata["series"],
        "plugin_version": packages[2].name.removeprefix("os-bind-rp-").removesuffix(".pkg"),
        "source_commit": metadata["source_commit"],
        "build": {field: metadata[field] for field in sorted(required_build_fields)},
        "bind": bind_identity,
        "package_creator": package_creator,
        "packages": {package.name: sha256(package) for package in packages},
    }
    (output / "channel.json").write_text(
        json.dumps(channel_manifest, sort_keys=True, indent=2) + "\n", encoding="utf-8"
    )
    return sorted(path for path in output.iterdir() if path.is_file())


def asset_order(directory: Path) -> list[Path]:
    """Order package assets before catalog assets, with meta last."""
    assets = sorted(path for path in directory.iterdir() if path.is_file())
    packages = [path for path in assets if path.suffix == ".pkg" and not path.name.startswith("data")]
    catalogs = [path for path in assets if path not in packages and not path.name.startswith("meta")]
    metadata = [path for path in assets if path.name.startswith("meta")]
    return packages + catalogs + metadata


def run_gh(arguments: list[str]) -> None:
    subprocess.run(["gh", *arguments], check=True)


def edit_package_release_title(repository: str, tag: str, prerelease: bool = False) -> None:
    """Converge a package Release on its purpose-first display title."""
    edit = [
        "release", "edit", tag, "--repo", repository,
        "--title", package_release_title(tag),
    ]
    if not prerelease:
        edit.append("--latest=false")
    run_gh(edit)


def sha256(path: Path) -> str:
    """Return the checksum used to verify a preserved Release asset."""
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def directory_checksums(directory: Path) -> dict[str, str]:
    """Checksum every flat Release asset in a staged or downloaded channel."""
    return {
        path.name: sha256(path)
        for path in directory.iterdir()
        if path.is_file()
    }


def validate_channel_directory(directory: Path) -> None:
    """Reject malformed recovery bytes before mutating the current channel."""
    packages = select_channel_packages(directory)
    if len(packages) != 3:
        raise ValueError("prior channel has an unexpected package set")
    try:
        channel = json.loads((directory / "channel.json").read_text(encoding="utf-8"))
        provenance = json.loads((directory / PROVENANCE_NAME).read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError("prior channel audit metadata is invalid") from error
    expected_checksums = {package.name: sha256(package) for package in packages}
    if not isinstance(channel, dict) or channel.get("packages") != expected_checksums:
        raise ValueError("prior channel package checksum does not match its audit manifest")
    schema = channel.get("schema") if isinstance(channel, dict) else None
    metadata = read_build_metadata(
        directory / "build-metadata.txt", allow_legacy=schema == 1
    )
    base_channel_fields = {
        "schema", "series", "plugin_version", "source_commit", "build", "bind", "packages",
    }
    required_channel_fields = (
        base_channel_fields if schema == 1 else base_channel_fields | {"package_creator"}
    )
    required_build_fields = {"upstream_commit", "core_commit", "tools_tag", "freebsd_release"}
    expected_bind = {
        field: provenance.get(field)
        for field in ("fingerprint", "freebsd_release", "architecture")
    }
    if (
        set(channel) != required_channel_fields
        or schema not in {1, 2}
        or channel["series"] != metadata["series"]
        or channel["plugin_version"]
        != packages[2].name.removeprefix("os-bind-rp-").removesuffix(".pkg")
        or channel["source_commit"] != metadata["source_commit"]
        or channel["build"] != {
            field: metadata[field] for field in sorted(required_build_fields)
        }
        or channel["bind"] != expected_bind
        or any(value is None for value in expected_bind.values())
        or (
            schema == 2
            and (
                channel.get("package_creator") != provenance.get("package_creator")
                or metadata.get("pkg_creator")
                != provenance.get("package_creator", {}).get("version")
                or metadata.get("pkg_creator_sha256")
                != provenance.get("package_creator", {}).get("sha256")
            )
        )
    ):
        raise ValueError("prior channel audit metadata is inconsistent")
    public_key = directory / "resolver-plugins.pub"
    assets = [path.name for path in directory.iterdir() if path.is_file()]
    if (
        not public_key.is_file()
        or public_key.stat().st_size == 0
        or not any(name.startswith("meta") for name in assets)
        or not any(name.startswith(("data", "packagesite")) for name in assets)
    ):
        raise ValueError("prior channel signed catalogue is incomplete")


def materialize_existing_snapshot(
    repository: str,
    series: str,
    version: str,
    source_commit: str,
    output: Path,
    public_key: Path,
) -> bool:
    """Reuse the signed immutable bytes for an already-published package version."""
    tag = snapshot_channel_tag(series, version)
    with tempfile.TemporaryDirectory() as temporary_directory:
        snapshot = snapshot_release(repository, tag, Path(temporary_directory))
        if not snapshot.existed:
            return False
        validate_channel_directory(snapshot.directory)
        try:
            channel = json.loads(
                (snapshot.directory / "channel.json").read_text(encoding="utf-8")
            )
        except (OSError, json.JSONDecodeError) as error:
            raise ValueError("immutable snapshot audit metadata is invalid") from error
        if (
            not isinstance(channel, dict)
            or channel.get("series") != series
            or channel.get("plugin_version") != version
            or channel.get("source_commit") != source_commit
        ):
            raise ValueError("immutable snapshot does not match requested release")
        try:
            trusted_key = public_key.read_bytes()
            snapshot_key = (snapshot.directory / "resolver-plugins.pub").read_bytes()
        except OSError as error:
            raise ValueError("immutable snapshot public key is unavailable") from error
        if not trusted_key or snapshot_key != trusted_key:
            raise ValueError("immutable snapshot public key does not match trusted key")
        output.mkdir(parents=True, exist_ok=False)
        shutil.copytree(snapshot.directory, output / "current")
        shutil.copytree(snapshot.directory, output / "snapshot")
    return True


class ReleaseSnapshot:
    """Verified local bytes required to restore one mutable GitHub Release."""

    def __init__(self, tag: str, existed: bool, directory: Path, manifest: Path) -> None:
        self.tag = tag
        self.existed = existed
        self.directory = directory
        self.manifest = manifest


def release_snapshots_match(left: ReleaseSnapshot, right: ReleaseSnapshot) -> bool:
    """Return whether two observations contain the same remote Release bytes."""
    if left.existed != right.existed:
        return False
    if not left.existed:
        return True
    try:
        return json.loads(left.manifest.read_text(encoding="utf-8")) == json.loads(
            right.manifest.read_text(encoding="utf-8")
        )
    except (OSError, json.JSONDecodeError):
        return False


def snapshot_matches_directory(snapshot: ReleaseSnapshot, directory: Path) -> bool:
    """Return whether downloaded Release assets match all staged bytes."""
    if not snapshot.existed:
        return False
    try:
        checksums = json.loads(snapshot.manifest.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return False
    return checksums == directory_checksums(directory)


def staged_source_descends_from_current(current: Path, staged: Path) -> bool:
    """Return whether staged source is a strict descendant of current source."""
    try:
        current_commit = json.loads(
            (current / "channel.json").read_text(encoding="utf-8")
        )["source_commit"]
        staged_commit = json.loads(
            (staged / "channel.json").read_text(encoding="utf-8")
        )["source_commit"]
    except (OSError, json.JSONDecodeError, KeyError, TypeError) as error:
        raise RuntimeError("cannot compare package channel source history") from error
    commit_pattern = re.compile(r"[0-9a-f]{40}")
    if (
        not isinstance(current_commit, str)
        or not isinstance(staged_commit, str)
        or commit_pattern.fullmatch(current_commit) is None
        or commit_pattern.fullmatch(staged_commit) is None
        or current_commit == staged_commit
    ):
        return False
    result = subprocess.run(
        ["git", "merge-base", "--is-ancestor", current_commit, staged_commit],
        capture_output=True,
        text=True,
    )
    if result.returncode not in (0, 1):
        raise RuntimeError(
            result.stderr.strip() or "cannot compare package channel source history"
        )
    return result.returncode == 0


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


def publish_immutable_release(
    repository: str, tag: str, directory: Path, title: str
) -> None:
    """Create an immutable Release, or accept an exact byte-for-byte retry."""
    with tempfile.TemporaryDirectory() as temporary_directory:
        existing = snapshot_release(repository, tag, Path(temporary_directory))
        if existing.existed:
            if not snapshot_matches_directory(existing, directory):
                raise RuntimeError(f"immutable GitHub Release has different bytes: {tag}")
            return

    run_gh([
        "release", "create", tag, *(str(path) for path in asset_order(directory)),
        "--repo", repository, "--title", title, "--latest=false",
    ])
    with tempfile.TemporaryDirectory() as temporary_directory:
        published = snapshot_release(repository, tag, Path(temporary_directory))
        if not snapshot_matches_directory(published, directory):
            raise RuntimeError(f"published immutable GitHub Release has different bytes: {tag}")


def restore_release(repository: str, snapshot: ReleaseSnapshot) -> None:
    """Restore one channel exclusively from its verified local recovery bytes."""
    if not snapshot.existed:
        result = subprocess.run(
            ["gh", "release", "delete", snapshot.tag, "--yes", "--repo", repository],
            capture_output=True,
            text=True,
        )
        if result.returncode and "not found" not in result.stderr.lower():
            raise RuntimeError(result.stderr.strip() or f"cannot remove new Release {snapshot.tag}")
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
    """Publish related channels while preserving immutable snapshot identity."""
    recovery.mkdir(parents=True, exist_ok=False)
    snapshots = [snapshot_release(repository, tag, recovery) for tag, _ in channels]
    reusable_channels: set[str] = set()
    for snapshot, (tag, directory) in zip(snapshots, channels, strict=True):
        if snapshot.tag != tag:
            raise RuntimeError("release snapshot does not match its requested channel")
        if "-os-bind-rp-" in snapshot.tag and snapshot.existed:
            if not snapshot_matches_directory(snapshot, directory):
                raise RuntimeError(
                    f"immutable rollback snapshot has different bytes: {snapshot.tag}"
                )
            reusable_channels.add(snapshot.tag)
        if snapshot.existed and SERIES_PATTERN.fullmatch(snapshot.tag.removeprefix("pkg-")):
            validate_channel_directory(snapshot.directory)
    reusing_immutable = bool(reusable_channels)
    for snapshot, (tag, directory) in zip(snapshots, channels, strict=True):
        if (
            snapshot.existed
            and SERIES_PATTERN.fullmatch(tag.removeprefix("pkg-"))
        ):
            if snapshot_matches_directory(snapshot, directory):
                reusable_channels.add(tag)
            elif reusing_immutable:
                raise RuntimeError(
                    f"current channel has different bytes during snapshot retry: {tag}"
                )
            elif not staged_source_descends_from_current(snapshot.directory, directory):
                raise RuntimeError(
                    f"stale package promotion cannot replace current channel: {tag}"
                )
    preflight_root = recovery / "preflight"
    preflight_root.mkdir()
    preflight = [snapshot_release(repository, tag, preflight_root) for tag, _ in channels]
    if any(
        not release_snapshots_match(before, after)
        for before, after in zip(snapshots, preflight, strict=True)
    ):
        raise RuntimeError("package channel changed during publication preflight")
    mutated = []
    try:
        for snapshot, (tag, directory) in zip(snapshots, channels, strict=True):
            if tag in reusable_channels:
                edit_package_release_title(repository, tag)
                continue
            mutated.append(snapshot)
            publish(repository, tag, directory, False)
    except Exception:
        restore_errors = []
        for snapshot in reversed(mutated):
            try:
                restore_release(repository, snapshot)
            except Exception as error:  # pragma: no cover - exercised against GitHub, not a fixture.
                restore_errors.append(f"{snapshot.tag}: {error}")
        if restore_errors:
            raise RuntimeError("promotion failed and recovery failed: " + "; ".join(restore_errors))
        raise


def prune_snapshots(repository: str, series: str, keep: int = 5) -> None:
    """Retain only the newest immutable self-contained snapshots for one series."""
    if keep < 1:
        raise ValueError("snapshot retention must be positive")
    result = subprocess.run(
        ["gh", "api", "--paginate", "--slurp", f"repos/{repository}/releases?per_page=100"],
        check=True,
        capture_output=True,
        text=True,
    )
    pages = json.loads(result.stdout)
    prefix = f"{channel_tag(series)}-os-bind-rp-"
    snapshots = []
    if not isinstance(pages, list):
        raise RuntimeError("cannot list snapshot releases")
    releases = [
        release
        for page in pages
        for release in (page if isinstance(page, list) else [page])
    ]
    for release in releases:
        if not isinstance(release, dict):
            continue
        tag = release.get("tag_name")
        created = release.get("created_at")
        if (
            isinstance(tag, str)
            and isinstance(created, str)
            and tag.startswith(prefix)
            and PACKAGE_VERSION_PATTERN.fullmatch(tag.removeprefix(prefix))
        ):
            snapshots.append((created, tag))
    for _, tag in sorted(snapshots, reverse=True)[keep:]:
        run_gh(["release", "delete", tag, "--yes", "--repo", repository])


def mark_latest_package_channel(repository: str) -> None:
    """Assign GitHub's repository-wide Latest badge to the newest current series."""
    result = subprocess.run(
        ["gh", "api", "--paginate", "--slurp", f"repos/{repository}/releases?per_page=100"],
        check=True,
        capture_output=True,
        text=True,
    )
    pages = json.loads(result.stdout)
    if not isinstance(pages, list) or not all(isinstance(page, list) for page in pages):
        raise RuntimeError("cannot list package releases")
    current_channels = []
    for page in pages:
        for release in page:
            if not isinstance(release, dict) or release.get("draft") or release.get("prerelease"):
                continue
            tag = release.get("tag_name")
            series = tag.removeprefix("pkg-") if isinstance(tag, str) else ""
            if isinstance(tag, str) and tag.startswith("pkg-") and SERIES_PATTERN.fullmatch(series):
                current_channels.append((tuple(int(part) for part in series.split(".")), tag))
    if not current_channels:
        raise RuntimeError("cannot find a current package channel")
    _, tag = max(current_channels)
    run_gh(["release", "edit", tag, "--repo", repository, "--latest"])


def select_pull_request_release_tags(releases: object, pull_number: str) -> list[str]:
    """Select only complete development Release tags for one pull request."""
    if PULL_REQUEST_PATTERN.fullmatch(pull_number) is None:
        raise ValueError("invalid pull request number")
    if not isinstance(releases, list) or not all(
        isinstance(page, list) for page in releases
    ):
        raise RuntimeError("cannot list pull request releases")
    expected_pull = int(pull_number)
    selected = set()
    for page in releases:
        for release in page:
            if not isinstance(release, dict):
                continue
            tag = release.get("tag_name")
            match = DEVELOPMENT_RELEASE_PATTERN.fullmatch(tag) if isinstance(tag, str) else None
            if match is not None and int(match.group(1)) == expected_pull:
                selected.add(tag)
    return sorted(selected)


def select_pull_request_tag_refs(refs: object, pull_number: str) -> list[str]:
    """Select only complete development Git tag refs for one pull request."""
    if PULL_REQUEST_PATTERN.fullmatch(pull_number) is None:
        raise ValueError("invalid pull request number")
    if not isinstance(refs, list) or not all(isinstance(page, list) for page in refs):
        raise RuntimeError("cannot list pull request tags")
    expected_pull = int(pull_number)
    selected = set()
    prefix = "refs/tags/"
    for page in refs:
        for item in page:
            if not isinstance(item, dict):
                continue
            ref = item.get("ref")
            tag = ref.removeprefix(prefix) if isinstance(ref, str) and ref.startswith(prefix) else ""
            match = DEVELOPMENT_RELEASE_PATTERN.fullmatch(tag)
            if match is not None and int(match.group(1)) == expected_pull:
                selected.add(tag)
    return sorted(selected)


def cleanup_development_release(repository: str, tag: str) -> None:
    """Delete one exact development Release and its Git tag, if present."""
    if DEVELOPMENT_RELEASE_PATTERN.fullmatch(tag) is None:
        raise ValueError("invalid development release tag")
    result = subprocess.run(
        [
            "gh", "release", "delete", tag, "--yes", "--repo", repository,
        ],
        capture_output=True,
        text=True,
    )
    if result.returncode and "release not found" not in result.stderr.lower():
        raise RuntimeError(result.stderr.strip() or f"cannot delete development Release {tag}")
    tag_result = subprocess.run(
        [
            "gh", "api", "--method", "DELETE",
            f"repos/{repository}/git/refs/tags/{tag}",
        ],
        capture_output=True,
        text=True,
    )
    if tag_result.returncode and "(http 404)" not in tag_result.stderr.lower():
        raise RuntimeError(
            f"cannot delete development tag {tag}: "
            f"{tag_result.stderr.strip() or 'GitHub API request failed'}"
        )


def cleanup_pull_request_releases(repository: str, pull_number: str) -> None:
    """Delete every series-scoped development Release for one pull request."""
    if PULL_REQUEST_PATTERN.fullmatch(pull_number) is None:
        raise ValueError("invalid pull request number")
    result = subprocess.run(
        [
            "gh", "api", "--paginate", "--slurp",
            f"repos/{repository}/releases?per_page=100",
        ],
        check=True,
        capture_output=True,
        text=True,
    )
    try:
        releases = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise RuntimeError("cannot list pull request releases") from error
    refs_result = subprocess.run(
        [
            "gh", "api", "--paginate", "--slurp",
            f"repos/{repository}/git/matching-refs/tags/pr-{pull_number}-",
        ],
        check=True,
        capture_output=True,
        text=True,
    )
    try:
        refs = json.loads(refs_result.stdout)
    except json.JSONDecodeError as error:
        raise RuntimeError("cannot list pull request tags") from error
    tags = set(select_pull_request_release_tags(releases, pull_number))
    tags.update(select_pull_request_tag_refs(refs, pull_number))
    for tag in sorted(tags):
        cleanup_development_release(repository, tag)


def parse_channel(value: str) -> tuple[str, Path]:
    """Parse one exact mutable channel tag and its staged repository path."""
    tag, separator, directory = value.partition("=")
    if not separator or not tag or not directory:
        raise argparse.ArgumentTypeError("channel must be TAG=DIRECTORY")
    return tag, Path(directory)


def publish(repository: str, tag: str, directory: Path, prerelease: bool) -> None:
    """Create (if needed) and replace the assets of one GitHub Release."""
    title = package_release_title(tag)
    create = ["release", "create", tag, "--repo", repository, "--title", title]
    if prerelease:
        create.append("--prerelease")
    else:
        create.append("--latest=false")
    result = subprocess.run(["gh", *create], capture_output=True, text=True)
    if result.returncode and "already exists" not in result.stderr.lower():
        raise RuntimeError(result.stderr.strip() or "cannot create GitHub Release")
    edit_package_release_title(repository, tag, prerelease)
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
    result = subprocess.run(
        ["gh", "release", "view", tag, "--repo", repository, "--json", "assets"],
        check=True,
        capture_output=True,
        text=True,
    )
    payload = json.loads(result.stdout)
    published = payload.get("assets")
    if not isinstance(published, list) or {
        asset.get("name") for asset in published if isinstance(asset, dict)
    } != asset_names:
        raise RuntimeError(f"published GitHub Release {tag} has an unexpected asset set")
    with tempfile.TemporaryDirectory() as temporary_directory:
        verified = snapshot_release(repository, tag, Path(temporary_directory))
        if not snapshot_matches_directory(verified, directory):
            raise RuntimeError(f"published GitHub Release {tag} has unexpected asset bytes")


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
    validate_provenance = commands.add_parser("validate-bind-provenance")
    validate_provenance.add_argument("--provenance", type=Path, required=True)
    validate_provenance.add_argument("--profile", type=Path, required=True)
    validate_provenance.add_argument("--series", required=True)
    validate_provenance.add_argument("--freebsd-release", required=True)
    validate_provenance.add_argument("--target-pkg-metadata", type=Path, required=True)
    validate_metadata = commands.add_parser("validate-build-metadata")
    validate_metadata.add_argument("--metadata", type=Path, required=True)
    validate_metadata.add_argument("--upstream", type=Path, required=True)
    validate_metadata.add_argument("--provenance", type=Path, required=True)
    validate_metadata.add_argument("--series", required=True)
    validate_metadata.add_argument("--source-commit", required=True)
    validate_metadata.add_argument("--target-pkg-metadata", type=Path, required=True)
    snapshot_tag = commands.add_parser("snapshot-tag")
    snapshot_tag.add_argument("series")
    snapshot_tag.add_argument("version")
    source_tag = commands.add_parser("source-release-tag")
    source_tag.add_argument("series")
    source_tag.add_argument("version")
    stage_channel = commands.add_parser("stage-channel")
    stage_channel.add_argument("--packages-directory", type=Path, required=True)
    stage_channel.add_argument("--output", type=Path, required=True)
    stage_channel.add_argument("--private-key", type=Path, required=True)
    stage_channel.add_argument("--pkg-command", default="pkg")
    stage_channel.add_argument("--target-pkg-metadata", type=Path, required=True)
    reuse_snapshot = commands.add_parser("reuse-snapshot")
    reuse_snapshot.add_argument("--repository", required=True)
    reuse_snapshot.add_argument("--series", required=True)
    reuse_snapshot.add_argument("--version", required=True)
    reuse_snapshot.add_argument("--source-commit", required=True)
    reuse_snapshot.add_argument("--output", type=Path, required=True)
    reuse_snapshot.add_argument("--public-key", type=Path, required=True)
    publish_parser = commands.add_parser("publish")
    publish_parser.add_argument("--repository", required=True)
    publish_parser.add_argument("--series", required=True)
    publish_parser.add_argument("--directory", type=Path, required=True)
    publish_parser.add_argument("--prerelease", action="store_true")
    publish_tag = commands.add_parser("publish-tag")
    publish_tag.add_argument("--repository", required=True)
    publish_tag.add_argument("--tag", required=True)
    publish_tag.add_argument("--directory", type=Path, required=True)
    publish_immutable = commands.add_parser("publish-immutable")
    publish_immutable.add_argument("--repository", required=True)
    publish_immutable.add_argument("--tag", required=True)
    publish_immutable.add_argument("--directory", type=Path, required=True)
    publish_immutable.add_argument("--title", required=True)
    publish_channels_parser = commands.add_parser("publish-channels")
    publish_channels_parser.add_argument("--repository", required=True)
    publish_channels_parser.add_argument("--recovery", type=Path, required=True)
    publish_channels_parser.add_argument("--channel", type=parse_channel, action="append", required=True)
    prune = commands.add_parser("prune-snapshots")
    prune.add_argument("--repository", required=True)
    prune.add_argument("--series", required=True)
    prune.add_argument("--keep", type=int, default=5)
    mark_latest = commands.add_parser("mark-latest-package-channel")
    mark_latest.add_argument("--repository", required=True)
    cleanup_tag = commands.add_parser("cleanup-tag")
    cleanup_tag.add_argument("--repository", required=True)
    cleanup_tag.add_argument("--tag", required=True)
    cleanup_pull = commands.add_parser("cleanup-pull-request")
    cleanup_pull.add_argument("--repository", required=True)
    cleanup_pull.add_argument("--pull-number", required=True)
    bootstrap = commands.add_parser("bootstrap")
    bootstrap.add_argument("--output", type=Path, required=True)
    bootstrap.add_argument("--base-url", required=True)
    bootstrap.add_argument("--series", required=True)
    bootstrap.add_argument("--public-key-path", required=True)
    arguments = parser.parse_args()
    if arguments.command == "validate-bind-provenance":
        provenance = json.loads(arguments.provenance.read_text(encoding="utf-8"))
        validate_bind_provenance(
            provenance, bind920_profile.load_profile(arguments.profile),
            arguments.series, arguments.freebsd_release,
            target_pkg.load_target(
                arguments.target_pkg_metadata, arguments.series
            ).record(),
        )
    elif arguments.command == "validate-build-metadata":
        validate_build_metadata(
            arguments.metadata,
            arguments.upstream,
            arguments.provenance,
            arguments.target_pkg_metadata,
            arguments.series,
            arguments.source_commit,
        )
    elif arguments.command == "snapshot-tag":
        print(snapshot_channel_tag(arguments.series, arguments.version))
    elif arguments.command == "source-release-tag":
        print(source_release_tag(arguments.series, arguments.version))
    elif arguments.command == "stage-channel":
        for asset in stage_channel_repository(
            arguments.packages_directory,
            arguments.output,
            arguments.private_key,
            arguments.pkg_command,
            arguments.target_pkg_metadata,
        ):
            print(asset)
    elif arguments.command == "reuse-snapshot":
        reused = materialize_existing_snapshot(
            arguments.repository,
            arguments.series,
            arguments.version,
            arguments.source_commit,
            arguments.output,
            arguments.public_key,
        )
        print("true" if reused else "false")
    elif arguments.command == "publish":
        publish(arguments.repository, channel_tag(arguments.series), arguments.directory, arguments.prerelease)
    elif arguments.command == "publish-tag":
        publish(arguments.repository, arguments.tag, arguments.directory, False)
    elif arguments.command == "publish-immutable":
        publish_immutable_release(
            arguments.repository, arguments.tag, arguments.directory, arguments.title
        )
    elif arguments.command == "publish-channels":
        publish_channels(arguments.repository, arguments.channel, arguments.recovery)
    elif arguments.command == "prune-snapshots":
        prune_snapshots(arguments.repository, arguments.series, arguments.keep)
    elif arguments.command == "mark-latest-package-channel":
        mark_latest_package_channel(arguments.repository)
    elif arguments.command == "cleanup-tag":
        cleanup_development_release(arguments.repository, arguments.tag)
    elif arguments.command == "cleanup-pull-request":
        cleanup_pull_request_releases(arguments.repository, arguments.pull_number)
    else:
        write_bootstrap(
            arguments.output, arguments.base_url, arguments.series, arguments.public_key_path
        )


if __name__ == "__main__":
    raise SystemExit(main())
