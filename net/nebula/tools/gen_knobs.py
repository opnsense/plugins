#!/usr/bin/env python3
"""
gen_knobs.py — deterministic generator for the Nebula OPNsense plugin.

Reads net/nebula/knobs.yaml and produces three artifacts:

  1. src/opnsense/mvc/app/models/OPNsense/Nebula/Nebula.xml
       — replaces content between GENERATED:instance-fields markers
  2. src/opnsense/mvc/app/controllers/OPNsense/Nebula/forms/dialogInstance.xml
       — same marker pair
  3. src/opnsense/mvc/app/models/OPNsense/Nebula/ConfigMap.php
       — overwritten entirely

Usage (from any directory):
    python3 net/nebula/tools/gen_knobs.py            # write in place
    python3 net/nebula/tools/gen_knobs.py --check    # drift check (exit 1 if differs)
    python3 net/nebula/tools/gen_knobs.py --check --root /tmp/fixture  # use alternate root

Dev tool only — NOT installed into the plugin package.
"""

from __future__ import annotations

import argparse
import difflib
import os
import sys
from typing import Any

# ---------------------------------------------------------------------------
# yaml import — stdlib only, no third-party deps
# ---------------------------------------------------------------------------
try:
    import yaml  # PyYAML
except ImportError:
    # Minimal YAML subset parser (only what knobs.yaml uses):
    # sequences of mappings with scalar values.  Not a full YAML parser — if
    # the file ever uses anchors, multi-line strings, etc., install PyYAML.
    import re as _re

    class _MinimalLoader:
        """Absolute-minimum YAML loader for flat sequence-of-mapping files."""

        def load(self, text: str) -> dict:
            lines = text.splitlines()
            result: dict[str, Any] = {}
            current_key: str | None = None
            current_obj: dict[str, Any] | None = None
            list_mode: str | None = None  # key whose value is a list

            for raw in lines:
                line = raw.rstrip()
                if not line or line.lstrip().startswith('#'):
                    continue
                indent = len(line) - len(line.lstrip())

                # Top-level mapping key (no indent)
                if indent == 0:
                    m = _re.match(r'^(\w+)\s*:\s*(.*)$', line)
                    if m:
                        current_key = m.group(1)
                        val = m.group(2).strip()
                        if val == '':
                            result[current_key] = []
                        else:
                            result[current_key] = val
                    list_mode = None
                    current_obj = None
                    continue

                # Sequence item (2-space indent "  - field: …")
                if indent == 2:
                    if line.lstrip().startswith('- '):
                        current_obj = {}
                        if current_key is not None:
                            result[current_key].append(current_obj)
                        rest = line.lstrip()[2:]
                        m = _re.match(r'^(\w+)\s*:\s*(.*)$', rest)
                        if m:
                            k, v = m.group(1), m.group(2).strip()
                            current_obj[k] = self._parse_scalar(v)
                        list_mode = None
                    elif line.lstrip().startswith('#'):
                        pass
                    continue

                # Mapping entry inside a sequence item (4+ spaces)
                if indent >= 4 and current_obj is not None:
                    stripped = line.lstrip()
                    if stripped.startswith('- '):
                        # inline list item  (e.g. enum: [a, b] already handled)
                        if list_mode and isinstance(current_obj.get(list_mode), list):
                            current_obj[list_mode].append(stripped[2:].strip())
                        continue
                    m = _re.match(r'^(\w+)\s*:\s*(.*)$', stripped)
                    if m:
                        k, v = m.group(1), m.group(2).strip()
                        list_mode = None
                        if v == '':
                            current_obj[k] = []
                            list_mode = k
                        else:
                            current_obj[k] = self._parse_scalar(v)
                    continue

            return result

        def _parse_scalar(self, v: str) -> Any:
            """Parse a YAML scalar value."""
            if not v:
                return None
            # Inline list: [a, b, c]
            if v.startswith('[') and v.endswith(']'):
                inner = v[1:-1]
                return [item.strip() for item in inner.split(',')]
            # Quoted string
            if (v.startswith('"') and v.endswith('"')) or \
               (v.startswith("'") and v.endswith("'")):
                return v[1:-1]
            # Integer
            if _re.match(r'^-?\d+$', v):
                return int(v)
            # Boolean (YAML 1.1 style — not used in knobs.yaml booleans which
            # are quoted strings like "true"/"false")
            if v in ('true', 'True', 'TRUE'):
                return True
            if v in ('false', 'False', 'FALSE'):
                return False
            return v

    class _YamlModule:
        def safe_load(self, text: str) -> Any:
            return _MinimalLoader().load(text)

    yaml = _YamlModule()  # type: ignore[assignment]


# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

MARKER_START = '<!-- GENERATED:instance-fields START -->'
MARKER_END = '<!-- GENERATED:instance-fields END -->'

# Indentation for field children inside <instance type="ArrayField">
# (16 spaces, matching the existing Nebula.xml indentation level)
FIELD_INDENT = ' ' * 16
CHILD_INDENT = ' ' * 20

# BSD-2-Clause header (exact copy from Nebula.php)
_PHP_HEADER = """\
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
"""


# ---------------------------------------------------------------------------
# Public helpers
# ---------------------------------------------------------------------------

def load_knobs(root: str | None = None) -> list[dict]:
    """Load and return the knobs list from knobs.yaml.

    *root* is the plugin root directory (the ``net/nebula`` subtree); defaults
    to the directory two levels above this script (i.e. ``net/nebula/``).
    """
    if root is None:
        root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    path = os.path.join(root, 'knobs.yaml')
    with open(path, encoding='utf-8') as fh:
        data = yaml.safe_load(fh)
    return data['knobs']


def replace_between_markers(text: str, start: str, end: str, payload: str) -> str:
    """Return *text* with the content between *start* and *end* replaced by
    *payload*.

    The markers themselves are preserved.  *payload* should **not** start or
    end with the marker strings.

    Raises ``ValueError`` if either marker is absent.
    """
    if start not in text:
        raise ValueError(
            f"START marker not found in target text: {start!r}"
        )
    if end not in text:
        raise ValueError(
            f"END marker not found in target text: {end!r}"
        )
    before, _rest = text.split(start, 1)
    _middle, after = _rest.split(end, 1)
    return f"{before}{start}\n{payload}{end}{after}"


# ---------------------------------------------------------------------------
# XML special-character escaping (for form help text)
# ---------------------------------------------------------------------------

_XML_ESCAPES = [
    ('&', '&amp;'),
    ('<', '&lt;'),
    ('>', '&gt;'),
    ('"', '&quot;'),
    ("'", '&apos;'),
]


def _xml_escape(s: str) -> str:
    """Escape XML special characters in *s*."""
    # Must replace & first to avoid double-escaping.
    for char, repl in _XML_ESCAPES:
        s = s.replace(char, repl)
    return s


# ---------------------------------------------------------------------------
# Render helpers
# ---------------------------------------------------------------------------

def _bool_default(knob: dict) -> str:
    """Return '1' for true-ish default, '0' otherwise."""
    d = str(knob.get('default', 'false')).lower()
    return '1' if d in ('true', '1', 'yes') else '0'


