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
    def test_package_release_title_names_current_and_archive_purpose(self) -> None:
        self.assertEqual("26.1-latest", release_channel.package_release_title("pkg-26.1"))
        self.assertEqual(
            "26.1-archive-1.36_9",
            release_channel.package_release_title("pkg-26.1-os-bind-rp-1.36_9"),
        )

    def test_package_release_title_rejects_non_channel_tags(self) -> None:
        for tag in (
            "pkg-26.1-bind920",
            "pkg-26.1-os-bind-rp-1.36/9",
            "os-bind-rp-26.1-1.36_9",
        ):
            with self.subTest(tag=tag):
                with self.assertRaisesRegex(ValueError, "invalid package release tag"):
                    release_channel.package_release_title(tag)

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


class PullRequestReleaseCleanupTest(unittest.TestCase):
    def test_pull_request_release_selection_rejects_near_matches(self) -> None:
        releases = [
            [
                {"tag_name": "pr-51-26.7"},
                {"tag_name": "pr-51-26.1"},
                {"tag_name": "pr-510-26.7"},
                {"tag_name": "pr-51-26.7-extra"},
                {"tag_name": "os-bind-rp-26.7-1.36_2"},
            ]
        ]

        self.assertEqual(
            ["pr-51-26.1", "pr-51-26.7"],
            release_channel.select_pull_request_release_tags(releases, "51"),
        )

    def test_pull_request_release_cleanup_deletes_release_and_tag(self) -> None:
        commands: list[list[str]] = []

        def fake_run(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            commands.append(command)
            if command[:3] == ["gh", "api", "--method"]:
                return subprocess.CompletedProcess(command, 1, "", "gh: Not Found (HTTP 404)")
            return subprocess.CompletedProcess(command, 0, "", "")

        with patch.object(release_channel.subprocess, "run", side_effect=fake_run):
            release_channel.cleanup_development_release(
                "resolver-plugins/plugins", "pr-51-26.7"
            )

        self.assertEqual(
            [
                [
                    "gh", "release", "delete", "pr-51-26.7", "--yes",
                    "--repo", "resolver-plugins/plugins",
                ],
                [
                    "gh", "api", "--method", "DELETE",
                    "repos/resolver-plugins/plugins/git/refs/tags/pr-51-26.7",
                ],
            ],
            commands,
        )

    def test_missing_pull_request_release_cleanup_removes_an_orphaned_tag(self) -> None:
        commands: list[list[str]] = []

        def fake_run(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            commands.append(command)
            if command[:2] == ["gh", "release"]:
                return subprocess.CompletedProcess(command, 1, "", "release not found")
            return subprocess.CompletedProcess(command, 0, "", "")

        with patch.object(release_channel.subprocess, "run", side_effect=fake_run):
            release_channel.cleanup_development_release(
                "resolver-plugins/plugins", "pr-51-26.7"
            )

        self.assertEqual(
            [
                "gh", "api", "--method", "DELETE",
                "repos/resolver-plugins/plugins/git/refs/tags/pr-51-26.7",
            ],
            commands[1],
        )

    def test_pull_request_release_cleanup_does_not_hide_tag_api_failure(self) -> None:
        def fake_run(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            if command[:2] == ["gh", "release"]:
                return subprocess.CompletedProcess(command, 0, "", "")
            return subprocess.CompletedProcess(command, 1, "", "gh: Server Error (HTTP 500)")

        with patch.object(release_channel.subprocess, "run", side_effect=fake_run):
            with self.assertRaisesRegex(RuntimeError, "cannot delete development tag"):
                release_channel.cleanup_development_release(
                    "resolver-plugins/plugins", "pr-51-26.7"
                )

    def test_invalid_pull_request_release_inputs_fail_before_mutation(self) -> None:
        with patch.object(release_channel.subprocess, "run") as run:
            with self.assertRaisesRegex(ValueError, "invalid pull request number"):
                release_channel.cleanup_pull_request_releases(
                    "resolver-plugins/plugins", "51/../../master"
                )
            with self.assertRaisesRegex(ValueError, "invalid development release tag"):
                release_channel.cleanup_development_release(
                    "resolver-plugins/plugins", "pkg-26.7"
                )

        run.assert_not_called()

    def test_pull_request_release_cleanup_lists_and_deletes_exact_matches(self) -> None:
        deleted: list[str] = []

        def fake_run(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            if command[:3] == ["gh", "api", "--paginate"]:
                if "/releases?" in command[-1]:
                    payload = [[
                        {"tag_name": "pr-51-26.7"},
                        {"tag_name": "pr-51-26.1"},
                        {"tag_name": "pr-510-26.7"},
                        {"tag_name": "pkg-26.7"},
                    ]]
                else:
                    payload = [[]]
                return subprocess.CompletedProcess(command, 0, json.dumps(payload), "")
            if command[:3] == ["gh", "api", "--method"]:
                return subprocess.CompletedProcess(command, 1, "", "gh: Not Found (HTTP 404)")
            deleted.append(command[3])
            return subprocess.CompletedProcess(command, 0, "", "")

        with patch.object(release_channel.subprocess, "run", side_effect=fake_run):
            release_channel.cleanup_pull_request_releases(
                "resolver-plugins/plugins", "51"
            )

        self.assertEqual(["pr-51-26.1", "pr-51-26.7"], deleted)

    def test_pull_request_release_cleanup_discovers_an_orphaned_tag(self) -> None:
        deleted_refs: list[str] = []

        def fake_run(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            if command[:3] == ["gh", "api", "--paginate"]:
                if "/releases?" in command[-1]:
                    payload = [[]]
                else:
                    payload = [[
                        {"ref": "refs/tags/pr-51-26.7"},
                        {"ref": "refs/tags/pr-510-26.7"},
                        {"ref": "refs/tags/pkg-26.7"},
                    ]]
                return subprocess.CompletedProcess(command, 0, json.dumps(payload), "")
            if command[:2] == ["gh", "release"]:
                return subprocess.CompletedProcess(command, 1, "", "release not found")
            deleted_refs.append(command[-1])
            return subprocess.CompletedProcess(command, 0, "", "")

        with patch.object(release_channel.subprocess, "run", side_effect=fake_run):
            release_channel.cleanup_pull_request_releases(
                "resolver-plugins/plugins", "51"
            )

        self.assertEqual(
            ["repos/resolver-plugins/plugins/git/refs/tags/pr-51-26.7"],
            deleted_refs,
        )


class SelfContainedRepositoryStageTest(unittest.TestCase):
    def test_stage_channel_contains_the_plugin_bind_pair_and_audit_manifest(self) -> None:
        """A self-contained channel carries one plugin, its BIND pair, and auditable metadata."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            package_creator = {
                "name": "pkg",
                "version": "2.3.1_1",
                "origin": "ports-mgmt/pkg",
                "abi": "FreeBSD:15:amd64",
                "filename": "pkg-2.3.1_1.pkg",
                "sha256": "a" * 64,
                "pkg_static_sha256": "b" * 64,
            }
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
                        "schema": 2,
                        "fingerprint": "f" * 64,
                        "series": "26.7",
                        "freebsd_release": "15.1",
                        "architecture": "x86_64",
                        "package_creator": package_creator,
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
                "freebsd_release=15.1\n"
                "pkg_creator=2.3.1_1\n"
                "pkg_creator_sha256=" + "a" * 64 + "\n",
                encoding="utf-8",
            )
            target_metadata = root / "target-pkg.json"
            target_metadata.write_text(
                json.dumps(
                    {
                        "schema": 1,
                        "series": {
                            "26.1": dict(package_creator, abi="FreeBSD:14:amd64"),
                            "26.7": package_creator,
                        },
                    }
                ),
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
                    packages, root / "channel", key, "pkg", target_metadata
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
            self.assertEqual(2, manifest["schema"])
            self.assertEqual("26.7", manifest["series"])
            self.assertEqual("1.36_7", manifest["plugin_version"])
            self.assertEqual("0123456789abcdef", manifest["source_commit"])
            self.assertEqual("f" * 64, manifest["bind"]["fingerprint"])
            self.assertEqual("15.1", manifest["build"]["freebsd_release"])
            self.assertEqual("26.7", manifest["build"]["tools_tag"])
            self.assertEqual(package_creator, manifest["package_creator"])
            self.assertEqual(
                release_channel.sha256(packages / "os-bind-rp-1.36_7.pkg"),
                manifest["packages"]["os-bind-rp-1.36_7.pkg"],
            )
            (root / "channel/resolver-plugins.pub").write_text("public key", encoding="utf-8")
            (root / "channel/packagesite.pkg").touch()
            release_channel.validate_channel_directory(root / "channel")

            legacy_manifest = dict(manifest, schema=1)
            legacy_manifest.pop("package_creator")
            (root / "channel/channel.json").write_text(
                json.dumps(legacy_manifest), encoding="utf-8"
            )
            legacy_provenance = json.loads(
                (root / "channel/bind920-provenance.json").read_text(encoding="utf-8")
            )
            legacy_provenance["schema"] = 1
            legacy_provenance.pop("package_creator")
            (root / "channel/bind920-provenance.json").write_text(
                json.dumps(legacy_provenance), encoding="utf-8"
            )
            legacy_metadata = "\n".join(
                line
                for line in (root / "channel/build-metadata.txt").read_text().splitlines()
                if not line.startswith(("pkg_creator=", "pkg_creator_sha256="))
            )
            (root / "channel/build-metadata.txt").write_text(
                legacy_metadata + "\n", encoding="utf-8"
            )
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
    def test_existing_package_release_title_converges_during_publication(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            directory = root / "staged"
            directory.mkdir()
            asset = directory / "os-bind-rp-1.36_9.pkg"
            asset.write_bytes(b"package")
            manifest = root / "checksums.json"
            manifest.write_text(
                json.dumps({asset.name: release_channel.sha256(asset)}), encoding="utf-8"
            )
            snapshot = release_channel.ReleaseSnapshot(
                "pkg-26.1-os-bind-rp-1.36_9", True, directory, manifest
            )
            mutations: list[list[str]] = []

            def fake_run(
                command: list[str], **_: object
            ) -> subprocess.CompletedProcess[str]:
                if command[:3] == ["gh", "release", "create"]:
                    return subprocess.CompletedProcess(command, 1, "", "release already exists")
                if "--jq" in command:
                    return subprocess.CompletedProcess(command, 0, f"{asset.name}\n", "")
                return subprocess.CompletedProcess(
                    command, 0, json.dumps({"assets": [{"name": asset.name}]}), ""
                )

            with (
                patch.object(release_channel.subprocess, "run", side_effect=fake_run),
                patch.object(release_channel, "run_gh", side_effect=mutations.append),
                patch.object(release_channel, "snapshot_release", return_value=snapshot),
            ):
                release_channel.publish(
                    "resolver-plugins/repository",
                    snapshot.tag,
                    directory,
                    False,
                )

            self.assertIn(
                [
                    "release", "edit", snapshot.tag,
                    "--repo", "resolver-plugins/repository",
                    "--title", "26.1-archive-1.36_9",
                    "--latest=false",
                ],
                mutations,
            )

    def test_existing_identical_immutable_release_is_a_noop(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged = root / "staged"
            remote = root / "remote"
            staged.mkdir()
            remote.mkdir()
            for directory in (staged, remote):
                (directory / "os-bind-rp-1.36_2.pkg").write_bytes(b"plugin")
                (directory / "build-metadata.txt").write_bytes(b"metadata")
            manifest = root / "manifest.json"
            manifest.write_text(
                json.dumps(release_channel.directory_checksums(remote)), encoding="utf-8"
            )
            snapshot = release_channel.ReleaseSnapshot(
                "os-bind-rp-26.7-1.36_2", True, remote, manifest
            )
            with (
                patch.object(release_channel, "snapshot_release", return_value=snapshot),
                patch.object(release_channel, "run_gh") as run_gh,
            ):
                release_channel.publish_immutable_release(
                    "resolver-plugins/plugins",
                    snapshot.tag,
                    staged,
                    "os-bind-rp 26.7 1.36_2",
                )

            run_gh.assert_not_called()

    def test_existing_different_immutable_release_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged = root / "staged"
            remote = root / "remote"
            staged.mkdir()
            remote.mkdir()
            (staged / "os-bind-rp-1.36_2.pkg").write_bytes(b"new")
            (remote / "os-bind-rp-1.36_2.pkg").write_bytes(b"old")
            manifest = root / "manifest.json"
            manifest.write_text(
                json.dumps(release_channel.directory_checksums(remote)), encoding="utf-8"
            )
            snapshot = release_channel.ReleaseSnapshot(
                "os-bind-rp-26.7-1.36_2", True, remote, manifest
            )
            with patch.object(release_channel, "snapshot_release", return_value=snapshot):
                with self.assertRaisesRegex(RuntimeError, "different bytes"):
                    release_channel.publish_immutable_release(
                        "resolver-plugins/plugins",
                        snapshot.tag,
                        staged,
                        "os-bind-rp 26.7 1.36_2",
                    )

    def test_absent_immutable_release_is_created_and_verified(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged = root / "staged"
            staged.mkdir()
            (staged / "os-bind-rp-1.36_2.pkg").write_bytes(b"plugin")
            absent = release_channel.ReleaseSnapshot(
                "os-bind-rp-26.7-1.36_2",
                False,
                root / "missing",
                root / "missing.json",
            )
            manifest = root / "manifest.json"
            manifest.write_text(
                json.dumps(release_channel.directory_checksums(staged)), encoding="utf-8"
            )
            published = release_channel.ReleaseSnapshot(
                absent.tag, True, staged, manifest
            )
            calls: list[list[str]] = []
            with (
                patch.object(
                    release_channel,
                    "snapshot_release",
                    side_effect=(absent, published),
                ),
                patch.object(release_channel, "run_gh", side_effect=calls.append),
            ):
                release_channel.publish_immutable_release(
                    "resolver-plugins/plugins",
                    absent.tag,
                    staged,
                    "os-bind-rp 26.7 1.36_2",
                )

            self.assertEqual("release", calls[0][0])
            self.assertEqual("create", calls[0][1])
            self.assertIn("--latest=false", calls[0])

    def test_existing_snapshot_is_materialized_for_an_exact_release_retry(self) -> None:
        """A published version is reused instead of rebuilt under a new control commit."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            remote = root / "remote"
            remote.mkdir()
            (remote / "channel.json").write_text(
                json.dumps(
                    {
                        "series": "26.7",
                        "plugin_version": "1.36_2",
                        "source_commit": "a" * 40,
                    }
                ),
                encoding="utf-8",
            )
            (remote / "os-bind-rp-1.36_2.pkg").write_bytes(b"immutable")
            public_key = root / "resolver-plugins.pub"
            public_key.write_bytes(b"trusted key")
            (remote / public_key.name).write_bytes(public_key.read_bytes())
            snapshot = release_channel.ReleaseSnapshot(
                "pkg-26.7-os-bind-rp-1.36_2", True, remote, root / "manifest.json"
            )

            with (
                patch.object(release_channel, "snapshot_release", return_value=snapshot),
                patch.object(release_channel, "validate_channel_directory") as validate,
            ):
                reused = release_channel.materialize_existing_snapshot(
                    "resolver-plugins/repository",
                    "26.7",
                    "1.36_2",
                    "a" * 40,
                    root / "repository",
                    public_key,
                )

            self.assertTrue(reused)
            validate.assert_called_once_with(remote)
            for channel in ("current", "snapshot"):
                self.assertEqual(
                    b"immutable",
                    (root / "repository" / channel / "os-bind-rp-1.36_2.pkg").read_bytes(),
                )

    def test_absent_snapshot_leaves_signing_output_unmodified(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            snapshot = release_channel.ReleaseSnapshot(
                "pkg-26.7-os-bind-rp-1.36_3",
                False,
                root / "missing",
                root / "missing.json",
            )
            public_key = root / "resolver-plugins.pub"
            public_key.write_bytes(b"trusted key")
            with patch.object(release_channel, "snapshot_release", return_value=snapshot):
                reused = release_channel.materialize_existing_snapshot(
                    "resolver-plugins/repository",
                    "26.7",
                    "1.36_3",
                    "b" * 40,
                    root / "repository",
                    public_key,
                )

            self.assertFalse(reused)
            self.assertFalse((root / "repository").exists())

    def test_snapshot_reuse_rejects_different_release_source(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            remote = root / "remote"
            remote.mkdir()
            (remote / "channel.json").write_text(
                json.dumps(
                    {
                        "series": "26.7",
                        "plugin_version": "1.36_2",
                        "source_commit": "a" * 40,
                    }
                ),
                encoding="utf-8",
            )
            public_key = root / "resolver-plugins.pub"
            public_key.write_bytes(b"trusted key")
            (remote / public_key.name).write_bytes(public_key.read_bytes())
            snapshot = release_channel.ReleaseSnapshot(
                "pkg-26.7-os-bind-rp-1.36_2", True, remote, root / "manifest.json"
            )
            with (
                patch.object(release_channel, "snapshot_release", return_value=snapshot),
                patch.object(release_channel, "validate_channel_directory"),
            ):
                with self.assertRaisesRegex(ValueError, "does not match requested release"):
                    release_channel.materialize_existing_snapshot(
                        "resolver-plugins/repository",
                        "26.7",
                        "1.36_2",
                        "b" * 40,
                        root / "repository",
                        public_key,
                    )

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
                patch.object(
                    release_channel, "staged_source_descends_from_current", return_value=True
                ),
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
                patch.object(release_channel, "run_gh"),
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

    def test_retry_only_updates_titles_when_snapshot_and_current_are_identical(self) -> None:
        """A repeated promotion corrects titles without rewriting package assets."""
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

            mutations: list[list[str]] = []
            with (
                patch.object(
                    release_channel,
                    "snapshot_release",
                    side_effect=lambda repository, tag, recovery: snapshots[tag],
                ),
                patch.object(release_channel, "validate_channel_directory"),
                patch.object(release_channel, "publish") as publish,
                patch.object(release_channel, "run_gh", side_effect=mutations.append),
            ):
                release_channel.publish_channels(
                    "resolver-plugins/repository", channels, root / "recovery"
                )

            publish.assert_not_called()
            self.assertEqual(
                [
                    [
                        "release", "edit", "pkg-26.7-os-bind-rp-1.36_2",
                        "--repo", "resolver-plugins/repository",
                        "--title", "26.7-archive-1.36_2", "--latest=false",
                    ],
                    [
                        "release", "edit", "pkg-26.7",
                        "--repo", "resolver-plugins/repository",
                        "--title", "26.7-latest", "--latest=false",
                    ],
                ],
                mutations,
            )

    def test_absent_snapshot_cannot_let_a_stale_run_replace_current(self) -> None:
        """A pruned or recovered snapshot must not let an old run downgrade current."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            staged_snapshot = root / "staged-snapshot"
            staged_current = root / "staged-current"
            remote_current = root / "remote-current"
            for directory in (staged_snapshot, staged_current, remote_current):
                directory.mkdir()
            (staged_snapshot / "asset.pkg").write_bytes(b"snapshot-a")
            (staged_current / "asset.pkg").write_bytes(b"current-a")
            (remote_current / "asset.pkg").write_bytes(b"current-b")
            current_manifest = root / "current.json"
            current_manifest.write_text(
                json.dumps(
                    {"asset.pkg": release_channel.sha256(remote_current / "asset.pkg")}
                )
            )
            absent_snapshot = release_channel.ReleaseSnapshot(
                "pkg-26.7-os-bind-rp-1.36_2",
                False,
                root / "missing",
                root / "missing.json",
            )
            current = release_channel.ReleaseSnapshot(
                "pkg-26.7", True, remote_current, current_manifest
            )

            def fake_snapshot(repository: str, tag: str, recovery: Path):
                return absent_snapshot if "-os-bind-rp-" in tag else current

            with (
                patch.object(release_channel, "snapshot_release", side_effect=fake_snapshot),
                patch.object(release_channel, "validate_channel_directory"),
                patch.object(
                    release_channel,
                    "staged_source_descends_from_current",
                    return_value=False,
                ),
                patch.object(release_channel, "publish") as publish,
            ):
                with self.assertRaisesRegex(RuntimeError, "stale package promotion"):
                    release_channel.publish_channels(
                        "resolver-plugins/repository",
                        [
                            (absent_snapshot.tag, staged_snapshot),
                            (current.tag, staged_current),
                        ],
                        root / "recovery",
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

    def test_repository_latest_is_the_current_channel_for_the_highest_series(self) -> None:
        """GitHub's one Latest badge must never identify an archive channel."""
        releases = [
            {"tag_name": "pkg-26.1", "draft": False, "prerelease": False},
            {"tag_name": "pkg-26.1-os-bind-rp-1.36_9", "draft": False, "prerelease": False},
            {"tag_name": "pkg-26.7", "draft": False, "prerelease": False},
            {"tag_name": "pkg-26.7-os-bind-rp-1.36_2", "draft": False, "prerelease": False},
            {"tag_name": "pkg-26.10", "draft": False, "prerelease": False},
            {"tag_name": "pkg-27.1", "draft": False, "prerelease": True},
        ]
        result = subprocess.CompletedProcess(
            ["gh"], 0, stdout=json.dumps([releases[:3], releases[3:]])
        )
        mutations: list[list[str]] = []
        with (
            patch.object(release_channel.subprocess, "run", return_value=result) as list_releases,
            patch.object(release_channel, "run_gh", side_effect=mutations.append),
        ):
            release_channel.mark_latest_package_channel("resolver-plugins/repository")

        list_releases.assert_called_once_with(
            [
                "gh", "api", "--paginate", "--slurp",
                "repos/resolver-plugins/repository/releases?per_page=100",
            ],
            check=True,
            capture_output=True,
            text=True,
        )
        self.assertEqual(
            [["release", "edit", "pkg-26.10", "--repo", "resolver-plugins/repository", "--latest"]],
            mutations,
        )

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
