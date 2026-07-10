import unittest
from pathlib import Path


class ListenerLifecycleTest(unittest.TestCase):
    def test_start_and_restart_render_selected_listener_interfaces(self):
        bind_root = Path(__file__).resolve().parents[6]
        plugin = (bind_root / "src/etc/inc/plugins.inc.d/bind.inc").read_text()
        actions = (bind_root / "src/opnsense/service/conf/actions.d/actions_bind.conf").read_text()
        template = (bind_root / "src/opnsense/service/templates/OPNsense/Bind/named.conf").read_text()

        self.assertIn("'bind_start' => ['bind_configure_do']", plugin)
        self.assertIn("mwexecf('/usr/local/opnsense/scripts/OPNsense/Bind/bindStart.sh');", plugin)
        self.assertIn("[start]\ncommand:/usr/local/sbin/pluginctl -c bind_start", actions)
        self.assertIn("[restart]\ncommand:/usr/local/sbin/pluginctl -c bind_start", actions)
        self.assertNotIn("[service.start]", actions)
        self.assertIn("mwexecf('/usr/local/opnsense/scripts/OPNsense/Bind/bindStart.sh');", plugin)
        self.assertIn("# bind-listener-directives", template)
        self.assertNotIn("bind-listen.conf", template)
        self.assertLess(
            plugin.index("configd_run('template reload OPNsense/Bind')"),
            plugin.index('bind_generate_listen_config();')
        )

    def test_start_keeps_watcher_running_when_named_is_already_up(self):
        bind_root = Path(__file__).resolve().parents[6]
        start = (bind_root / "src/opnsense/scripts/OPNsense/Bind/bindStart.sh").read_text()
        watcher_start = (
            bind_root / "src/opnsense/scripts/OPNsense/Bind/dhcpwatcherStart.sh"
        ).read_text()

        self.assertIn('if ! "${BIND_START_NAMED_RC}" status >/dev/null 2>&1; then', start)
        self.assertIn('"${BIND_START_NAMED_RC}" start || exit $?', start)
        self.assertLess(
            start.index('"${BIND_START_NAMED_RC}" start || exit $?'),
            start.index('"${BIND_START_DHCPWATCHER}"'),
        )
        self.assertIn('WATCHER_PIDFILE=', watcher_start)
        self.assertIn('kill -0 "$watcher_pid"', watcher_start)


if __name__ == "__main__":
    unittest.main()
