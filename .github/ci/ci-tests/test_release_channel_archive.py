#!/usr/bin/env python3
"""Regression coverage for self-contained package channels and rollback snapshots."""

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
    def test_source_release_tag_identifies_the_series_and_plugin_version(self) -> None:
        self.assertEqual(
            "os-bind-rp-26.7-1.36_7", release_channel.source_release_tag("26.7", "1.36_7")
        )

    def test_channel_tags_are_series_scoped(self) -> None:
        """Current and immutable snapshot channels must never share a tag."""
        self.assertEqual("pkg-26.7", release_channel.channel_tag("26.7"))
        self.assertEqual(
            "pkg-26.7-os-bind-rp-1.36_2",
            release_channel.snapshot_channel_tag("26.7", "1.36_2"),
        )

    def test_channel_tags_reject_invalid_series(self) -> None:
        """Channel names remain constrained to the supported series form."""
        with self.assertRaisesRegex(ValueError, "invalid series"):
            release_channel.channel_tag("26.7/archive")
        with self.assertRaisesRegex(ValueError, "invalid package version"):
            release_channel.snapshot_channel_tag("26.7", "1.36/2")


class SelfContainedRepositoryStageTest(unittest.TestCase):
    def test_stage_channel_contains_the_plugin_bind_pair_and_audit_manifest(self) -> None:
        """A self-contained channel carries one plugin, its BIND pair, and auditable metadata."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            packages = root / "packages"
            packages.mkdir()
            for name in (
                "bind-tools-9.20.26_1.pkg",
                "bind920-9.20.26_1.pkg",
                "os-bind-rp-1.36_7.pkg",
            ):
                (packages / name).write_bytes(name.encode())
            (packages / "bind920-provenance.json").write_text(
                json.dumps(
                    {
                        "schema": 1,
                        "fingerprint": "f" * 64,
                        "series": "26.7",
                        "freebsd_release": "15.1",
                        "architecture": "x86_64",
                        "packages": {
                            "bind-tools": {
                                "name": "bind-tools",
                                "version": "9.20.26_1",
                                "origin": "dns/bind-tools",
                                "filename": "bind-tools-9.20.26_1.pkg",
                            },
                            "bind920": {
                                "name": "bind920",
                                "version": "9.20.26_1",
                                "origin": "dns/bind920",
                                "filename": "bind920-9.20.26_1.pkg",
                            },
                        }
                    }
                ),
                encoding="utf-8",
            )
            (packages / "build-metadata.txt").write_text(
                "series=26.7\n"
                "uname=FreeBSD test 15.1\n"
                "pkg_abi=FreeBSD:15:amd64\n"
                "bind920=9.20.26_1\n"
                "bind_source=resolver\n"
                "opnsense=26.7\n"
                "opnsense_core_commit=2222222222222222222222222222222222222222\n"
                "source_commit=0123456789abcdef\n"
                "upstream_commit=1111111111111111111111111111111111111111\n"
                "core_commit=2222222222222222222222222222222222222222\n"
                "tools_tag=26.7\n"
                "freebsd_release=15.1\n",
                encoding="utf-8",
            )
            key = root / "private.pem"
            key.touch()

            def fake_repo(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
                Path(command[2]).joinpath("meta.conf").touch()
                return subprocess.CompletedProcess(command, 0)

            with (
                patch.object(release_channel, "validate_channel_package_manifests"),
                patch.object(release_channel.subprocess, "run", side_effect=fake_repo),
            ):
                assets = release_channel.stage_channel_repository(
                    packages, root / "channel", key, "pkg"
                )

            names = {asset.name for asset in assets}
            self.assertEqual(
                {
                    "bind-tools-9.20.26_1.pkg",
                    "bind920-9.20.26_1.pkg",
                    "os-bind-rp-1.36_7.pkg",
                    "bind920-provenance.json",
                    "build-metadata.txt",
                    "channel.json",
                    "meta.conf",
                },
                names,
            )
            manifest = json.loads((root / "channel/channel.json").read_text(encoding="utf-8"))
            self.assertEqual("26.7", manifest["series"])
            self.assertEqual("1.36_7", manifest["plugin_version"])
            self.assertEqual("0123456789abcdef", manifest["source_commit"])
            self.assertEqual("f" * 64, manifest["bind"]["fingerprint"])
            self.assertEqual("15.1", manifest["build"]["freebsd_release"])
            self.assertEqual("26.7", manifest["build"]["tools_tag"])
            self.assertEqual(
                release_channel.sha256(packages / "os-bind-rp-1.36_7.pkg"),
                manifest["packages"]["os-bind-rp-1.36_7.pkg"],
            )
            (root / "channel/resolver-plugins.pub").write_text("public key", encoding="utf-8")
            (root / "channel/packagesite.pkg").touch()
            release_channel.validate_channel_directory(root / "channel")

    def test_asset_order_puts_repository_metadata_after_packages(self) -> None:
        """Publishing a self-contained channel uploads packages before catalog metadata."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            directory = Path(temporary_directory)
            for name in (
                "bind-tools-9.20.26_1.pkg",
                "bind920-9.20.26_1.pkg",
                "os-bind-rp-1.36_2.pkg",
                "build-metadata.txt",
                "data.pkg",
                "meta.conf",
                "resolver-plugins.pub",
            ):
                (directory / name).touch()
            self.assertEqual(
                [
                    "bind-tools-9.20.26_1.pkg",
                    "bind920-9.20.26_1.pkg",
                    "os-bind-rp-1.36_2.pkg",
                    "build-metadata.txt",
                    "data.pkg",
                    "resolver-plugins.pub",
                    "meta.conf",
                ],
                [path.name for path in release_channel.asset_order(directory)],
            )