def _field_lines(knob: dict) -> list[str]:
    """Return the XML lines (without trailing newline) for a single knob."""
    field = knob['field']
    typ = knob['type']
    required = knob.get('required', '')
    default = knob.get('default')

    lines: list[str] = []

    def _open(xml_type: str) -> str:
        return f'{FIELD_INDENT}<{field} type="{xml_type}">'

    def _close() -> str:
        return f'{FIELD_INDENT}</{field}>'

    def _child(tag: str, value: Any) -> str:
        return f'{CHILD_INDENT}<{tag}>{value}</{tag}>'

    if typ == 'bool':
        lines.append(_open('BooleanField'))
        lines.append(_child('Default', _bool_default(knob)))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    elif typ == 'int':
        lines.append(_open('IntegerField'))
        if 'min' in knob:
            lines.append(_child('MinimumValue', knob['min']))
        if 'max' in knob:
            lines.append(_child('MaximumValue', knob['max']))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    elif typ == 'enum':
        lines.append(_open('OptionField'))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(f'{CHILD_INDENT}<OptionValues>')
        for v in knob.get('enum', []):
            lines.append(f'{CHILD_INDENT}    <{v}>{v}</{v}>')
        lines.append(f'{CHILD_INDENT}</OptionValues>')
        lines.append(_close())

    elif typ == 'duration':
        lines.append(_open('TextField'))
        lines.append(_child('Mask', r'/^\d+(ns|us|µs|ms|s|m|h)$/'))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    elif typ == 'text':
        lines.append(_open('TextField'))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    elif typ == 'host':
        lines.append(_open('HostnameField'))
        lines.append(_child('IpAllowed', 'Y'))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    elif typ == 'cidr':
        lines.append(_open('NetworkField'))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    elif typ == 'list':
        # CSVListField (AsList) so the form's tokenize select_multiple round-trips
        # each value as a tag; stored comma-joined. Validated by `nebula -test`.
        lines.append(_open('CSVListField'))
        if default is not None:
            lines.append(_child('Default', default))
        if required:
            lines.append(_child('Required', required))
        lines.append(_close())

    else:
        raise ValueError(f"Unknown knob type: {typ!r} for field {field!r}")

    return lines


# ---------------------------------------------------------------------------
# Three render functions
# ---------------------------------------------------------------------------

def render_model_fields(knobs: list[dict]) -> str:
    """Render the inner XML for the per-instance ArrayField.

    Returns a string ready to be inserted between the GENERATED markers in
    ``Nebula.xml``.  Each field element is indented at 16 spaces (matching the
    existing ``<instance>`` children).  The returned string ends with a
    newline.
    """
    parts: list[str] = []
    for knob in knobs:
        parts.extend(_field_lines(knob))
        parts.append('')  # blank line between fields for readability
    # Remove trailing blank line, then add final newline
    while parts and parts[-1] == '':
        parts.pop()
    return '\n'.join(parts) + '\n'


def render_form_fields(knobs: list[dict]) -> str:
    """Render the inner XML for ``dialogInstance.xml``'s ``<form>`` (one region)."""
    parts: list[str] = []
    fi = '    '   # 4-space indent for <field> children

    # Section headers: emit a <type>header</type> separator before the first
    # knob of each section. Sections must be contiguous in knobs.yaml (a section
    # may not reappear after a different one) so the dialog reads top-to-bottom
    # in the documented order; we guard that here.
    current_section: str | None = None
    seen_sections: set[str] = set()

    # A section whose every knob is `advanced` would otherwise show a bare header
    # row when Advanced mode is off. Mark those headers advanced too, so the whole
    # section (header + fields) hides together. Mixed sections keep their header.
    section_all_advanced: dict[str, bool] = {}
    for knob in knobs:
        sec = knob.get('section')
        if sec is None:
            continue
        is_adv = bool(knob.get('advanced'))
        section_all_advanced[sec] = is_adv if sec not in section_all_advanced \
            else (section_all_advanced[sec] and is_adv)

    for knob in knobs:
        field = knob['field']
        typ = knob['type']
        help_text = knob.get('help', '')

        section = knob.get('section')
        if section is not None and section != current_section:
            if section in seen_sections:
                raise ValueError(
                    f"section {section!r} is not contiguous in knobs.yaml: it "
                    f"reappears after section {current_section!r}. Group all "
                    f"knobs of a section together."
                )
            seen_sections.add(section)
            current_section = section
            parts.append(f'    <field>')
            parts.append(f'        <type>header</type>')
            parts.append(f'        <label>{_xml_escape(section)}</label>')
            if section_all_advanced.get(section):
                parts.append(f'        <advanced>true</advanced>')
            parts.append(f'    </field>')

        # Determine form type
        if typ == 'bool':
            form_type = 'checkbox'
        elif typ == 'enum':
            form_type = 'dropdown'
        elif typ == 'list':
            # tokenize select_multiple: each value entered as a chip/tag.
            form_type = 'select_multiple'
        else:
            form_type = 'text'

        # Label: use explicit 'label' key if present, else humanize snake_case.
        if 'label' in knob:
            label = knob['label']
        else:
            label = ' '.join(word.capitalize() for word in field.split('_'))

        lines = [
            f'    <field>',
            f'        <id>instance.{field}</id>',
            f'        <label>{label}</label>',
            f'        <type>{form_type}</type>',
        ]
        # List fields render as a tokenize select_multiple (chip/tag input,
        # allowing arbitrary new values).
        if typ == 'list':
            lines.append(f'        <style>tokenize</style>')
            lines.append(f'        <allownew>true</allownew>')
        # Advanced mode toggle — only emit when explicitly true.
        if knob.get('advanced'):
            lines.append(f'        <advanced>true</advanced>')
        if help_text:
            lines.append(f'        <help>{_xml_escape(help_text)}</help>')
        # Grid column: every field gets a <grid_view>; grid knobs get <sequence>
        # (plus optional width/type/formatter), all others get <ignore>true</ignore>.
        # Order inside <grid_view>: width, type, formatter, sequence  (matches OpenVPN).
        if 'grid' in knob:
            lines.append(f'        <grid_view>')
            if 'grid_width' in knob:
                lines.append(f'            <width>{knob["grid_width"]}</width>')
            if typ == 'bool':
                lines.append(f'            <type>boolean</type>')
                lines.append(f'            <formatter>boolean</formatter>')
            lines.append(f'            <sequence>{knob["grid"]}</sequence>')
            lines.append(f'        </grid_view>')
        else:
            lines.append(f'        <grid_view>')
            lines.append(f'            <ignore>true</ignore>')
            lines.append(f'        </grid_view>')
        lines.append(f'    </field>')

        parts.extend(lines)
        parts.append('')  # blank line between fields

    # Remove trailing blank line
    while parts and parts[-1] == '':
        parts.pop()

    return '\n'.join(parts) + '\n'


