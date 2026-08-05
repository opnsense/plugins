# Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

import unittest
from pathlib import Path


class ListenerLifecycleTest(unittest.TestCase):
    def test_start_and_restart_render_selected_listener_interfaces(self):
        bind_root = Path(__file__).resolve().parents[1]
        plugin = (bind_root / "src/etc/inc/plugins.inc.d/bind.inc").read_text()
        actions = (bind_root / "src/opnsense/service/conf/actions.d/actions_bind.conf").read_text()
        template = (bind_root / "src/opnsense/service/templates/OPNsense/Bind/named.conf").read_text()

        self.assertIn("'bind_start' => ['bind_configure_do']", plugin)
        self.assertIn("mwexecf('/usr/local/opnsense/scripts/OPNsense/Bind/bindStart.sh');", plugin)
        self.assertIn("[start]\ncommand:/usr/local/sbin/pluginctl -c bind_start", actions)
        self.assertIn(
            "[restart]\n"
            "command:/usr/local/opnsense/scripts/OPNsense/Bind/bindStop.py && "
            "/usr/local/sbin/pluginctl -c bind_start",
            actions,
        )
        self.assertNotIn("[service.start]", actions)
        self.assertIn("mwexecf('/usr/local/opnsense/scripts/OPNsense/Bind/bindStart.sh');", plugin)
        self.assertIn("# bind-listener-directives", template)
        self.assertNotIn("bind-listen.conf", template)
        self.assertLess(
            plugin.index("configd_run('template reload OPNsense/Bind')"),
            plugin.index('bind_generate_listen_config();')
        )

    def test_start_keeps_watcher_running_when_named_is_already_up(self):
        bind_root = Path(__file__).resolve().parents[1]
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
