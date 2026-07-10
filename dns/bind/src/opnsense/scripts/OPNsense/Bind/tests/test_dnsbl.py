import importlib.util
import os
import tempfile
import unittest


SPEC = importlib.util.spec_from_file_location(
    'dnsbl', os.path.join(os.path.dirname(__file__), '..', 'dnsbl.py')
)
dnsbl = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(dnsbl)


class TestNormalizeDomains(unittest.TestCase):
    def test_accepts_hosts_plain_and_adblock_domains(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as raw:
            raw.write('0.0.0.0 ads.example\nplain.example\n||tracker.example^\n')
            path = raw.name
        try:
            self.assertEqual(
                dnsbl.normalize_domains(path),
                {'ads.example', 'plain.example', 'tracker.example'}
            )
        finally:
            os.unlink(path)

    def test_rejects_non_dns_owner_names(self):
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as raw:
            raw.write('||invalid owner^\n/regex/\nlocalhost\n')
            path = raw.name
        try:
            self.assertEqual(dnsbl.normalize_domains(path), set())
        finally:
            os.unlink(path)
