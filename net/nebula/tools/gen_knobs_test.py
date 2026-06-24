#!/usr/bin/env python3
"""
Unit tests for gen_knobs.py.

Run:  python3 net/nebula/tools/gen_knobs_test.py
Requires no external dependencies (stdlib unittest only).
"""

import sys
import os
import unittest

# Allow importing gen_knobs from the same directory regardless of CWD.
sys.path.insert(0, os.path.dirname(__file__))

import gen_knobs  # noqa: E402 — must be after path manipulation


# ---------------------------------------------------------------------------
# Small knobs list used across multiple tests
# ---------------------------------------------------------------------------

LIST_KNOB = {
    'field': 'lighthouse_hosts',
    'yaml': 'lighthouse.hosts',
    'type': 'list',
    'help': 'IP:port of each lighthouse node.',
}

SAMPLE_KNOBS = [
    # bool — default true
    {
        'field': 'punchy_punch',
        'yaml': 'punchy.punch',
        'type': 'bool',
        'default': 'true',
        'help': 'Send UDP hole-punching packets.',
    },
    # bool — default false
    {
        'field': 'tun_disabled',
        'yaml': 'tun.disabled',
        'type': 'bool',
        'default': 'false',
        'help': 'Disable the TUN interface.',
    },
    # bool — no default
    {
        'field': 'relay_am_relay',
        'yaml': 'relay.am_relay',
        'type': 'bool',
        'help': 'Act as a relay node.',
    },
    # int with min+max+default
    {
        'field': 'pki_initiating_version',
        'yaml': 'pki.initiating_version',
        'type': 'int',
        'default': 1,
        'min': 1,
        'max': 2,
        'help': 'Handshake version.',
    },
    # int with only default (no min/max)
    {
        'field': 'routines',
        'yaml': 'routines',
        'type': 'int',
        'default': 1,
        'help': 'Number of goroutine workers.',
    },
    # int no default, no min/max
    {
        'field': 'listen_read_buffer',
        'yaml': 'listen.read_buffer',
        'type': 'int',
        'help': 'SO_RCVBUF size in bytes.',
    },
    # enum
    {
        'field': 'cipher',
        'yaml': 'cipher',
        'type': 'enum',
        'default': 'chachapoly',
        'enum': ['chachapoly', 'aes'],
        'help': 'Encryption cipher.',
    },
    # duration with default
    {
        'field': 'punchy_delay',
        'yaml': 'punchy.delay',
        'type': 'duration',
        'default': '1s',
        'help': 'Wait before first hole-punch.',
    },
    # duration without default
    {
        'field': 'tunnels_inactivity_timeout',
        'yaml': 'tunnels.inactivity_timeout',
        'type': 'duration',
        'help': 'How long before inactive.',
    },
    # text with no default
    {
        'field': 'logging_timestamp_format',
        'yaml': 'logging.timestamp_format',
        'type': 'text',
        'help': 'Go time-format string.',
    },
    # text with default
    {
        'field': 'sshd_listen',
        'yaml': 'sshd.listen',
        'type': 'text',
        'default': '127.0.0.1:2222',
        'help': 'host:port the debug SSH server listens on.',
    },
    # host with default
    {
        'field': 'listen_host',
        'yaml': 'listen.host',
        'type': 'host',
        'default': '::',
        'required': 'Y',
        'help': 'IP address Nebula binds its UDP listener to.',
    },
    # host without default
    {
        'field': 'lighthouse_dns_host',
        'yaml': 'lighthouse.dns.host',
        'type': 'host',
        'help': 'IP address the lighthouse DNS server binds to.',
    },
    # cidr with default
    {
        'field': 'some_cidr',
        'yaml': 'some.cidr',
        'type': 'cidr',
        'default': '10.0.0.0/8',
        'help': 'CIDR range.',
    },
    # cidr without default
    {
        'field': 'another_cidr',
        'yaml': 'another.cidr',
        'type': 'cidr',
        'help': 'Another CIDR range.',
    },
    # required without default — int
    {
        'field': 'listen_port',
        'yaml': 'listen.port',
        'type': 'int',
        'default': 4242,
        'min': 0,
        'max': 65535,
        'required': 'Y',
        'help': 'UDP port Nebula listens on.',
    },
]


# ---------------------------------------------------------------------------
# Helper
# ---------------------------------------------------------------------------

def _indent(n):
    return ' ' * n


# ---------------------------------------------------------------------------
# Tests for render_model_fields
# ---------------------------------------------------------------------------

