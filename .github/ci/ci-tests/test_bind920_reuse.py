#!/usr/bin/env python3
"""Local regression coverage for reusable BIND build provenance."""

from __future__ import annotations

import importlib.util
import json
import subprocess
import tempfile
import unittest
from pathlib import Path


MODULE_PATH = Path(__file__).resolve().parents[1] / "bind920_profile.py"
SPEC = importlib.util.spec_from_file_location("bind920_profile", MODULE_PATH)
assert SPEC is not None and SPEC.loader is not None
bind920_profile = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(bind920_profile)


PROFILE = {
    "ports_repository": "https://github.com/freebsd/freebsd-ports.git",
    "ports_commit": "343e9b366f371df755622e1680b59d998e5778fd",
    "makefile_sha256": "64199c6f419c49186ee35f37d42219aff33591f0de040429f3ceddb6889d2234",
    "distinfo_sha256": "714ea8f967746994a624a55dd6e2bbdd41d173dcffe8f25242f7aa4053d116b6",
    "distversion": "9.20.26",
    "portrevision": 2,
}
PACKAGES = {
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
}


class Bind920ReuseTest(unittest.TestCase):
    def test_package_version_uses_any_positive_portrevision(self) -> None:
        self.assertEqual("9.20.26_2", bind920_profile.package_version(PROFILE))
        with self.assertRaisesRegex(ValueError, "portrevision"):
            bind920_profile.validate_profile(dict(PROFILE, portrevision=0))

    def test_fingerprint_rejects_different_compatibility_inputs(self) -> None:
        """Changing any compatibility input must prevent package reuse."""
        baseline = bind920_profile.compatibility_fingerprint(PROFILE, "26.1", "14.3", "x86_64")
        changed_profile = dict(PROFILE, makefile_sha256="0" * 64)
        self.assertNotEqual(baseline, bind920_profile.compatibility_fingerprint(PROFILE, "26.7", "14.3", "x86_64"))
        self.assertNotEqual(baseline, bind920_profile.compatibility_fingerprint(PROFILE, "26.1", "14.4", "x86_64"))
        self.assertNotEqual(baseline, bind920_profile.compatibility_fingerprint(PROFILE, "26.1", "14.3", "aarch64"))
        self.assertNotEqual(baseline, bind920_profile.compatibility_fingerprint(changed_profile, "26.1", "14.3", "x86_64"))

    def test_provenance_requires_exact_bind_package_identities(self) -> None:
        """A cache candidate must identify both BIND package archives exactly."""
        provenance = bind920_profile.build_provenance(PROFILE, "26.1", "14.3", "x86_64", PACKAGES)
        self.assertEqual("dns/bind920", provenance["packages"]["bind920"]["origin"])
        invalid = dict(PACKAGES)
        invalid["bind920"] = dict(PACKAGES["bind920"], origin="dns/bind918")
        with self.assertRaisesRegex(ValueError, "bind920 package"):
            bind920_profile.build_provenance(PROFILE, "26.1", "14.3", "x86_64", invalid)

    def test_provenance_command_writes_declared_package_filenames(self) -> None:
        """The shell build wrapper must be able to write reusable provenance."""
        with tempfile.TemporaryDirectory() as temporary_directory:
            directory = Path(temporary_directory)
            profile = directory / "bind920.json"
            bind_tools = directory / "bind-tools-9.20.26_2.pkg"
            bind920 = directory / "bind920-9.20.26_2.pkg"
            output = directory / "bind920-provenance.json"
            profile.write_text(json.dumps(PROFILE), encoding="utf-8")
            bind_tools.touch()
            bind920.touch()
            result = subprocess.run(
                [
                    "python3", str(MODULE_PATH), str(profile), "provenance", "26.1", "14.3",
                    "--bind-tools", str(bind_tools), "--bind920", str(bind920), "--output", str(output),
                ],
                capture_output=True,
                text=True,
            )
            self.assertEqual(result.stderr, "")
            self.assertEqual(result.returncode, 0)
            self.assertEqual("bind920-9.20.26_2.pkg", json.loads(output.read_text())["packages"]["bind920"]["filename"])


if __name__ == "__main__":
    unittest.main()
