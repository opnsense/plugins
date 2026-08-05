#!/usr/bin/env python3
"""Regression coverage for the BIND source eligibility policy."""

from __future__ import annotations

import importlib.util
import json
import subprocess
import unittest
from pathlib import Path


MODULE_PATH = Path(__file__).resolve().parents[1] / "bind_compatibility.py"
SPEC = importlib.util.spec_from_file_location("bind_compatibility", MODULE_PATH)
assert SPEC is not None and SPEC.loader is not None
bind_compatibility = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(bind_compatibility)


POLICY = {
    "schema": 1,
    "minimum_version": "9.20.26",
    "bind920": {"name": "bind920", "origin": "dns/bind920"},
    "bind_tools": {"name": "bind-tools", "origin": "dns/bind-tools"},
    "series": {"26.1": "14.3", "26.7": "15.1"},
}


class BindCompatibilityTest(unittest.TestCase):
    def test_policy_requires_supported_series_and_complete_identities(self) -> None:
        """The policy rejects relaxed or incomplete compatibility metadata."""
        policy = bind_compatibility.validate_policy(POLICY)
        self.assertEqual("9.20.26", policy["minimum_version"])
        self.assertEqual("14.3", bind_compatibility.freebsd_release(policy, "26.1"))

        invalid = dict(POLICY, bind920={"name": "bind920"})
        with self.assertRaisesRegex(ValueError, "bind920"):
            bind_compatibility.validate_policy(invalid)
        with self.assertRaisesRegex(ValueError, "unsupported"):
            bind_compatibility.freebsd_release(policy, "27.1")

    def test_only_eligible_opnsense_bind_is_preferred(self) -> None:
        """Origin, tools, and minimum version are all mandatory for preference."""
        policy = bind_compatibility.validate_policy(POLICY)
        compare = lambda candidate, minimum: {  # noqa: E731
            ("9.20.26", "9.20.26"): "=",
            ("9.20.27", "9.20.26"): ">",
            ("9.20.25", "9.20.26"): "<",
        }[(candidate, minimum)]

        self.assertTrue(
            bind_compatibility.is_eligible(
                policy,
                ("bind920", "9.20.26", "dns/bind920"),
                ("bind-tools", "9.20.26", "dns/bind-tools"),
                compare,
            )
        )
        self.assertFalse(
            bind_compatibility.is_eligible(
                policy,
                ("bind920", "9.20.26", "dns/bind920"),
                ("bind-tools", "9.20.26", "dns/bind-tools-alt"),
                compare,
            )
        )
        self.assertFalse(
            bind_compatibility.is_eligible(
                policy,
                ("bind920", "9.20.25", "dns/bind920"),
                ("bind-tools", "9.20.26", "dns/bind-tools"),
                compare,
            )
        )

    def test_policy_file_is_committed_and_valid(self) -> None:
        """The build wrapper uses a reviewable, static compatibility contract."""
        policy_path = MODULE_PATH.parents[2] / ".resolver-plugins/bind-compatibility.json"
        policy = json.loads(policy_path.read_text(encoding="utf-8"))
        self.assertEqual(POLICY, bind_compatibility.validate_policy(policy))

    def test_profile_command_returns_the_minimum_version(self) -> None:
        """Shell callers obtain the policy minimum through a validated interface."""
        policy_path = MODULE_PATH.parents[2] / ".resolver-plugins/bind-compatibility.json"
        result = subprocess.run(
            ["python3", str(MODULE_PATH), "minimum-version", str(policy_path), "26.7"],
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual("9.20.26\n", result.stdout)

    def test_identity_command_returns_the_required_origin(self) -> None:
        """The shell wrapper does not duplicate the policy's package identity."""
        policy_path = MODULE_PATH.parents[2] / ".resolver-plugins/bind-compatibility.json"
        result = subprocess.run(
            ["python3", str(MODULE_PATH), "identity", str(policy_path), "26.1", "bind920"],
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual("bind920\tdns/bind920\n", result.stdout)


if __name__ == "__main__":
    unittest.main()