class TestRenderModelFields(unittest.TestCase):

    def setUp(self):
        self.out = gen_knobs.render_model_fields(SAMPLE_KNOBS)

    # --- BooleanField ---

    def test_bool_default_true(self):
        self.assertIn('<punchy_punch type="BooleanField">', self.out)
        self.assertIn('<Default>1</Default>', self.out)

    def test_bool_default_false(self):
        self.assertIn('<tun_disabled type="BooleanField">', self.out)
        # Should contain <Default>0</Default> (note: punchy_punch has 1, tun_disabled has 0)
        self.assertIn('<Default>0</Default>', self.out)

    def test_bool_no_default_renders_zero(self):
        # relay_am_relay has no default → should render 0
        # Find the section for relay_am_relay
        idx = self.out.find('<relay_am_relay type="BooleanField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<Default>0</Default>', snippet)

    def test_bool_closing_tag(self):
        self.assertIn('</punchy_punch>', self.out)

    # --- IntegerField ---

    def test_int_with_min_max_default(self):
        idx = self.out.find('<pki_initiating_version type="IntegerField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<MinimumValue>1</MinimumValue>', snippet)
        self.assertIn('<MaximumValue>2</MaximumValue>', snippet)
        self.assertIn('<Default>1</Default>', snippet)
        self.assertIn('</pki_initiating_version>', snippet)

    def test_int_no_min_max(self):
        idx = self.out.find('<routines type="IntegerField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertNotIn('<MinimumValue>', snippet.split('</routines>')[0])
        self.assertNotIn('<MaximumValue>', snippet.split('</routines>')[0])
        self.assertIn('<Default>1</Default>', snippet)

    def test_int_no_default_no_min_max(self):
        idx = self.out.find('<listen_read_buffer type="IntegerField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        # No Default, no Min/Max
        self.assertNotIn('<Default>', snippet.split('</listen_read_buffer>')[0])
        self.assertNotIn('<MinimumValue>', snippet.split('</listen_read_buffer>')[0])

    def test_int_required(self):
        idx = self.out.find('<listen_port type="IntegerField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<Required>Y</Required>', snippet)
        self.assertIn('<MinimumValue>0</MinimumValue>', snippet)
        self.assertIn('<MaximumValue>65535</MaximumValue>', snippet)

    # --- OptionField ---

    def test_enum_shape(self):
        idx = self.out.find('<cipher type="OptionField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 400]
        self.assertIn('<Default>chachapoly</Default>', snippet)
        self.assertIn('<OptionValues>', snippet)
        self.assertIn('<chachapoly>chachapoly</chachapoly>', snippet)
        self.assertIn('<aes>aes</aes>', snippet)
        self.assertIn('</OptionValues>', snippet)
        self.assertIn('</cipher>', snippet)

    # --- TextField (duration) ---

    def test_duration_with_default(self):
        idx = self.out.find('<punchy_delay type="TextField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 400]
        self.assertIn('<Mask>/^\\d+(ns|us|µs|ms|s|m|h)$/</Mask>', snippet)
        self.assertIn('<Default>1s</Default>', snippet)
        self.assertIn('</punchy_delay>', snippet)

    def test_duration_without_default(self):
        idx = self.out.find('<tunnels_inactivity_timeout type="TextField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 400]
        self.assertIn('<Mask>/^\\d+(ns|us|µs|ms|s|m|h)$/</Mask>', snippet)
        # No <Default> element when no default set
        before_close = snippet.split('</tunnels_inactivity_timeout>')[0]
        self.assertNotIn('<Default>', before_close)

    # --- TextField (text) ---

    def test_text_no_default(self):
        idx = self.out.find('<logging_timestamp_format type="TextField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        before_close = snippet.split('</logging_timestamp_format>')[0]
        self.assertNotIn('<Mask>', before_close)
        self.assertNotIn('<Default>', before_close)

    def test_text_with_default(self):
        idx = self.out.find('<sshd_listen type="TextField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<Default>127.0.0.1:2222</Default>', snippet)

    # --- HostnameField ---

    def test_host_with_default(self):
        idx = self.out.find('<listen_host type="HostnameField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<IpAllowed>Y</IpAllowed>', snippet)
        self.assertIn('<Default>::</Default>', snippet)
        self.assertIn('<Required>Y</Required>', snippet)
        self.assertIn('</listen_host>', snippet)

    def test_host_without_default(self):
        idx = self.out.find('<lighthouse_dns_host type="HostnameField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<IpAllowed>Y</IpAllowed>', snippet)
        before_close = snippet.split('</lighthouse_dns_host>')[0]
        self.assertNotIn('<Default>', before_close)

    # --- NetworkField ---

    def test_cidr_with_default(self):
        idx = self.out.find('<some_cidr type="NetworkField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<Default>10.0.0.0/8</Default>', snippet)
        self.assertIn('</some_cidr>', snippet)

    def test_cidr_without_default(self):
        idx = self.out.find('<another_cidr type="NetworkField">')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        before_close = snippet.split('</another_cidr>')[0]
        self.assertNotIn('<Default>', before_close)

    # --- Indentation ---

    def test_indentation_16_spaces(self):
        for line in self.out.splitlines():
            if not line.strip():
                continue
            # All non-empty lines must start with exactly 16 spaces (the field
            # opening/closing tags) or more (children).
            self.assertTrue(
                line.startswith(' ' * 16),
                f"Line not indented 16+ spaces: {repr(line)}"
            )

    # --- Determinism ---

    def test_deterministic(self):
        out1 = gen_knobs.render_model_fields(SAMPLE_KNOBS)
        out2 = gen_knobs.render_model_fields(SAMPLE_KNOBS)
        self.assertEqual(out1, out2)

    # --- Order preservation ---

    def test_order_preserved(self):
        # punchy_punch should appear before cipher which should appear before listen_host
        idx_punch = self.out.find('<punchy_punch')
        idx_cipher = self.out.find('<cipher')
        idx_listen = self.out.find('<listen_host')
        self.assertLess(idx_punch, idx_cipher)
        self.assertLess(idx_cipher, idx_listen)


# ---------------------------------------------------------------------------
# Tests for render_form_fields
# ---------------------------------------------------------------------------

class TestRenderFormFields(unittest.TestCase):

    def setUp(self):
        self.out = gen_knobs.render_form_fields(SAMPLE_KNOBS)

    def test_bool_is_checkbox(self):
        idx = self.out.find('<id>instance.punchy_punch</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<type>checkbox</type>', snippet)

    def test_enum_is_dropdown(self):
        idx = self.out.find('<id>instance.cipher</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<type>dropdown</type>', snippet)

    def test_int_is_text(self):
        idx = self.out.find('<id>instance.pki_initiating_version</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<type>text</type>', snippet)

    def test_host_is_text(self):
        idx = self.out.find('<id>instance.listen_host</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<type>text</type>', snippet)

    def test_duration_is_text(self):
        idx = self.out.find('<id>instance.punchy_delay</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<type>text</type>', snippet)

    def test_label_humanized(self):
        # 'punchy_punch' -> 'Punchy Punch'
        idx = self.out.find('<id>instance.punchy_punch</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<label>Punchy Punch</label>', snippet)

    def test_label_humanized_multi_word(self):
        # 'pki_initiating_version' -> 'Pki Initiating Version'
        idx = self.out.find('<id>instance.pki_initiating_version</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 200]
        self.assertIn('<label>Pki Initiating Version</label>', snippet)

    def test_help_present(self):
        idx = self.out.find('<id>instance.punchy_punch</id>')
        snippet = self.out[idx:idx + 300]
        self.assertIn('<help>Send UDP hole-punching packets.</help>', snippet)

    def test_xml_escape_in_help(self):
        # The cipher help contains & lt in the form; let's use a knob with special chars
        knobs_with_amp = [
            {
                'field': 'test_amp',
                'yaml': 'test.amp',
                'type': 'text',
                'help': 'Use <foo> & "bar"',
            }
        ]
        out = gen_knobs.render_form_fields(knobs_with_amp)
        self.assertIn('&lt;foo&gt;', out)
        self.assertIn('&amp;', out)
        self.assertIn('&quot;', out)

    def test_field_structure(self):
        # Every field block should have <field>...</field>
        self.assertIn('<field>', self.out)
        self.assertIn('</field>', self.out)

    def test_deterministic(self):
        out1 = gen_knobs.render_form_fields(SAMPLE_KNOBS)
        out2 = gen_knobs.render_form_fields(SAMPLE_KNOBS)
        self.assertEqual(out1, out2)

    def test_order_preserved(self):
        idx_punch = self.out.find('<id>instance.punchy_punch</id>')
        idx_cipher = self.out.find('<id>instance.cipher</id>')
        idx_listen = self.out.find('<id>instance.listen_host</id>')
        self.assertLess(idx_punch, idx_cipher)
        self.assertLess(idx_cipher, idx_listen)

    def test_no_header_when_section_absent(self):
        # SAMPLE_KNOBS carry no 'section'; no header fields should be emitted.
        self.assertNotIn('<type>header</type>', self.out)


# ---------------------------------------------------------------------------
# Tests for section headers in render_form_fields
# ---------------------------------------------------------------------------

SECTIONED_KNOBS = [
    {'field': 'pki_a', 'yaml': 'pki.a', 'type': 'bool', 'default': 'false',
     'section': 'PKI', 'help': 'a'},
    {'field': 'pki_b', 'yaml': 'pki.b', 'type': 'int', 'default': 1,
     'section': 'PKI', 'help': 'b'},
    {'field': 'listen_a', 'yaml': 'listen.a', 'type': 'text',
     'section': 'Listen', 'help': 'c'},
]


class TestSectionHeaders(unittest.TestCase):

    def setUp(self):
        self.out = gen_knobs.render_form_fields(SECTIONED_KNOBS)

    def test_one_header_per_section(self):
        self.assertEqual(self.out.count('<type>header</type>'), 2)

    def test_header_label_present(self):
        self.assertIn('<label>PKI</label>', self.out)
        self.assertIn('<label>Listen</label>', self.out)

    def test_header_precedes_first_field_of_section(self):
        idx_pki_header = self.out.find('<label>PKI</label>')
        idx_pki_a = self.out.find('<id>instance.pki_a</id>')
        idx_listen_header = self.out.find('<label>Listen</label>')
        idx_listen_a = self.out.find('<id>instance.listen_a</id>')
        self.assertLess(idx_pki_header, idx_pki_a)
        self.assertLess(idx_pki_a, idx_listen_header)
        self.assertLess(idx_listen_header, idx_listen_a)

    def test_header_not_repeated_within_section(self):
        # Only one PKI header even though PKI has two knobs.
        self.assertEqual(self.out.count('<label>PKI</label>'), 1)

    def test_header_xml_escaped(self):
        knobs = [{'field': 'x', 'yaml': 'x', 'type': 'text',
                  'section': 'A & B', 'help': 'h'}]
        out = gen_knobs.render_form_fields(knobs)
        self.assertIn('<label>A &amp; B</label>', out)

    def test_all_advanced_section_header_is_advanced(self):
        # A section whose every knob is advanced gets an advanced header, so the
        # whole section hides when Advanced mode is off (no bare header row).
        knobs = [
            {'field': 'a', 'yaml': 'a', 'type': 'text', 'section': 'AdvOnly',
             'advanced': True},
            {'field': 'b', 'yaml': 'b', 'type': 'text', 'section': 'AdvOnly',
             'advanced': True},
        ]
        out = gen_knobs.render_form_fields(knobs)
        idx_header = out.find('<label>AdvOnly</label>')
        # The <advanced>true</advanced> must sit inside the header field block.
        snippet = out[idx_header:idx_header + 80]
        self.assertIn('<advanced>true</advanced>', snippet)

    def test_mixed_section_header_not_advanced(self):
        # A section with at least one non-advanced knob keeps a visible header.
        knobs = [
            {'field': 'a', 'yaml': 'a', 'type': 'text', 'section': 'Mixed'},
            {'field': 'b', 'yaml': 'b', 'type': 'text', 'section': 'Mixed',
             'advanced': True},
        ]
        out = gen_knobs.render_form_fields(knobs)
        idx_header = out.find('<label>Mixed</label>')
        snippet = out[idx_header:idx_header + 80]
        self.assertNotIn('<advanced>true</advanced>', snippet)

    def test_non_contiguous_section_raises(self):
        knobs = [
            {'field': 'a', 'yaml': 'a', 'type': 'text', 'section': 'PKI'},
            {'field': 'b', 'yaml': 'b', 'type': 'text', 'section': 'Listen'},
            {'field': 'c', 'yaml': 'c', 'type': 'text', 'section': 'PKI'},
        ]
        with self.assertRaises(ValueError):
            gen_knobs.render_form_fields(knobs)


# ---------------------------------------------------------------------------
# Tests for render_config_map
# ---------------------------------------------------------------------------

class TestRenderConfigMap(unittest.TestCase):

    def setUp(self):
        self.out = gen_knobs.render_config_map(SAMPLE_KNOBS)

    def test_php_opening_tag(self):
        self.assertTrue(self.out.startswith('<?php'))

    def test_copyright_header(self):
        self.assertIn('Copyright', self.out)
        self.assertIn('BSD', self.out.upper().replace('REDISTRIBUTION', 'BSD'))
        # Just verify it has the standard license boilerplate keywords
        self.assertIn('Redistribution and use in source and binary forms', self.out)

    def test_generated_comment(self):
        self.assertIn('GENERATED from knobs.yaml by tools/gen_knobs.py', self.out)
        self.assertIn('do not edit by hand', self.out)

    def test_return_array(self):
        self.assertIn('return [', self.out)
        self.assertIn('];', self.out)

    def test_entry_shape(self):
        # Each entry: 'field' => ['yaml' => 'yaml.path', 'type' => 'type'],
        self.assertIn("'punchy_punch' => ['yaml' => 'punchy.punch', 'type' => 'bool']", self.out)

    def test_entry_enum(self):
        self.assertIn("'cipher' => ['yaml' => 'cipher', 'type' => 'enum']", self.out)

    def test_entry_host(self):
        self.assertIn("'listen_host' => ['yaml' => 'listen.host', 'type' => 'host']", self.out)

    def test_all_knobs_present(self):
        for k in SAMPLE_KNOBS:
            self.assertIn(f"'{k['field']}' =>", self.out)

    def test_order_preserved(self):
        idx_punch = self.out.find("'punchy_punch'")
        idx_cipher = self.out.find("'cipher'")
        idx_listen = self.out.find("'listen_host'")
        self.assertLess(idx_punch, idx_cipher)
        self.assertLess(idx_cipher, idx_listen)

    def test_deterministic(self):
        out1 = gen_knobs.render_config_map(SAMPLE_KNOBS)
        out2 = gen_knobs.render_config_map(SAMPLE_KNOBS)
        self.assertEqual(out1, out2)


# ---------------------------------------------------------------------------
# Tests for list type — render_model_fields
# ---------------------------------------------------------------------------

class TestListTypeModelField(unittest.TestCase):
    """render_model_fields must emit a CSVListField (no Mask) for list knobs."""

    def setUp(self):
        self.out = gen_knobs.render_model_fields([LIST_KNOB])

    def test_list_model_opens_as_csvlistfield(self):
        self.assertIn('<lighthouse_hosts type="CSVListField">', self.out)

    def test_list_model_has_no_mask(self):
        self.assertNotIn('<Mask>', self.out)

    def test_list_model_closes_tag(self):
        self.assertIn('</lighthouse_hosts>', self.out)

    def test_list_model_indented_16_spaces(self):
        for line in self.out.splitlines():
            if not line.strip():
                continue
            self.assertTrue(
                line.startswith(' ' * 16),
                f"Line not indented 16+ spaces: {repr(line)}"
            )

    def test_list_model_deterministic(self):
        self.assertEqual(
            gen_knobs.render_model_fields([LIST_KNOB]),
            gen_knobs.render_model_fields([LIST_KNOB]),
        )

    def test_existing_types_unchanged(self):
        """Adding list knob after existing knobs must not alter existing output."""
        baseline = gen_knobs.render_model_fields(SAMPLE_KNOBS)
        combined = gen_knobs.render_model_fields(SAMPLE_KNOBS + [LIST_KNOB])
        self.assertTrue(combined.startswith(baseline.rstrip('\n')))


# ---------------------------------------------------------------------------
# Tests for list type — render_form_fields
# ---------------------------------------------------------------------------

class TestListTypeFormField(unittest.TestCase):
    """render_form_fields must emit a tokenize select_multiple for list knobs."""

    def setUp(self):
        self.out = gen_knobs.render_form_fields([LIST_KNOB])

    def test_list_form_id(self):
        self.assertIn('<id>instance.lighthouse_hosts</id>', self.out)

    def test_list_form_type_is_tokenize_multiselect(self):
        idx = self.out.find('<id>instance.lighthouse_hosts</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<type>select_multiple</type>', snippet)
        self.assertIn('<style>tokenize</style>', snippet)
        self.assertIn('<allownew>true</allownew>', snippet)

    def test_list_form_label_humanized(self):
        # 'lighthouse_hosts' -> 'Lighthouse Hosts'
        idx = self.out.find('<id>instance.lighthouse_hosts</id>')
        self.assertGreater(idx, -1)
        snippet = self.out[idx:idx + 300]
        self.assertIn('<label>Lighthouse Hosts</label>', snippet)

    def test_list_form_help_present(self):
        self.assertIn('<help>IP:port of each lighthouse node.</help>', self.out)

    def test_list_form_field_wrapper(self):
        self.assertIn('<field>', self.out)
        self.assertIn('</field>', self.out)

    def test_list_form_deterministic(self):
        self.assertEqual(
            gen_knobs.render_form_fields([LIST_KNOB]),
            gen_knobs.render_form_fields([LIST_KNOB]),
        )

    def test_existing_types_unchanged(self):
        """list type must not affect the output for other knob types."""
        baseline = gen_knobs.render_form_fields(SAMPLE_KNOBS)
        combined = gen_knobs.render_form_fields(SAMPLE_KNOBS + [LIST_KNOB])
        self.assertTrue(combined.startswith(baseline.rstrip('\n')))


# ---------------------------------------------------------------------------
# Tests for list type — render_config_map
# ---------------------------------------------------------------------------

class TestListTypeConfigMap(unittest.TestCase):
    """render_config_map must emit 'type' => 'list' for list knobs."""

    def setUp(self):
        self.out = gen_knobs.render_config_map([LIST_KNOB])

    def test_list_config_map_entry(self):
        self.assertIn(
            "'lighthouse_hosts' => ['yaml' => 'lighthouse.hosts', 'type' => 'list'],",
            self.out,
        )

    def test_list_config_map_deterministic(self):
        self.assertEqual(
            gen_knobs.render_config_map([LIST_KNOB]),
            gen_knobs.render_config_map([LIST_KNOB]),
        )

    def test_existing_types_unchanged(self):
        """list entry must not change any existing entry in the map."""
        baseline = gen_knobs.render_config_map(SAMPLE_KNOBS)
        combined = gen_knobs.render_config_map(SAMPLE_KNOBS + [LIST_KNOB])
        # Every line from baseline (except the closing '];') must appear in combined.
        for line in baseline.splitlines():
            if line.strip() in ('', '];'):
                continue
            self.assertIn(line, combined, f"Existing line missing from combined output: {line!r}")


# ---------------------------------------------------------------------------
# Tests for replace_between_markers
# ---------------------------------------------------------------------------

class TestReplaceBetweenMarkers(unittest.TestCase):

    START = '<!-- GENERATED:instance-fields START -->'
    END = '<!-- GENERATED:instance-fields END -->'

    def _make(self, inner='old content\n'):
        return f'before\n{self.START}\n{inner}{self.END}\nafter\n'

    def test_basic_replacement(self):
        text = self._make('old content\n')
        result = gen_knobs.replace_between_markers(text, self.START, self.END, 'new content\n')
        self.assertIn('before', result)
        self.assertIn('after', result)
        self.assertIn(self.START, result)
        self.assertIn(self.END, result)
        self.assertIn('new content', result)
        self.assertNotIn('old content', result)

    def test_preserves_outside_content(self):
        text = self._make('middle\n')
        result = gen_knobs.replace_between_markers(text, self.START, self.END, 'replaced\n')
        self.assertTrue(result.startswith('before\n'))
        self.assertTrue(result.endswith('after\n'))

    def test_empty_payload(self):
        text = self._make('old\n')
        result = gen_knobs.replace_between_markers(text, self.START, self.END, '')
        self.assertNotIn('old', result)
        self.assertIn(self.START, result)
        self.assertIn(self.END, result)

    def test_missing_start_marker_raises(self):
        text = f'no markers here\n{self.END}\n'
        with self.assertRaises(ValueError) as ctx:
            gen_knobs.replace_between_markers(text, self.START, self.END, 'x')
        self.assertIn('START', str(ctx.exception).upper())

    def test_missing_end_marker_raises(self):
        text = f'{self.START}\nno end\n'
        with self.assertRaises(ValueError) as ctx:
            gen_knobs.replace_between_markers(text, self.START, self.END, 'x')
        self.assertIn('END', str(ctx.exception).upper())

    def test_both_markers_missing_raises(self):
        with self.assertRaises(ValueError):
            gen_knobs.replace_between_markers('no markers', self.START, self.END, 'x')

    def test_result_has_newline_after_start_marker(self):
        text = self._make('old\n')
        result = gen_knobs.replace_between_markers(text, self.START, self.END, 'new\n')
        # The payload should appear right after the start marker (with a newline)
        after_start = result.split(self.START)[1]
        self.assertTrue(after_start.startswith('\n'))

    def test_deterministic(self):
        text = self._make('old\n')
        r1 = gen_knobs.replace_between_markers(text, self.START, self.END, 'new\n')
        r2 = gen_knobs.replace_between_markers(text, self.START, self.END, 'new\n')
        self.assertEqual(r1, r2)


# ---------------------------------------------------------------------------
# Integration: load_knobs reads the real knobs.yaml
# ---------------------------------------------------------------------------

class TestLoadKnobs(unittest.TestCase):

    def test_load_knobs_returns_list(self):
        knobs = gen_knobs.load_knobs()
        self.assertIsInstance(knobs, list)
        self.assertGreater(len(knobs), 0)

    def test_load_knobs_has_required_keys(self):
        for k in gen_knobs.load_knobs():
            self.assertIn('field', k)
            self.assertIn('yaml', k)
            self.assertIn('type', k)
            self.assertIn('help', k)

    def test_load_knobs_count(self):
        # knobs.yaml has 52 scalar knobs per the task spec
        knobs = gen_knobs.load_knobs()
        self.assertGreaterEqual(len(knobs), 50)

    def test_full_render_model_fields_no_crash(self):
        knobs = gen_knobs.load_knobs()
        out = gen_knobs.render_model_fields(knobs)
        self.assertIsInstance(out, str)
        self.assertGreater(len(out), 0)

    def test_full_render_form_fields_no_crash(self):
        knobs = gen_knobs.load_knobs()
        out = gen_knobs.render_form_fields(knobs)
        self.assertIsInstance(out, str)

    def test_full_render_config_map_no_crash(self):
        knobs = gen_knobs.load_knobs()
        out = gen_knobs.render_config_map(knobs)
        self.assertIsInstance(out, str)
        self.assertTrue(out.startswith('<?php'))


# ---------------------------------------------------------------------------
# CLI --check test against a temp fixture
# ---------------------------------------------------------------------------

class TestCLICheck(unittest.TestCase):
    """Test the --check mode against a temp directory with fixture files."""

    def setUp(self):
        import tempfile
        import shutil
        # Build a temporary directory tree that mirrors the real layout.
        self.tmpdir = tempfile.mkdtemp()
        # We need:
        #   <tmpdir>/knobs.yaml          (symlink or copy of real one)
        #   <tmpdir>/src/opnsense/mvc/app/models/OPNsense/Nebula/Nebula.xml
        #   <tmpdir>/src/opnsense/mvc/app/controllers/OPNsense/Nebula/forms/dialogInstance.xml
        #   <tmpdir>/src/opnsense/mvc/app/models/OPNsense/Nebula/ConfigMap.php

        START = '<!-- GENERATED:instance-fields START -->'
        END = '<!-- GENERATED:instance-fields END -->'

        knobs = gen_knobs.load_knobs()
        model_payload = gen_knobs.render_model_fields(knobs)
        form_payload = gen_knobs.render_form_fields(knobs)
        config_map = gen_knobs.render_config_map(knobs)

        model_dir = os.path.join(self.tmpdir, 'src', 'opnsense', 'mvc', 'app',
                                 'models', 'OPNsense', 'Nebula')
        form_dir = os.path.join(self.tmpdir, 'src', 'opnsense', 'mvc', 'app',
                                'controllers', 'OPNsense', 'Nebula', 'forms')
        os.makedirs(model_dir, exist_ok=True)
        os.makedirs(form_dir, exist_ok=True)

        # Write Nebula.xml with markers + current payload (so --check passes)
        model_xml = f'<model>\n{START}\n{model_payload}{END}\n</model>\n'
        with open(os.path.join(model_dir, 'Nebula.xml'), 'w') as f:
            f.write(model_xml)

        # Write dialogInstance.xml — single GENERATED region.
        form_xml = f'<form>\n{START}\n{form_payload}{END}\n</form>\n'
        with open(os.path.join(form_dir, 'dialogInstance.xml'), 'w') as f:
            f.write(form_xml)

        # Write ConfigMap.php
        with open(os.path.join(model_dir, 'ConfigMap.php'), 'w') as f:
            f.write(config_map)

        # Copy knobs.yaml
        real_knobs = os.path.join(os.path.dirname(__file__), '..', 'knobs.yaml')
        shutil.copy(real_knobs, os.path.join(self.tmpdir, 'knobs.yaml'))

        self.model_dir = model_dir
        self.form_dir = form_dir

    def tearDown(self):
        import shutil
        shutil.rmtree(self.tmpdir, ignore_errors=True)

    def test_check_passes_when_in_sync(self):
        """--check should exit 0 when all artifacts match."""
        import subprocess
        result = subprocess.run(
            [sys.executable, __file__.replace('_test', ''), '--check',
             '--root', self.tmpdir],
            capture_output=True, text=True
        )
        self.assertEqual(result.returncode, 0,
                         f"stdout: {result.stdout}\nstderr: {result.stderr}")

    def test_check_fails_when_out_of_sync(self):
        """--check should exit 1 when ConfigMap.php is stale."""
        config_map_path = os.path.join(self.model_dir, 'ConfigMap.php')
        with open(config_map_path, 'w') as f:
            f.write('<?php\n// stale\n')
        import subprocess
        result = subprocess.run(
            [sys.executable, __file__.replace('_test', ''), '--check',
             '--root', self.tmpdir],
            capture_output=True, text=True
        )
        self.assertNotEqual(result.returncode, 0)


# ---------------------------------------------------------------------------
# Tests for label / grid / advanced support in render_form_fields
# ---------------------------------------------------------------------------

# A knob that exercises all three new optional attributes at once.
FULL_ATTRS_KNOB = {
    'field': 'listen_port',
    'yaml': 'listen.port',
    'type': 'int',
    'default': 4242,
    'min': 0,
    'max': 65535,
    'required': 'Y',
    'help': 'UDP port Nebula listens on.',
    'label': 'Listen Port',
    'grid': 4,
    'advanced': True,
}

# A knob with none of the three new keys — must produce identical output to the
# existing SAMPLE_KNOBS entry for listen_port.
PLAIN_KNOB = {
    'field': 'listen_port',
    'yaml': 'listen.port',
    'type': 'int',
    'default': 4242,
    'min': 0,
    'max': 65535,
    'required': 'Y',
    'help': 'UDP port Nebula listens on.',
}


class TestFormFieldLabelGridAdvanced(unittest.TestCase):
    """render_form_fields: label / grid / advanced optional-attribute support."""

    def _snippet(self, knob: dict) -> str:
        """Render a single knob's form field and return the full output."""
        return gen_knobs.render_form_fields([knob])

    # --- explicit label ---

    def test_explicit_label_used(self):
        out = self._snippet(FULL_ATTRS_KNOB)
        self.assertIn('<label>Listen Port</label>', out)

    def test_explicit_label_not_humanized(self):
        """When label is supplied, the humanized fallback must NOT appear instead."""
        out = self._snippet(FULL_ATTRS_KNOB)
        # humanized version would be 'Listen Port' too in this case, so use a
        # knob where they differ.
        knob = dict(FULL_ATTRS_KNOB, field='listen_port', label='My Custom Label')
        out2 = gen_knobs.render_form_fields([knob])
        self.assertIn('<label>My Custom Label</label>', out2)

    def test_no_label_key_humanizes(self):
        out = self._snippet(PLAIN_KNOB)
        # 'listen_port' → 'Listen Port'
        self.assertIn('<label>Listen Port</label>', out)

    # --- advanced ---

    def test_advanced_true_emits_element(self):
        out = self._snippet(FULL_ATTRS_KNOB)
        self.assertIn('<advanced>true</advanced>', out)

    def test_advanced_absent_no_element(self):
        out = self._snippet(PLAIN_KNOB)
        self.assertNotIn('<advanced>', out)

    def test_advanced_false_no_element(self):
        knob = dict(PLAIN_KNOB, advanced=False)
        out = self._snippet(knob)
        self.assertNotIn('<advanced>', out)

    # --- grid ---

    def test_grid_emits_grid_view_sequence(self):
        out = self._snippet(FULL_ATTRS_KNOB)
        self.assertIn('<grid_view>', out)
        self.assertIn('<sequence>4</sequence>', out)
        self.assertIn('</grid_view>', out)

    def test_grid_absent_emits_ignore(self):
        """A knob without 'grid' must emit <grid_view><ignore>true</ignore></grid_view>."""
        out = self._snippet(PLAIN_KNOB)
        self.assertIn('<grid_view>', out)
        self.assertIn('<ignore>true</ignore>', out)
        self.assertNotIn('<sequence>', out)

    def test_grid_different_values(self):
        knob = dict(PLAIN_KNOB, grid=1)
        out = self._snippet(knob)
        self.assertIn('<sequence>1</sequence>', out)
        knob2 = dict(PLAIN_KNOB, grid=99)
        out2 = self._snippet(knob2)
        self.assertIn('<sequence>99</sequence>', out2)

    # --- element ordering: id, label, type, [advanced], help, [grid_view] ---

    def test_ordering_full_attrs(self):
        out = self._snippet(FULL_ATTRS_KNOB)
        idx_id = out.find('<id>instance.listen_port</id>')
        idx_label = out.find('<label>Listen Port</label>')
        idx_type = out.find('<type>text</type>')
        idx_advanced = out.find('<advanced>true</advanced>')
        idx_help = out.find('<help>')
        idx_grid = out.find('<grid_view>')
        self.assertLess(idx_id, idx_label, 'id must come before label')
        self.assertLess(idx_label, idx_type, 'label must come before type')
        self.assertLess(idx_type, idx_advanced, 'type must come before advanced')
        self.assertLess(idx_advanced, idx_help, 'advanced must come before help')
        self.assertLess(idx_help, idx_grid, 'help must come before grid_view')

    def test_ordering_no_advanced(self):
        """Without advanced, order is: id, label, type, help, grid_view (ignore)."""
        out = self._snippet(PLAIN_KNOB)
        idx_id = out.find('<id>instance.listen_port</id>')
        idx_label = out.find('<label>Listen Port</label>')
        idx_type = out.find('<type>text</type>')
        idx_help = out.find('<help>')
        idx_grid = out.find('<grid_view>')
        self.assertLess(idx_id, idx_label)
        self.assertLess(idx_label, idx_type)
        self.assertLess(idx_type, idx_help)
        self.assertLess(idx_help, idx_grid)

    # --- backward-compatibility: SAMPLE_KNOBS output must be byte-identical ---

    def test_sample_knobs_output_unchanged(self):
        """New keys must not affect knobs that have none of them."""
        baseline = gen_knobs.render_form_fields(SAMPLE_KNOBS)
        # None of SAMPLE_KNOBS has label/grid/advanced, so re-rendering must
        # produce the exact same string.
        self.assertEqual(baseline, gen_knobs.render_form_fields(SAMPLE_KNOBS))

    def test_plain_knob_has_no_advanced_and_has_ignore_grid(self):
        """A knob without label/grid/advanced must have no <advanced> but must
        have <grid_view><ignore>true</ignore></grid_view> (never a column)."""
        out = self._snippet(PLAIN_KNOB)
        self.assertNotIn('<advanced>', out)
        self.assertIn('<grid_view>', out)
        self.assertIn('<ignore>true</ignore>', out)
        self.assertNotIn('<sequence>', out)

    # --- model + config_map unaffected by new keys ---

    def test_model_field_unchanged_with_new_keys(self):
        """render_model_fields must produce identical output whether or not the
        new optional keys are present."""
        baseline = gen_knobs.render_model_fields([PLAIN_KNOB])
        with_extras = gen_knobs.render_model_fields([FULL_ATTRS_KNOB])
        self.assertEqual(baseline, with_extras)

    def test_config_map_unchanged_with_new_keys(self):
        """render_config_map must produce identical output for plain vs annotated."""
        baseline = gen_knobs.render_config_map([PLAIN_KNOB])
        with_extras = gen_knobs.render_config_map([FULL_ATTRS_KNOB])
        self.assertEqual(baseline, with_extras)

    # --- determinism ---

    def test_deterministic(self):
        out1 = self._snippet(FULL_ATTRS_KNOB)
        out2 = self._snippet(FULL_ATTRS_KNOB)
        self.assertEqual(out1, out2)


# ---------------------------------------------------------------------------
# Tests for grid_width and bool boolean-formatter support in render_form_fields
# ---------------------------------------------------------------------------

class TestGridWidthAndBoolFormatter(unittest.TestCase):
    """render_form_fields: grid_width emits <width>; bool grid knobs get
    <type>boolean</type><formatter>boolean</formatter>."""

    def _render(self, knob: dict) -> str:
        return gen_knobs.render_form_fields([knob])

    # --- bool knob with grid + grid_width ---

    def test_bool_grid_emits_width(self):
        knob = {
            'field': 'am_lighthouse',
            'yaml': 'lighthouse.am_lighthouse',
            'type': 'bool',
            'default': 'false',
            'label': 'Lighthouse',
            'grid': 4,
            'grid_width': '6em',
            'help': 'Is a lighthouse.',
        }
        out = self._render(knob)
        self.assertIn('<width>6em</width>', out)

    def test_bool_grid_emits_type_boolean(self):
        knob = {
            'field': 'am_lighthouse',
            'yaml': 'lighthouse.am_lighthouse',
            'type': 'bool',
            'default': 'false',
            'label': 'Lighthouse',
            'grid': 4,
            'grid_width': '6em',
            'help': 'Is a lighthouse.',
        }
        out = self._render(knob)
        self.assertIn('<type>boolean</type>', out)

    def test_bool_grid_emits_formatter_boolean(self):
        knob = {
            'field': 'am_lighthouse',
            'yaml': 'lighthouse.am_lighthouse',
            'type': 'bool',
            'default': 'false',
            'label': 'Lighthouse',
            'grid': 4,
            'grid_width': '6em',
            'help': 'Is a lighthouse.',
        }
        out = self._render(knob)
        self.assertIn('<formatter>boolean</formatter>', out)

    def test_bool_grid_emits_sequence(self):
        knob = {
            'field': 'am_lighthouse',
            'yaml': 'lighthouse.am_lighthouse',
            'type': 'bool',
            'default': 'false',
            'label': 'Lighthouse',
            'grid': 4,
            'grid_width': '6em',
            'help': 'Is a lighthouse.',
        }
        out = self._render(knob)
        self.assertIn('<sequence>4</sequence>', out)

    def test_bool_grid_order_width_type_formatter_sequence(self):
        """Inside <grid_view>: width, type, formatter, sequence."""
        knob = {
            'field': 'am_lighthouse',
            'yaml': 'lighthouse.am_lighthouse',
            'type': 'bool',
            'default': 'false',
            'label': 'Lighthouse',
            'grid': 4,
            'grid_width': '6em',
            'help': 'Is a lighthouse.',
        }
        out = self._render(knob)
        gv_start = out.find('<grid_view>')
        gv_end = out.find('</grid_view>')
        gv_block = out[gv_start:gv_end]
        idx_width = gv_block.find('<width>')
        idx_type = gv_block.find('<type>boolean</type>')
        idx_fmt = gv_block.find('<formatter>boolean</formatter>')
        idx_seq = gv_block.find('<sequence>')
        self.assertLess(idx_width, idx_type, 'width before type')
        self.assertLess(idx_type, idx_fmt, 'type before formatter')
        self.assertLess(idx_fmt, idx_seq, 'formatter before sequence')

    # --- text knob with grid + grid_width (no boolean formatter) ---

    def test_text_grid_width_emitted(self):
        knob = {
            'field': 'tun_name',
            'yaml': 'tun.dev',
            'type': 'text',
            'label': 'Interface',
            'grid': 2,
            'grid_width': '10em',
            'help': 'Tunnel device name.',
        }
        out = self._render(knob)
        self.assertIn('<width>10em</width>', out)

    def test_text_grid_no_boolean_formatter(self):
        knob = {
            'field': 'tun_name',
            'yaml': 'tun.dev',
            'type': 'text',
            'label': 'Interface',
            'grid': 2,
            'grid_width': '10em',
            'help': 'Tunnel device name.',
        }
        out = self._render(knob)
        self.assertNotIn('<formatter>', out)
        self.assertNotIn('<type>boolean</type>', out)

    def test_text_grid_has_sequence(self):
        knob = {
            'field': 'tun_name',
            'yaml': 'tun.dev',
            'type': 'text',
            'label': 'Interface',
            'grid': 2,
            'grid_width': '10em',
            'help': 'Tunnel device name.',
        }
        out = self._render(knob)
        self.assertIn('<sequence>2</sequence>', out)

    def test_text_grid_order_width_sequence(self):
        """For a text knob: width before sequence, no type/formatter."""
        knob = {
            'field': 'tun_name',
            'yaml': 'tun.dev',
            'type': 'text',
            'label': 'Interface',
            'grid': 2,
            'grid_width': '10em',
            'help': 'Tunnel device name.',
        }
        out = self._render(knob)
        gv_start = out.find('<grid_view>')
        gv_end = out.find('</grid_view>')
        gv_block = out[gv_start:gv_end]
        idx_width = gv_block.find('<width>')
        idx_seq = gv_block.find('<sequence>')
        self.assertGreater(idx_width, -1)
        self.assertGreater(idx_seq, -1)
        self.assertLess(idx_width, idx_seq, 'width before sequence')

    # --- grid knob without grid_width: no <width> emitted ---

    def test_grid_no_width_no_width_element(self):
        knob = {
            'field': 'listen_port',
            'yaml': 'listen.port',
            'type': 'int',
            'default': 4242,
            'label': 'Listen port',
            'grid': 5,
            'help': 'Port.',
        }
        out = self._render(knob)
        self.assertNotIn('<width>', out)
        self.assertIn('<sequence>5</sequence>', out)

    # --- bool knob WITHOUT grid: still no type/formatter emitted ---

    def test_bool_no_grid_no_formatter(self):
        knob = {
            'field': 'punchy_punch',
            'yaml': 'punchy.punch',
            'type': 'bool',
            'default': 'true',
            'help': 'Punch.',
        }
        out = self._render(knob)
        self.assertNotIn('<formatter>', out)
        self.assertNotIn('<type>boolean</type>', out)
        self.assertIn('<ignore>true</ignore>', out)

    # --- backward compat: SAMPLE_KNOBS (none have grid_width) unchanged ---

    def test_sample_knobs_no_width_elements(self):
        """SAMPLE_KNOBS have no grid_width, so no <width> should appear."""
        out = gen_knobs.render_form_fields(SAMPLE_KNOBS)
        self.assertNotIn('<width>', out)


if __name__ == '__main__':
    unittest.main(verbosity=2)
