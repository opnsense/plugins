#!/usr/bin/env python3
"""
audit_knobs.py — verify the plugin's config knobs against the upstream Nebula
configuration key set.

This is the durable, CI-enforceable form of "generate the knobs from the docs":
rather than scrape prose, we pin the complete upstream leaf-key set in
tools/nebula_config_reference.yaml (transcribed from nebula examples/config.yml +
the versioned docs) and assert that the generated knobs in knobs.yaml stay in
exact correspondence with it.

The audit fails (exit 1) when any of the following hold:

  * an upstream key in the reference's `all` list is neither a generated knob
    (knobs.yaml) nor classified (handled / deferred / not_applicable /
    deprecated) — i.e. a new Nebula release added a key we have not triaged;
  * a knob's yaml path is absent from the reference's `all` list — i.e. a knob
    points at a key upstream does not (or no longer) defines;
  * a classification entry overlaps a knob (except a `deprecated` entry marked
    `retained: true`, which is expected to keep its warning-marked knob);
  * a `deprecated` entry marked `retained: true` has no corresponding knob;
  * the reference is internally inconsistent (duplicate or unknown keys).

Usage:
    python3 net/nebula/tools/audit_knobs.py            # human-readable report
    python3 net/nebula/tools/audit_knobs.py --check    # exit 1 on any failure
    python3 net/nebula/tools/audit_knobs.py --root /path/to/net/nebula
"""

from __future__ import annotations

import argparse
import os
import sys

import yaml  # PyYAML — required for this dev/CI tool

# Reuse the knob loader so there is a single definition of "what is a knob".
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from gen_knobs import load_knobs  # noqa: E402

_CLASSIFICATIONS = ('handled', 'deferred', 'not_applicable', 'deprecated')


def _default_root() -> str:
    return os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def load_reference(root: str | None = None) -> dict:
    """Load the upstream key registry."""
    if root is None:
        root = _default_root()
    path = os.path.join(root, 'tools', 'nebula_config_reference.yaml')
    with open(path, encoding='utf-8') as fh:
        return yaml.safe_load(fh)


def audit(root: str | None = None) -> list[str]:
    """Return a list of audit failure messages (empty means the audit passed)."""
    if root is None:
        root = _default_root()

    ref = load_reference(root)
    knobs = load_knobs(root)

    failures: list[str] = []

    # ----- reference internal consistency -----------------------------------
    all_list = ref.get('all') or []
    all_keys: set[str] = set()
    for key in all_list:
        if key in all_keys:
            failures.append(f"reference `all` lists {key!r} more than once")
        all_keys.add(key)

    # Build key -> category, flagging retained deprecated entries.
    classified: dict[str, str] = {}
    retained_deprecated: set[str] = set()
    for cat in _CLASSIFICATIONS:
        for entry in ref.get(cat) or []:
            key = entry['key']
            if key in classified:
                failures.append(
                    f"{key!r} is classified twice "
                    f"({classified[key]} and {cat})"
                )
            classified[key] = cat
            if cat == 'deprecated' and entry.get('retained'):
                retained_deprecated.add(key)
            if key not in all_keys:
                failures.append(
                    f"{cat} entry {key!r} is not present in the reference "
                    f"`all` key set"
                )

    knob_paths = {k['yaml'] for k in knobs}

    # ----- knob <-> upstream correspondence ---------------------------------
    for path in sorted(knob_paths):
        if path not in all_keys:
            failures.append(
                f"knob yaml path {path!r} is not an upstream key in the "
                f"reference `all` set (typo, or removed upstream?)"
            )

    # ----- every upstream key is covered ------------------------------------
    for key in sorted(all_keys):
        is_knob = key in knob_paths
        cat = classified.get(key)
        if not is_knob and cat is None:
            failures.append(
                f"upstream key {key!r} is unclassified: add a knob in "
                f"knobs.yaml or classify it in nebula_config_reference.yaml"
            )
        if is_knob and cat is not None and key not in retained_deprecated:
            failures.append(
                f"upstream key {key!r} is both a knob and classified as "
                f"{cat!r}; remove one (or mark deprecated retained:true)"
            )

    # ----- retained deprecated knobs must actually exist --------------------
    for key in sorted(retained_deprecated):
        if key not in knob_paths:
            failures.append(
                f"deprecated key {key!r} is marked retained:true but has no "
                f"knob in knobs.yaml to round-trip stored configs"
            )

    return failures


def summary(root: str | None = None) -> str:
    """Return a human-readable coverage summary."""
    if root is None:
        root = _default_root()
    ref = load_reference(root)
    knobs = load_knobs(root)
    knob_paths = {k['yaml'] for k in knobs}
    all_keys = ref.get('all') or []

    counts = {cat: len(ref.get(cat) or []) for cat in _CLASSIFICATIONS}
    n_knob = sum(1 for k in all_keys if k in knob_paths)

    lines = [
        f"Upstream keys (targeted release): {len(all_keys)}",
        f"  knob (generated form field):   {n_knob}",
        f"  handled (other model/UI):      {counts['handled']}",
        f"  deferred (not yet exposed):    {counts['deferred']}",
        f"  not_applicable (platform/ver): {counts['not_applicable']}",
        f"  deprecated (omitted):          {counts['deprecated']}",
    ]
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true',
                        help='exit 1 on any audit failure (for CI)')
    parser.add_argument('--root', default=None,
                        help='plugin root (the net/nebula subtree)')
    args = parser.parse_args(argv)

    failures = audit(args.root)

    if not args.check:
        print(summary(args.root))
        print()

    if failures:
        print(f"AUDIT FAILED ({len(failures)} issue(s)):", file=sys.stderr)
        for msg in failures:
            print(f"  - {msg}", file=sys.stderr)
        return 1

    print("Knob audit OK — knobs.yaml is in sync with the upstream key set.")
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
