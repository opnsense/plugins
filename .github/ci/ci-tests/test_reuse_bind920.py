#!/usr/bin/env python3
"""Local regression coverage for BIND reuse candidate selection."""

from __future__ import annotations

import importlib.util
import subprocess
import tempfile
import unittest
from contextlib import contextmanager
from collections.abc import Iterator
from pathlib import Path
from unittest.mock import patch


MODULE_PATH = Path(__file__).resolve().parents[1] / "reuse_bind920.py"
SPEC = importlib.util.spec_from_file_location("reuse_bind920", MODULE_PATH)
assert SPEC is not None and SPEC.loader is not None
reuse_bind920 = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(reuse_bind920)
FIXTURE_ROOT = Path(__file__).resolve().parents[1] / "ci-local"


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
    "abi": "FreeBSD:14:amd64",
    "filename": "pkg-2.3.1_1.pkg",
    "sha256": "a" * 64,
    "pkg_static_sha256": "b" * 64,
}
PROVENANCE = {
    "schema": 2,
    "fingerprint": "",
    "series": "26.1",
    "freebsd_release": "14.3",
    "architecture": "x86_64",
    "package_creator": PACKAGE_CREATOR,
    "packages": {
        "bind-tools": {
            "name": "bind-tools",
            "version": "9.20.26_2",
            "origin": "dns/bind-tools",
            "filename": "bind-tools-9.20.26_2.pkg",
        },
        "bind920": {
            "name": "bind920",
            "version": "9.20.26_2",
            "origin": "dns/bind920",
            "filename": "bind920-9.20.26_2.pkg",
        },
    },
}


@contextmanager
def pkg_static_fixture(checksum: str) -> Iterator[Path]:
    FIXTURE_ROOT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(dir=FIXTURE_ROOT) as directory_text:
        executable = Path(directory_text) / "pkg-static"
        executable.write_text(
            "#!/bin/sh\n"
            "[ \"$1\" = query ] && [ \"$2\" = -F ] || exit 64\n"
            f"printf '%s\\n' '/usr/local/sbin/named|{checksum}'\n",
            encoding="utf-8",
        )
        executable.chmod(0o755)
        yield executable


class ReuseBind920Test(unittest.TestCase):
    def test_incompatible_archive_checksums_force_a_cache_miss(self) -> None:
        with tempfile.TemporaryDirectory() as directory_text:
            archive = Path(directory_text) / "bind920-9.20.26_2.pkg"
            archive.touch()
            with pkg_static_fixture("(null)") as pkg_static:
                with self.assertRaisesRegex(reuse_bind920.CacheMiss, "target-readable"):
                    reuse_bind920.verify_archive_compatibility(str(pkg_static), archive)

    def test_matching_provenance_selects_the_declared_pair(self) -> None:
        """Only a fully matching package pair may take the no-build path."""
        provenance = reuse_bind920.bind920_profile.build_provenance(
            PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR, PROVENANCE["packages"]
        )
        packages = reuse_bind920.select_candidate(
            provenance, PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR
        )
        self.assertEqual("bind920-9.20.26_2.pkg", packages["bind920"]["filename"])

    def test_different_fingerprint_is_an_ordinary_cache_miss(self) -> None:
        """A changed BIND profile must rebuild instead of reusing old BIND."""
        provenance = dict(PROVENANCE, fingerprint="0" * 64)
        with self.assertRaises(reuse_bind920.CacheMiss):
            reuse_bind920.select_candidate(
                provenance, PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR
            )

    def test_old_or_different_creator_provenance_is_a_cache_miss(self) -> None:
        old = dict(PROVENANCE)
        old.pop("package_creator")
        old["schema"] = 1
        with self.assertRaises(reuse_bind920.CacheMiss):
            reuse_bind920.select_candidate(
                old, PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR
            )
        changed = dict(PROVENANCE, package_creator=dict(PACKAGE_CREATOR, sha256="c" * 64))
        with self.assertRaises(reuse_bind920.CacheMiss):
            reuse_bind920.select_candidate(
                changed, PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR
            )

    def test_invalid_package_identity_is_rejected(self) -> None:
        """Malformed matching metadata must not silently become a cache miss."""
        provenance = dict(PROVENANCE, packages=dict(PROVENANCE["packages"]))
        provenance["packages"]["bind920"] = dict(PROVENANCE["packages"]["bind920"], origin="dns/bind918")
        provenance["fingerprint"] = reuse_bind920.bind920_profile.compatibility_fingerprint(
            PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR
        )
        with self.assertRaisesRegex(ValueError, "bind920 package"):
            reuse_bind920.select_candidate(
                provenance, PROFILE, "26.1", "14.3", "x86_64", PACKAGE_CREATOR
            )

    def test_installed_identity_compares_pkg_query_fields_in_python(self) -> None:
        """Version/origin verification must not rely on pkg predicate support."""
        command: list[str] = []

        def fake_run(arguments: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            command.extend(arguments)
            return subprocess.CompletedProcess(arguments, 0, stdout="bind920\t9.20.26_2\tdns/bind920\n")

        with patch.object(reuse_bind920, "run", side_effect=fake_run):
            identity = reuse_bind920.installed_package_identity("pkg", "bind920")

        self.assertEqual(("bind920", "9.20.26_2", "dns/bind920"), identity)
        self.assertEqual(["pkg", "query", "-e", "%n = bind920", "%n\t%v\t%o"], command)

    def test_fetch_package_is_noninteractive(self) -> None:
        """The VM must never stop at pkg's confirmation prompt."""
        command: list[str] = []

        def fake_run(arguments: list[str], **_: object) -> subprocess.CompletedProcess[str]:
            command.extend(arguments)
            return subprocess.CompletedProcess(arguments, 0)

        with patch.object(reuse_bind920, "run", side_effect=fake_run):
            reuse_bind920.fetch_package(
                ["pkg", "-o", "REPOS_DIR=/tmp/repos"],
                Path("/tmp/downloads"),
                "bind-tools-9.20.26_2.pkg",
            )

        self.assertEqual(
            [
                "pkg", "-o", "REPOS_DIR=/tmp/repos", "fetch", "-y", "-r", "resolver-plugins",
                "-o", "/tmp/downloads", "bind-tools-9.20.26_2",
            ],
            command,
        )

    def test_downloaded_archive_accepts_pkg_named_fetch_layouts(self) -> None:
        """pkg versions may place named downloads either directly or in All/."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            downloads = Path(temporary_directory)
            direct = downloads / "bind-tools-9.20.26_2.pkg"
            direct.touch()
            self.assertEqual(direct, reuse_bind920.downloaded_archive(downloads, direct.name))


if __name__ == "__main__":
    unittest.main()
