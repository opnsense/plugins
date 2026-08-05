#!/usr/bin/env python3
"""Regression coverage for split package channels and plugin rollback archives."""

from __future__ import annotations

import importlib.util
import json
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch


MODULE_PATH = Path(__file__).resolve().parents[1] / "release_channel.py"
SPEC = importlib.util.spec_from_file_location("release_channel", MODULE_PATH)
assert SPEC is not None and SPEC.loader is not None
release_channel = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release_channel)


class ChannelTagTest(unittest.TestCase):
    def test_split_channel_tags_are_series_scoped(self) -> None:
        """Latest, rollback, and fallback channels must never share a tag."""
        self.assertEqual("pkg-26.7", release_channel.channel_tag("26.7"))
        self.assertEqual("pkg-26.7-archive", release_channel.archive_channel_tag("26.7"))
        self.assertEqual("pkg-26.7-bind920", release_channel.bind920_channel_tag("26.7"))

    def test_split_channel_tags_reject_invalid_series(self) -> None:
        """Channel names remain constrained to the supported series form."""
        for channel in (
            release_channel.channel_tag,
            release_channel.archive_channel_tag,
            release_channel.bind920_channel_tag,
        ):
            with self.assertRaisesRegex(ValueError, "invalid series"):
                channel("26.7/archive")


