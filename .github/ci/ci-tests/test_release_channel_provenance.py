#!/usr/bin/env python3
"""Local regression coverage for BIND provenance release staging."""

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

PROFILE = {
    "ports_repository": "https://github.com/freebsd/freebsd-ports.git",
    "ports_commit": "343e9b366f371df755622e1680b59d998e5778fd",
    "makefile_sha256": "64199c6f419c49186ee35f37d42219aff33591f0de040429f3ceddb6889d2234",
    "distinfo_sha256": "714ea8f967746994a624a55dd6e2bbdd41d173dcffe8f25242f7aa4053d116b6",
    "distversion": "9.20.26",
    "portrevision": 1,
}


class StageProvenanceTest(unittest.TestCase):
    def test_channel_rejects_provenance_that_does_not_match_the_trusted_profile(self) -> None:
        provenance = {
            "schema": 1,
            "fingerprint": "0" * 64,
            "series": "26.1",
            "freebsd_release": "14.3",
            "architecture": "x86_64",
            "packages": {
                "bind-tools": {"name": "bind-tools", "version": "9.20.26_1", "origin": "dns/bind-tools", "filename": "bind-tools-9.20.26_1.pkg"},
                "bind920": {"name": "bind920", "version": "9.20.26_1", "origin": "dns/bind920", "filename": "bind920-9.20.26_1.pkg"},
            },
        }
        with self.assertRaisesRegex(ValueError, "fingerprint"):
            release_channel.validate_bind_provenance(provenance, PROFILE, "26.1", "14.3")

    def test_channel_selection_uses_the_bind_pair_named_by_provenance(self) -> None:
        """A later pinned BIND revision is selected from provenance, not a hard-coded filename."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            packages = Path(temporary_directory)
            provenance = {
                "packages": {
                    "bind-tools": {
                        "name": "bind-tools",
                        "version": "9.20.27_1",
                        "origin": "dns/bind-tools",
                        "filename": "bind-tools-9.20.27_1.pkg",
                    },
                    "bind920": {
                        "name": "bind920",
                        "version": "9.20.27_1",
                        "origin": "dns/bind920",
                        "filename": "bind920-9.20.27_1.pkg",
                    },
                }
            }
            (packages / "bind920-provenance.json").write_text(
                json.dumps(provenance), encoding="utf-8"
            )
            for name in (
                "bind-tools-9.20.27_1.pkg",
                "bind920-9.20.27_1.pkg",
                "os-bind-rp-1.36_8.pkg",
            ):
                (packages / name).touch()

            self.assertEqual(
                [
                    "bind-tools-9.20.27_1.pkg",
                    "bind920-9.20.27_1.pkg",
                    "os-bind-rp-1.36_8.pkg",
                ],
                [path.name for path in release_channel.select_channel_packages(packages)],
            )

    def test_stage_requires_and_copies_bind_provenance(self) -> None:
        """A stable channel must retain the metadata needed for BIND reuse."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            packages = root / "packages"
            packages.mkdir()
            for name in (
                "bind-tools-9.20.26_1.pkg",
                "bind920-9.20.26_1.pkg",
                "os-bind-rp-1.36_8.pkg",
            ):
                (packages / name).touch()
            key = root / "private.pem"
            key.touch()

            def fake_repo(command: list[str], **_: object) -> subprocess.CompletedProcess[str]:
                output = Path(command[2])
                (output / "meta").touch()
                return subprocess.CompletedProcess(command, 0)

            with (
                patch.object(release_channel, "validate_package_manifests"),
                patch.object(release_channel.subprocess, "run", side_effect=fake_repo),
            ):
                with self.assertRaisesRegex(ValueError, "BIND provenance"):
                    release_channel.stage_repository(packages, root / "missing", key, "pkg")
                (packages / "bind920-provenance.json").write_text("{}\n", encoding="utf-8")
                assets = release_channel.stage_repository(packages, root / "staged", key, "pkg")

            self.assertIn(root / "staged" / "bind920-provenance.json", assets)


if __name__ == "__main__":
    unittest.main()