class PublicationRecoveryTest(unittest.TestCase):
    def test_recovery_channel_rejects_an_audit_checksum_mismatch(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            directory = Path(temporary_directory)
            packages = [
                directory / "bind-tools-9.20.26_1.pkg",
                directory / "bind920-9.20.26_1.pkg",
                directory / "os-bind-rp-1.36_2.pkg",
            ]
            for package in packages:
                package.write_bytes(package.name.encode())
            (directory / "bind920-provenance.json").write_text(
                json.dumps(
                    {
                        "series": "26.7",
                        "freebsd_release": "15.1",
                        "packages": {
                            "bind-tools": {
                                "name": "bind-tools",
                                "version": "9.20.26_1",
                                "origin": "dns/bind-tools",
                                "filename": packages[0].name,
                            },
                            "bind920": {
                                "name": "bind920",
                                "version": "9.20.26_1",
                                "origin": "dns/bind920",
                                "filename": packages[1].name,
                            },
                        },
                    }
                ),
                encoding="utf-8",
            )
            (directory / "build-metadata.txt").write_text(
                "series=26.7\nuname=FreeBSD test\npkg_abi=FreeBSD:15:amd64\n"
                "bind920=9.20.26_1\nbind_source=resolver\nopnsense=26.7\n"
                "opnsense_core_commit=core\nupstream_commit=upstream\ncore_commit=core\n"
                "tools_tag=26.7.1\nfreebsd_release=15.1\nsource_commit=source\n",
                encoding="utf-8",
            )
            (directory / "channel.json").write_text(
                json.dumps(
                    {
                        "schema": 1,
                        "series": "26.7",
                        "plugin_version": "1.36_2",
                        "source_commit": "source",
                        "build": {},
                        "bind": {},
                        "packages": {package.name: "0" * 64 for package in packages},
                    }
                ),
                encoding="utf-8",
            )
            (directory / "resolver-plugins.pub").write_text("public key", encoding="utf-8")
            (directory / "meta.conf").touch()
            (directory / "packagesite.pkg").touch()

            with self.assertRaisesRegex(ValueError, "checksum"):
                release_channel.validate_channel_directory(directory)

    def test_release_snapshot_comparison_detects_a_remote_change(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            manifests = []
            snapshots = []
            for number, digest in enumerate(("a" * 64, "b" * 64)):
                directory = root / str(number)
                directory.mkdir()
                manifest = root / f"{number}.json"
                manifest.write_text(json.dumps({"asset.pkg": digest}), encoding="utf-8")
                manifests.append(manifest)
                snapshots.append(
                    release_channel.ReleaseSnapshot("pkg-26.7", True, directory, manifest)
                )
            self.assertFalse(release_channel.release_snapshots_match(*snapshots))

    def test_failed_promotion_restores_all_channels_from_preserved_bytes(self) -> None:
        """A failed later upload restores already changed releases without remote downloads."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            snapshot = root / "snapshot"
            latest = root / "latest"
            snapshot.mkdir()
            latest.mkdir()
            (snapshot / "os-bind-rp-1.36_1.pkg").write_bytes(b"snapshot-new")
            (latest / "os-bind-rp-1.36_2.pkg").write_bytes(b"latest-new")
            restored: list[tuple[str, bytes]] = []

            def fake_snapshot(repository: str, tag: str, recovery: Path):
                directory = recovery / tag
                if "-os-bind-rp-" in tag:
                    return release_channel.ReleaseSnapshot(
                        tag, False, directory, recovery / f"{tag}.json"
                    )
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
                restored.append(
                    (
                        snapshot.tag,
                        (snapshot.directory / "old.pkg").read_bytes()
                        if snapshot.existed
                        else b"absent",
                    )
                )

            with (
                patch.object(release_channel, "snapshot_release", side_effect=fake_snapshot),
                patch.object(release_channel, "validate_channel_directory"),
                patch.object(release_channel, "publish", side_effect=fake_publish),
                patch.object(release_channel, "restore_release", side_effect=fake_restore),
            ):
                with self.assertRaisesRegex(RuntimeError, "latest upload failed"):
                    release_channel.publish_channels(
                        "resolver-plugins/plugins",
                        [("pkg-26.7-os-bind-rp-1.36_2", snapshot), ("pkg-26.7", latest)],
                        root / "recovery",
                    )

            self.assertEqual(
                [("pkg-26.7", b"pkg-26.7-old"), ("pkg-26.7-os-bind-rp-1.36_2", b"absent")],
                restored,
            )

    def test_retry_keeps_a_byte_identical_immutable_snapshot(self) -> None:
        """A full retry may reuse an identical snapshot without rewriting it."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged_snapshot = root / "staged-snapshot"
            staged_current = root / "staged-current"
            remote_snapshot = root / "remote-snapshot"
            for directory in (staged_snapshot, staged_current, remote_snapshot):
                directory.mkdir()
            (staged_snapshot / "asset.pkg").write_bytes(b"immutable")
            (remote_snapshot / "asset.pkg").write_bytes(b"immutable")
            (staged_current / "asset.pkg").write_bytes(b"current")
            manifest = root / "snapshot.json"
            manifest.write_text(
                json.dumps({"asset.pkg": release_channel.sha256(remote_snapshot / "asset.pkg")})
            )
            immutable = release_channel.ReleaseSnapshot(
                "pkg-26.7-os-bind-rp-1.36_2", True, remote_snapshot, manifest
            )
            absent_current = release_channel.ReleaseSnapshot(
                "pkg-26.7", False, root / "missing", root / "missing.json"
            )
            published: list[str] = []

            def fake_snapshot(repository: str, tag: str, recovery: Path):
                return immutable if "-os-bind-rp-" in tag else absent_current

            with (
                patch.object(release_channel, "snapshot_release", side_effect=fake_snapshot),
                patch.object(
                    release_channel,
                    "publish",
                    side_effect=lambda repository, tag, directory, prerelease: published.append(tag),
                ),
            ):
                release_channel.publish_channels(
                    "resolver-plugins/repository",
                    [
                        (immutable.tag, staged_snapshot),
                        (absent_current.tag, staged_current),
                    ],
                    root / "recovery",
                )

            self.assertEqual(["pkg-26.7"], published)

    def test_retry_rejects_changed_bytes_for_an_immutable_snapshot(self) -> None:
        """The same immutable tag must never identify different package bytes."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged = root / "staged"
            remote = root / "remote"
            staged.mkdir()
            remote.mkdir()
            (staged / "asset.pkg").write_bytes(b"new")
            (remote / "asset.pkg").write_bytes(b"old")
            manifest = root / "snapshot.json"
            manifest.write_text(
                json.dumps({"asset.pkg": release_channel.sha256(remote / "asset.pkg")})
            )
            immutable = release_channel.ReleaseSnapshot(
                "pkg-26.7-os-bind-rp-1.36_2", True, remote, manifest
            )

            with patch.object(release_channel, "snapshot_release", return_value=immutable):
                with self.assertRaisesRegex(RuntimeError, "different bytes"):
                    release_channel.publish_channels(
                        "resolver-plugins/repository",
                        [(immutable.tag, staged)],
                        root / "recovery",
                    )

    def test_retry_is_a_noop_when_snapshot_and_current_are_identical(self) -> None:
        """A repeated promotion with identical remote state changes no release."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            channels = []
            snapshots = {}
            for tag in ("pkg-26.7-os-bind-rp-1.36_2", "pkg-26.7"):
                staged = root / f"staged-{tag}"
                remote = root / f"remote-{tag}"
                staged.mkdir()
                remote.mkdir()
                (staged / "asset.pkg").write_bytes(tag.encode())
                (remote / "asset.pkg").write_bytes(tag.encode())
                manifest = root / f"{tag}.json"
                manifest.write_text(
                    json.dumps({"asset.pkg": release_channel.sha256(remote / "asset.pkg")})
                )
                channels.append((tag, staged))
                snapshots[tag] = release_channel.ReleaseSnapshot(
                    tag, True, remote, manifest
                )

            with (
                patch.object(
                    release_channel,
                    "snapshot_release",
                    side_effect=lambda repository, tag, recovery: snapshots[tag],
                ),
                patch.object(release_channel, "validate_channel_directory"),
                patch.object(release_channel, "publish") as publish,
            ):
                release_channel.publish_channels(
                    "resolver-plugins/repository", channels, root / "recovery"
                )

            publish.assert_not_called()

    def test_retry_cannot_replace_a_different_current_channel(self) -> None:
        """Retrying an older snapshot must not roll a newer current channel back."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged_snapshot = root / "staged-snapshot"
            staged_current = root / "staged-current"
            remote_snapshot = root / "remote-snapshot"
            remote_current = root / "remote-current"
            for directory in (
                staged_snapshot,
                staged_current,
                remote_snapshot,
                remote_current,
            ):
                directory.mkdir()
            (staged_snapshot / "asset.pkg").write_bytes(b"snapshot-a")
            (remote_snapshot / "asset.pkg").write_bytes(b"snapshot-a")
            (staged_current / "asset.pkg").write_bytes(b"current-a")
            (remote_current / "asset.pkg").write_bytes(b"current-b")

            def release_snapshot(tag: str, directory: Path) -> object:
                manifest = root / f"{tag}.json"
                manifest.write_text(
                    json.dumps({"asset.pkg": release_channel.sha256(directory / "asset.pkg")})
                )
                return release_channel.ReleaseSnapshot(tag, True, directory, manifest)

            immutable = release_snapshot(
                "pkg-26.7-os-bind-rp-1.36_2", remote_snapshot
            )
            current = release_snapshot("pkg-26.7", remote_current)

            def fake_snapshot(repository: str, tag: str, recovery: Path):
                return immutable if "-os-bind-rp-" in tag else current

            with (
                patch.object(release_channel, "snapshot_release", side_effect=fake_snapshot),
                patch.object(release_channel, "validate_channel_directory"),
                patch.object(release_channel, "publish") as publish,
            ):
                with self.assertRaisesRegex(RuntimeError, "current channel has different bytes"):
                    release_channel.publish_channels(
                        "resolver-plugins/repository",
                        [
                            (immutable.tag, staged_snapshot),
                            (current.tag, staged_current),
                        ],
                        root / "recovery",
                    )

            publish.assert_not_called()

    def test_restore_absent_release_accepts_a_not_found_delete(self) -> None:
        """A failed release creation has no remote state to restore."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            snapshot = release_channel.ReleaseSnapshot("pkg-26.7-test", False, root, root / "missing.json")
            result = subprocess.CompletedProcess(["gh"], 1, stderr="release not found")
            with patch.object(release_channel.subprocess, "run", return_value=result):
                release_channel.restore_release("resolver-plugins/plugins", snapshot)

    def test_snapshot_pruning_keeps_the_newest_five_immutable_tags(self) -> None:
        """Only a successful promotion may remove the sixth-oldest snapshot."""
        releases = [
            {"tag_name": f"pkg-26.7-os-bind-rp-1.36_{number}", "created_at": f"2026-01-0{number}T00:00:00Z"}
            for number in range(1, 7)
        ]
        # `gh api --paginate --slurp` returns one JSON array per fetched page.
        # Retention must therefore flatten all pages before selecting the oldest tag.
        result = subprocess.CompletedProcess(
            ["gh"], 0, stdout=json.dumps([releases[:3], releases[3:]])
        )
        deleted: list[list[str]] = []
        with (
            patch.object(release_channel.subprocess, "run", return_value=result),
            patch.object(release_channel, "run_gh", side_effect=deleted.append),
        ):
            release_channel.prune_snapshots("resolver-plugins/plugins", "26.7")

        self.assertEqual(
            [["release", "delete", "pkg-26.7-os-bind-rp-1.36_1", "--yes", "--repo", "resolver-plugins/plugins"]],
            deleted,
        )


class ChannelManifestValidationTest(unittest.TestCase):
    def package_identities(self):
        return [
            (("bind-tools", "9.20.26_1", "dns/bind-tools", "FreeBSD:15:amd64"), set()),
            (
                ("bind920", "9.20.26_1", "dns/bind920", "FreeBSD:15:amd64"),
                {("bind-tools", "dns/bind-tools", "9.20.26_1")},
            ),
            (("os-bind-rp", "1.36_2", "opnsense/os-bind-rp", "FreeBSD:15:amd64"), set()),
        ]

    def bind_records(self):
        return {
            "bind-tools": {
                "name": "bind-tools",
                "version": "9.20.26_1",
                "origin": "dns/bind-tools",
                "filename": "bind-tools-9.20.26_1.pkg",
            },
            "bind920": {
                "name": "bind920",
                "version": "9.20.26_1",
                "origin": "dns/bind920",
                "filename": "bind920-9.20.26_1.pkg",
            },
        }

    def validate_with_formula(self, formula: str) -> None:
        packages = [
            Path("/tmp/bind-tools-9.20.26_1.pkg"),
            Path("/tmp/bind920-9.20.26_1.pkg"),
            Path("/tmp/os-bind-rp-1.36_2.pkg"),
        ]
        with (
            patch.object(release_channel, "read_bind_package_records", return_value=self.bind_records()),
            patch.object(release_channel, "query_package", side_effect=self.package_identities()),
            patch.object(release_channel, "read_package_manifest", return_value={"dep_formula": formula}),
        ):
            release_channel.validate_channel_package_manifests(
                packages, Path("/tmp/bind920-provenance.json"), "pkg"
            )

    def test_channel_rejects_plugin_without_minimum_bind_formula(self) -> None:
        with self.assertRaisesRegex(ValueError, "dependency formula"):
            self.validate_with_formula("bind920 = 9.20.26")

    def test_channel_accepts_declared_minimum_bind_formula(self) -> None:
        self.validate_with_formula("bind920 >= 9.20.26")

    def test_channel_rejects_formula_with_exact_bind_edge(self) -> None:
        identities = self.package_identities()
        identities[2] = (
            identities[2][0],
            {("bind920", "dns/bind920", "9.20.26_1")},
        )
        packages = [
            Path("/tmp/bind-tools-9.20.26_1.pkg"),
            Path("/tmp/bind920-9.20.26_1.pkg"),
            Path("/tmp/os-bind-rp-1.36_2.pkg"),
        ]
        with (
            patch.object(release_channel, "read_bind_package_records", return_value=self.bind_records()),
            patch.object(release_channel, "query_package", side_effect=identities),
            patch.object(
                release_channel,
                "read_package_manifest",
                return_value={"dep_formula": "bind920 >= 9.20.26"},
            ),
        ):
            with self.assertRaisesRegex(ValueError, "exact BIND"):
                release_channel.validate_channel_package_manifests(
                    packages, Path("/tmp/bind920-provenance.json"), "pkg"
                )


if __name__ == "__main__":
    unittest.main()
