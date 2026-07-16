import pathlib
import unittest


PLUGIN_ROOT = pathlib.Path(__file__).resolve().parents[4]
CONTROLLER = PLUGIN_ROOT / 'mvc/app/controllers/OPNsense/Bind/Api/DhcprecordController.php'
RECORDS_VIEW = PLUGIN_ROOT / 'mvc/app/views/OPNsense/Bind/records.volt'


class TestDhcpRecordsDomainColumn(unittest.TestCase):
    def test_active_records_expose_and_display_the_watcher_domain(self):
        controller = CONTROLLER.read_text()
        view = RECORDS_VIEW.read_text()

        self.assertIn("'domain' => $lease['suffix'] ?? ''", controller)
        self.assertIn('data-column-id="domain"', view)
        self.assertIn("lang._('Domain')", view)

    def test_legacy_state_is_not_presented_as_an_active_record(self):
        controller = CONTROLLER.read_text()

        self.assertIn("empty($lease['suffix'])", controller)
