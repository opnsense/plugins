#!/usr/bin/env python3
"""Local regression coverage for BIND provenance release staging."""

from __future__ import annotations

import importlib.util
import json
import tempfile
import unittest
from pathlib import Path


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
    "portrevision": 2,
}
PACKAGE_CREATOR = {
    "name": "pkg",
    "version": "2.3.1_1",
    "origin": "ports-mgmt/pkg",
    "abi": "FreeBSD:15:amd64",
    "filename": "pkg-2.3.1_1.pkg",
    "sha256": "a" * 64,
    "pkg_static_sha256": "b" * 64,
}


class StageProvenanceTest(unittest.TestCase):
    def test_channel_rejects_provenance_that_does_not_match_the_trusted_profile(self) -> None:
        provenance = {
            "schema": 2,
            "fingerprint": "0" * 64,
            "series": "26.1",
            "freebsd_release": "14.3",
            "architecture": "x86_64",
            "package_creator": dict(PACKAGE_CREATOR, abi="FreeBSD:14:amd64"),
            "packages": {
                "bind-tools": {"name": "bind-tools", "version": "9.20.26_2", "origin": "dns/bind-tools", "filename": "bind-tools-9.20.26_2.pkg"},
                "bind920": {"name": "bind920", "version": "9.20.26_2", "origin": "dns/bind920", "filename": "bind920-9.20.26_2.pkg"},
            },
        }
        with self.assertRaisesRegex(ValueError, "fingerprint"):
            release_channel.validate_bind_provenance(
                provenance,
                PROFILE,
                "26.1",
                "14.3",
                dict(PACKAGE_CREATOR, abi="FreeBSD:14:amd64"),
            )

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

    def test_build_metadata_must_match_trusted_release_and_bind_provenance(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            root = Path(temporary_directory)
            metadata = root / "build-metadata.txt"
            metadata.write_text(
                "series=26.7\n"
                "uname=FreeBSD test 15.1\n"
                "pkg_abi=FreeBSD:15:amd64\n"
                "bind920=9.20.26_1\n"
                "bind_source=resolver\n"
                "opnsense=26.7\n"
                "opnsense_core_commit=core-commit\n"
                "upstream_commit=upstream-commit\n"
                "core_commit=core-commit\n"
                "tools_tag=26.7.1\n"
                "freebsd_release=15.1\n"
                "source_commit=source-commit\n"
                "pkg_creator=2.3.1_1\n"
                "pkg_creator_sha256=" + "a" * 64 + "\n",
                encoding="utf-8",
            )
            upstream = root / "upstream.json"
            upstream.write_text(
                json.dumps(
                    {
                        "series": "26.7",
                        "upstream_commit": "upstream-commit",
                        "core_commit": "core-commit",
                        "tools_tag": "26.7.1",
                        "freebsd_release": "15.1",
                    }
                ),
                encoding="utf-8",
            )
            provenance = root / "bind920-provenance.json"
            provenance.write_text(
                json.dumps(
                    {
                        "series": "26.7",
                        "freebsd_release": "15.1",
                        "package_creator": PACKAGE_CREATOR,
                        "packages": {"bind920": {"version": "9.20.26_1"}},
                    }
                ),
                encoding="utf-8",
            )
            target_metadata = root / "target-pkg.json"
            target_metadata.write_text(
                json.dumps(
                    {
                        "schema": 1,
                        "series": {
                            "26.1": dict(PACKAGE_CREATOR, abi="FreeBSD:14:amd64"),
                            "26.7": PACKAGE_CREATOR,
                        },
                    }
                ),
                encoding="utf-8",
            )

            release_channel.validate_build_metadata(
                metadata,
                upstream,
                provenance,
                target_metadata,
                "26.7",
                "source-commit",
            )
            metadata.write_text(
                metadata.read_text(encoding="utf-8").replace(
                    "core_commit=core-commit", "core_commit=untrusted", 1
                ),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(ValueError, "trusted release metadata"):
                release_channel.validate_build_metadata(
                    metadata,
                    upstream,
                    provenance,
                    target_metadata,
                    "26.7",
                    "source-commit",
                )

if __name__ == "__main__":
    unittest.main()