def render_config_map(knobs: list[dict]) -> str:
    """Render the full ``ConfigMap.php`` file."""
    lines: list[str] = []
    lines.append(_PHP_HEADER.rstrip('\n'))
    lines.append("/* GENERATED from knobs.yaml by tools/gen_knobs.py — do not edit by hand. */")
    lines.append("return [")
    for knob in knobs:
        field = knob['field']
        yaml_path = knob['yaml']
        typ = knob['type']
        lines.append(f"    '{field}' => ['yaml' => '{yaml_path}', 'type' => '{typ}'],")
    lines.append("];")
    lines.append("")  # trailing newline
    return '\n'.join(lines)


# ---------------------------------------------------------------------------
# Path resolution
# ---------------------------------------------------------------------------

def _resolve_paths(root: str) -> dict[str, str]:
    """Return a dict of logical name → absolute file path, given plugin root."""
    mvc = os.path.join(root, 'src', 'opnsense', 'mvc', 'app')
    return {
        'nebula_xml': os.path.join(mvc, 'models', 'OPNsense', 'Nebula', 'Nebula.xml'),
        'dialog_xml': os.path.join(mvc, 'controllers', 'OPNsense', 'Nebula', 'forms', 'dialogInstance.xml'),
        'config_map': os.path.join(mvc, 'models', 'OPNsense', 'Nebula', 'ConfigMap.php'),
    }


