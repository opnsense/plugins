import unittest
from pathlib import Path


class JournalLifecycleTest(unittest.TestCase):
    def test_stop_clears_journals_for_reverse_model_zones(self):
        bind_root = Path(__file__).resolve().parents[6]
        stop_script = (
            bind_root / "src/opnsense/scripts/OPNsense/Bind/bindStop.sh"
        ).read_text()

        self.assertIn('(string)$domain->type === "reverse"', stop_script)
        self.assertIn(
            "echo (string)$domain->domainname, PHP_EOL;", stop_script
        )


if __name__ == "__main__":
    unittest.main()
