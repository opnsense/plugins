#!/usr/bin/env python3
"""
Unit tests for audit_knobs.py.

Run:  python3 net/nebula/tools/audit_knobs_test.py
Requires PyYAML (same as audit_knobs.py itself).
"""

import os
import sys
import tempfile
import textwrap
import unittest

sys.path.insert(0, os.path.dirname(__file__))

import audit_knobs  # noqa: E402 — must be after path manipulation


def _write_root(knobs_yaml: str, reference_yaml: str) -> str:
    """Create a throwaway plugin root with the two files audit_knobs reads.

    Both arguments are dedented, so callers may indent the literals to match
    the surrounding code.
    """
    root = tempfile.mkdtemp(prefix='nebula-audit-')
    os.makedirs(os.path.join(root, 'tools'), exist_ok=True)
    with open(os.path.join(root, 'knobs.yaml'), 'w', encoding='utf-8') as fh:
        fh.write(textwrap.dedent(knobs_yaml))
    with open(os.path.join(root, 'tools', 'nebula_config_reference.yaml'),
              'w', encoding='utf-8') as fh:
        fh.write(textwrap.dedent(reference_yaml))
    return root


# One knob (cipher); a richer fixture adds local_range below where needed.
ONE_KNOB = """\
    knobs:
      - field: cipher
        yaml: cipher
        type: enum
        default: chachapoly
        enum: [chachapoly, aes]
        help: Encryption cipher.
"""

# cipher = knob; one entry in each classification list; internally consistent.
GOOD_REFERENCE = """\
    all:
      - cipher
      - pki.ca
      - stats
      - tun.windows_bypass_wdf
      - local_range
    handled:
      - key: pki.ca
        by: PKI authorities
    deferred:
      - key: stats
        reason: telemetry block
    not_applicable:
      - key: tun.windows_bypass_wdf
        reason: Windows-only
    deprecated:
      - key: local_range
        reason: alias for preferred_ranges
        retained: false
"""


class TestRealReference(unittest.TestCase):
    def test_shipped_reference_passes(self):
        """The real knobs.yaml + reference must always pass the audit."""
        root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.assertEqual(audit_knobs.audit(root), [])

    def test_summary_mentions_upstream_keys(self):
        root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.assertIn('Upstream keys', audit_knobs.summary(root))


class TestFixturePasses(unittest.TestCase):
    def test_minimal_good_fixture(self):
        root = _write_root(ONE_KNOB, GOOD_REFERENCE)
        self.assertEqual(audit_knobs.audit(root), [])


class TestDriftIsCaught(unittest.TestCase):
    def test_unclassified_upstream_key_fails(self):
        reference = """\
            all:
              - cipher
              - punchy.punch
        """  # punchy.punch is neither a knob nor classified
        root = _write_root(ONE_KNOB, reference)
        failures = audit_knobs.audit(root)
        self.assertTrue(
            any('unclassified' in f and 'punchy.punch' in f for f in failures),
            failures)

    def test_knob_path_not_in_upstream_fails(self):
        knobs = """\
            knobs:
              - field: cipher
                yaml: cipher
                type: enum
                default: chachapoly
                enum: [chachapoly, aes]
                help: Encryption cipher.
              - field: bogus_field
                yaml: bogus.path
                type: bool
                default: "false"
                help: Not a real Nebula key.
        """
        reference = """\
            all:
              - cipher
        """
        root = _write_root(knobs, reference)
        failures = audit_knobs.audit(root)
        self.assertTrue(any('bogus.path' in f for f in failures), failures)

    def test_knob_classified_overlap_fails(self):
        reference = """\
            all:
              - cipher
            deferred:
              - key: cipher
                reason: wrongly double-listed
        """
        root = _write_root(ONE_KNOB, reference)
        failures = audit_knobs.audit(root)
        self.assertTrue(
            any('both a knob and classified' in f for f in failures), failures)

    def test_retained_deprecated_without_knob_fails(self):
        reference = """\
            all:
              - cipher
              - local_range
            deprecated:
              - key: local_range
                reason: kept for round-trip
                retained: true
        """
        root = _write_root(ONE_KNOB, reference)
        failures = audit_knobs.audit(root)
        self.assertTrue(
            any('retained:true' in f and 'local_range' in f for f in failures),
            failures)

    def test_retained_deprecated_with_knob_passes(self):
        knobs = """\
            knobs:
              - field: cipher
                yaml: cipher
                type: enum
                default: chachapoly
                enum: [chachapoly, aes]
                help: Encryption cipher.
              - field: local_range
                yaml: local_range
                type: text
                help: DEPRECATED — use preferred_ranges.
        """
        reference = """\
            all:
              - cipher
              - local_range
            deprecated:
              - key: local_range
                reason: kept for round-trip
                retained: true
        """
        root = _write_root(knobs, reference)
        self.assertEqual(audit_knobs.audit(root), [])

    def test_classified_key_absent_from_all_fails(self):
        reference = """\
            all:
              - cipher
            deferred:
              - key: stats
                reason: not in all
        """
        root = _write_root(ONE_KNOB, reference)
        failures = audit_knobs.audit(root)
        self.assertTrue(
            any('not present in the reference' in f for f in failures), failures)


if __name__ == '__main__':
    unittest.main()