def _plugin_root() -> str:
    """Return the net/nebula plugin root (two dirs above this script)."""
    return os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def _write_in_place(root: str) -> None:
    """Write the three artifacts in place."""
    knobs = load_knobs(root)
    paths = _resolve_paths(root)

    model_payload = render_model_fields(knobs)
    form_payload = render_form_fields(knobs)
    config_map = render_config_map(knobs)

    errors: list[str] = []

    # Nebula.xml — replace between markers
    with open(paths['nebula_xml'], encoding='utf-8') as fh:
        nebula_text = fh.read()
    try:
        new_nebula = replace_between_markers(
            nebula_text, MARKER_START, MARKER_END, model_payload
        )
    except ValueError as exc:
        errors.append(f"{paths['nebula_xml']}: {exc}")
        new_nebula = None

    # dialogInstance.xml — single generated region.
    with open(paths['dialog_xml'], encoding='utf-8') as fh:
        dialog_text = fh.read()
    try:
        new_dialog = replace_between_markers(
            dialog_text, MARKER_START, MARKER_END, form_payload
        )
    except ValueError as exc:
        errors.append(f"{paths['dialog_xml']}: {exc}")
        new_dialog = None

    if errors:
        for msg in errors:
            print(f"ERROR: {msg}", file=sys.stderr)
        sys.exit(1)

    with open(paths['nebula_xml'], 'w', encoding='utf-8') as fh:
        fh.write(new_nebula)
    print(f"Wrote: {paths['nebula_xml']}")

    with open(paths['dialog_xml'], 'w', encoding='utf-8') as fh:
        fh.write(new_dialog)
    print(f"Wrote: {paths['dialog_xml']}")

    with open(paths['config_map'], 'w', encoding='utf-8') as fh:
        fh.write(config_map)
    print(f"Wrote: {paths['config_map']}")


def _check(root: str) -> bool:
    """Check whether on-disk artifacts are up to date.

    Returns True if all artifacts are current (no drift), False otherwise.
    Prints a diff summary when drift is detected.
    """
    knobs = load_knobs(root)
    paths = _resolve_paths(root)

    model_payload = render_model_fields(knobs)
    form_payload = render_form_fields(knobs)
    config_map = render_config_map(knobs)

    ok = True

    def _check_xml(path: str, payload: str, label: str) -> bool:
        if not os.path.exists(path):
            print(f"MISSING: {path}", file=sys.stderr)
            return False
        with open(path, encoding='utf-8') as fh:
            current = fh.read()
        # Regenerate as if we were writing in place
        try:
            expected = replace_between_markers(
                current, MARKER_START, MARKER_END, payload
            )
        except ValueError as exc:
            print(f"ERROR ({label}): {exc}", file=sys.stderr)
            return False
        if current == expected:
            return True
        # Show diff
        diff = list(difflib.unified_diff(
            current.splitlines(keepends=True),
            expected.splitlines(keepends=True),
            fromfile=f'{label} (on-disk)',
            tofile=f'{label} (generated)',
            n=3,
        ))
        print(f"DRIFT in {path}:")
        sys.stdout.writelines(diff[:40])
        if len(diff) > 40:
            print(f"  ... ({len(diff) - 40} more diff lines)")
        return False

    def _check_file(path: str, expected: str, label: str) -> bool:
        if not os.path.exists(path):
            print(f"MISSING: {path}", file=sys.stderr)
            return False
        with open(path, encoding='utf-8') as fh:
            current = fh.read()
        if current == expected:
            return True
        diff = list(difflib.unified_diff(
            current.splitlines(keepends=True),
            expected.splitlines(keepends=True),
            fromfile=f'{label} (on-disk)',
            tofile=f'{label} (generated)',
            n=3,
        ))
        print(f"DRIFT in {path}:")
        sys.stdout.writelines(diff[:40])
        if len(diff) > 40:
            print(f"  ... ({len(diff) - 40} more diff lines)")
        return False

    ok = _check_xml(paths['nebula_xml'], model_payload, 'Nebula.xml') and ok
    ok = _check_xml(paths['dialog_xml'], form_payload, 'dialogInstance.xml') and ok
    ok = _check_file(paths['config_map'], config_map, 'ConfigMap.php') and ok

    return ok


def main(argv: list[str] | None = None) -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        '--check',
        action='store_true',
        help='Check drift only; exit 1 if artifacts are out of date.',
    )
    parser.add_argument(
        '--root',
        default=None,
        help='Override the plugin root directory (default: two levels above this script).',
    )
    args = parser.parse_args(argv)

    root = args.root if args.root else _plugin_root()

    if args.check:
        ok = _check(root)
        sys.exit(0 if ok else 1)
    else:
        _write_in_place(root)


if __name__ == '__main__':
    main()
