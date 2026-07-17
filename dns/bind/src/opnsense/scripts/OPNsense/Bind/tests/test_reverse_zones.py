import pathlib
import unittest


VIEW = pathlib.Path(__file__).resolve().parents[4] / 'mvc/app/views/OPNsense/Bind/reverse_zones.volt'


class TestReverseZonesGrid(unittest.TestCase):
    def test_enabled_state_uses_the_actions_toggle_without_a_duplicate_column(self):
        view = VIEW.read_text()

        self.assertIn('command-toggle', view)
        self.assertNotIn('data-column-id="enabled"', view)


if __name__ == '__main__':
    unittest.main()