class SplitRepositoryStageTest(unittest.TestCase):
    def test_plugin_stage_excludes_bind_packages_and_retains_build_metadata(self) -> None:
        """The plugin catalogue must be independently installable and rollback-ready."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            packages = root / "packages"
            packages.mkdir()
            for name in (
                "bind-tools-9.20.26_1.pkg",
                "bind920-9.20.26_1.pkg",
                "os-bind-rp-1.36_1.pkg",
                "os-bind-rp-1.36_2.pkg",
            ):
                (packages / name).touch()
            (packages / "build-metadata.txt").write_text("series=26.7\n", encoding="utf-8")
            key = root / "private.pem"
            key.touch()

            def fake_repo(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
                Path(command[2]).joinpath("meta.conf").touch()
                return subprocess.CompletedProcess(command, 0)

            with (
                patch.object(release_channel, "validate_plugin_package_manifests"),
                patch.object(release_channel, "newest_plugin_packages", side_effect=lambda packages, _: packages),
                patch.object(release_channel.subprocess, "run", side_effect=fake_repo),
            ):
                assets = release_channel.stage_plugin_repository(
                    packages, root / "plugin", key, "pkg"
                )

            names = {asset.name for asset in assets}
            self.assertEqual(
                {"os-bind-rp-1.36_1.pkg", "os-bind-rp-1.36_2.pkg", "build-metadata.txt", "meta.conf"},
                names,
            )

    def test_bind_stage_excludes_plugin_packages_and_requires_provenance(self) -> None:
        """Fallback BIND remains a separate signed repository with provenance."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            packages = root / "packages"
            packages.mkdir()
            for name in (
                "bind-tools-9.20.26_1.pkg",
                "bind920-9.20.26_1.pkg",
                "os-bind-rp-1.36_2.pkg",
            ):
                (packages / name).touch()
            key = root / "private.pem"
            key.touch()

            def fake_repo(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
                Path(command[2]).joinpath("meta.conf").touch()
                return subprocess.CompletedProcess(command, 0)

            with (
                patch.object(release_channel, "validate_bind920_package_manifests"),
                patch.object(release_channel.subprocess, "run", side_effect=fake_repo),
            ):
                with self.assertRaisesRegex(ValueError, "BIND provenance"):
                    release_channel.stage_bind920_repository(packages, root / "missing", key, "pkg")
                (packages / "bind920-provenance.json").write_text("{}\n", encoding="utf-8")
                assets = release_channel.stage_bind920_repository(
                    packages, root / "bind", key, "pkg"
                )

            self.assertEqual(
                {
                    "bind-tools-9.20.26_1.pkg",
                    "bind920-9.20.26_1.pkg",
                    "bind920-provenance.json",
                    "meta.conf",
                },
                {asset.name for asset in assets},
            )

    def test_asset_order_accepts_a_plugin_only_catalogue(self) -> None:
        """Publishing a decoupled latest channel must not require BIND assets."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            directory = Path(temporary_directory)
            for name in (
                "os-bind-rp-1.36_2.pkg",
                "build-metadata.txt",
                "data.pkg",
                "meta.conf",
                "resolver-plugins.pub",
            ):
                (directory / name).touch()
            self.assertEqual(
                [
                    "os-bind-rp-1.36_2.pkg",
                    "build-metadata.txt",
                    "data.pkg",
                    "resolver-plugins.pub",
                    "meta.conf",
                ],
                [path.name for path in release_channel.asset_order(directory)],
            )


class PublicationRecoveryTest(unittest.TestCase):
    def test_failed_promotion_restores_all_channels_from_preserved_bytes(self) -> None:
        """A failed later upload restores already changed releases without remote downloads."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            archive = root / "archive"
            latest = root / "latest"
            archive.mkdir()
            latest.mkdir()
            (archive / "os-bind-rp-1.36_1.pkg").write_bytes(b"archive-new")
            (latest / "os-bind-rp-1.36_2.pkg").write_bytes(b"latest-new")
            restored: list[tuple[str, bytes]] = []

            def fake_snapshot(repository: str, tag: str, recovery: Path):
                directory = recovery / tag
                directory.mkdir(parents=True, exist_ok=True)
                asset = directory / "old.pkg"
                asset.write_bytes(f"{tag}-old".encode())
                manifest = recovery / f"{tag}.json"
                manifest.write_text(json.dumps({"old.pkg": release_channel.sha256(asset)}))
                return release_channel.ReleaseSnapshot(tag, True, directory, manifest)

            def fake_publish(repository: str, tag: str, directory: Path, prerelease: bool) -> None:
                if tag == "pkg-26.7":
                    raise RuntimeError("latest upload failed")

            def fake_restore(repository: str, snapshot: object) -> None:
                assert isinstance(snapshot, release_channel.ReleaseSnapshot)
                restored.append((snapshot.tag, (snapshot.directory / "old.pkg").read_bytes()))

            with (
                patch.object(release_channel, "snapshot_release", side_effect=fake_snapshot),
                patch.object(release_channel, "publish", side_effect=fake_publish),
                patch.object(release_channel, "restore_release", side_effect=fake_restore),
            ):
                with self.assertRaisesRegex(RuntimeError, "latest upload failed"):
                    release_channel.publish_channels(
                        "resolver-plugins/plugins",
                        [("pkg-26.7-archive", archive), ("pkg-26.7", latest)],
                        root / "recovery",
                    )

            self.assertEqual(
                [("pkg-26.7", b"pkg-26.7-old"), ("pkg-26.7-archive", b"pkg-26.7-archive-old")],
                restored,
            )


class PluginManifestValidationTest(unittest.TestCase):
    def test_archive_rejects_a_plugin_without_the_minimum_bind_formula(self) -> None:
        """An old exact BIND dependency cannot enter the formula-compatible archive."""
        package = Path("/tmp/os-bind-rp-1.36_2.pkg")
        identity = (("os-bind-rp", "1.36_2", "opnsense/os-bind-rp", "FreeBSD:15:amd64"), set())
        with (
            patch.object(release_channel, "query_package", return_value=identity),
            patch.object(release_channel, "read_package_manifest", return_value={"dep_formula": "bind920 = 9.20.26"}),
        ):
            with self.assertRaisesRegex(ValueError, "dependency formula"):
                release_channel.validate_plugin_package_manifests([package], "pkg")

    def test_archive_accepts_the_declared_minimum_bind_formula(self) -> None:
        """Every retained plugin uses the same BIND compatibility floor."""
        package = Path("/tmp/os-bind-rp-1.36_2.pkg")
        identity = (("os-bind-rp", "1.36_2", "opnsense/os-bind-rp", "FreeBSD:15:amd64"), set())
        with (
            patch.object(release_channel, "query_package", return_value=identity),
            patch.object(release_channel, "read_package_manifest", return_value={"dep_formula": "bind920 >= 9.20.26"}),
        ):
            release_channel.validate_plugin_package_manifests([package], "pkg")

    def test_archive_keeps_the_five_newest_versions_by_pkg_ordering(self) -> None:
        """Retention uses pkg ordering rather than a lexical filename sort."""
        packages = [Path(f"/tmp/os-bind-rp-1.36_{version}.pkg") for version in range(1, 7)]

        def fake_version(package: Path, _: str) -> str:
            return package.stem.rsplit("_", 1)[1]

        def fake_run(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            left, right = int(command[-2]), int(command[-1])
            result = ">" if left > right else "<" if left < right else "="
            return subprocess.CompletedProcess(command, 0, stdout=result)

        with (
            patch.object(release_channel, "plugin_package_version", side_effect=fake_version),
            patch.object(release_channel.subprocess, "run", side_effect=fake_run),
        ):
            retained = release_channel.newest_plugin_packages(packages, "pkg")

        self.assertEqual(["6", "5", "4", "3", "2"], [path.stem.rsplit("_", 1)[1] for path in retained])


if __name__ == "__main__":
    unittest.main()
