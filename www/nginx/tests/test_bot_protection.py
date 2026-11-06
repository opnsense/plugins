# SPDX-License-Identifier: BSD-2-Clause
"""Render the plugin's HTTP template and exercise its bot check with nginx."""

from contextlib import contextmanager
import http.client
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import uuid
import xml.etree.ElementTree as ET

import jinja2


PLUGIN = Path(__file__).resolve().parents[1]
MODEL = ET.parse(PLUGIN / "src/opnsense/mvc/app/models/OPNsense/Nginx/Nginx.xml")
TEMPLATES = PLUGIN / "src/opnsense/service/templates"
NGINX = os.environ.get("NGINX", shutil.which("nginx"))
# IPv4 fixtures use documentation-only networks (RFC 5737).
TRUSTED = "192.0.2.0/24,198.51.100.0/24,2001:db8:1::/64,203.0.113.7,2001:db8::7"
DEFAULT_BOTS = MODEL.findtext("items/http/bots_ua/Default")
BROWSER = "Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/128.0"


def defaults(section):
    return {node.tag: node.findtext("Default") for node in MODEL.find(section)
            if node.find("Default") is not None}


class Helpers:
    def __init__(self, config):
        self.config = config

    def toList(self, path):
        return self.config.get(path.rsplit(".", 1)[-1], [])

    def exists(self, path):
        return path == "OPNsense.Nginx"

    def getUUID(self, value):
        return next(item for items in self.config.values() if isinstance(items, list)
                    for item in items if item["@uuid"] == value)


@unittest.skipUnless(NGINX, "nginx with the HTTP real-IP module is required")
class BotProtectionTest(unittest.TestCase):
    @contextmanager
    def running(self, networks=TRUSTED, bots=DEFAULT_BOTS):
        with tempfile.TemporaryDirectory(prefix="nginx-bots-") as directory:
            root = Path(directory)
            (root / "index.txt").write_text("allowed")
            (root / "opnsense_error_403.html").write_text("forbidden")
            (root / "mime.types").write_text("")
            (root / "fastcgi_params").write_text("")
            (root / "passwords").write_text("")
            with socket.socket() as listener:
                listener.bind(("127.0.0.1", 0))
                port = listener.getsockname()[1]
            http = defaults("items/http")
            for key, value in (("bots_ua", bots), ("bot_protection_trusted_networks", networks)):
                if value is None:
                    http.pop(key, None)
                else:
                    http[key] = value
            servers = []
            trap = {"@uuid": str(uuid.uuid4()), "matchtype": "=", "urlpattern": "/trap", "honeypot": "1"}
            for name, options in (
                ("direct", {}),
                ("proxy", {"real_ip_source": "X-Real-IP", "trusted_proxies": "127.0.0.1"}),
                ("forwarded", {"real_ip_source": "X-Forwarded-For", "trusted_proxies": "127.0.0.1"}),
                ("untrusted", {"real_ip_source": "X-Real-IP", "trusted_proxies": "203.0.113.254"}),
                ("disabled", {"disable_bot_protection": "1"}),
            ):
                server = defaults("items/http_server")
                server.update({
                    "@uuid": str(uuid.uuid4()), "servername": name + ".test",
                    "listen_http_address": "127.0.0.1:" + str(port),
                    "access_log_format": "disabled", "log_handshakes": "0",
                    "root": str(root), "locations": trap["@uuid"],
                })
                if name == "direct":
                    server.pop("disable_bot_protection", None)
                server.update(options)
                servers.append(server)
                hooks = root / (server["@uuid"] + "_post")
                hooks.mkdir()
                (hooks / "test.conf").write_text(
                    'location / { try_files $uri =404; }\n'
                    'location = /acl { deny all; }\n'
                    'location = /auth { auth_basic "test"; auth_basic_user_file passwords; }\n'
                )
            config = {"general": {"enabled": "1"}, "http": http,
                      "http_server": servers, "location": [trap]}
            env = jinja2.Environment(loader=jinja2.FileSystemLoader(TEMPLATES), trim_blocks=True,
                                     extensions=["jinja2.ext.do", "jinja2.ext.loopcontrols"])
            rendered = env.get_template("OPNsense/Nginx/http.conf").render(
                OPNsense={"Nginx": config}, helpers=Helpers(config))
            # Stock nginx lacks these unrelated OPNsense modules/variables.
            # Keep all geo/map, server, real-IP, bot, ACL and ban directives intact.
            rendered = re.sub(r"^(?:js_import|js_set|brotli_static|brotli|log_format  handshake).*\n",
                              "", rendered, flags=re.MULTILINE)
            rendered = rendered.replace("/var/log/nginx/", str(root) + "/")
            rendered = rendered.replace("/usr/local/etc/nginx/views", str(root))
            path = root / "nginx.conf"
            path.write_text(
                f"daemon off;\nmaster_process off;\npid {root}/nginx.pid;\n"
                f"error_log {root}/error.log;\nevents {{}}\nhttp {{\n{rendered}\n}}\n"
            )
            command = [NGINX, "-p", directory + "/", "-c", str(path)]
            checked = subprocess.run(command + ["-t"], capture_output=True, text=True)
            self.assertEqual(checked.returncode, 0, checked.stderr)
            with (root / "process.log").open("w+") as output:
                process = subprocess.Popen(command, stdout=output, stderr=output)
                try:
                    for _ in range(100):
                        try:
                            with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                                break
                        except OSError:
                            if process.poll() is not None:
                                self.fail("nginx exited: " + (root / "process.log").read_text())
                            time.sleep(0.01)
                    else:
                        self.fail("nginx did not start")
                    self.port, self.root = port, root
                    yield rendered
                finally:
                    if process.poll() is None:
                        process.terminate()
                    process.wait(timeout=5)

    def request(self, ua="python-requests/2.32", host="proxy", address=None,
                path="/index.txt", headers=None, banned=False, status=200):
        log = self.root / "permanentban.access.log"
        before = log.read_text() if log.exists() else ""
        fields = {"Host": host + ".test", "User-Agent": ua, **(headers or {})}
        if address:
            fields["X-Forwarded-For" if host == "forwarded" else "X-Real-IP"] = address
        connection = http.client.HTTPConnection("127.0.0.1", self.port, timeout=3)
        try:
            connection.request("GET", path, headers=fields)
            response = connection.getresponse()
            body = response.read()
            self.assertEqual(response.status, status, body)
        finally:
            connection.close()
        # Wait for the worker to finish the request's unbuffered access logging.
        time.sleep(0.01)
        after = log.read_text() if log.exists() else ""
        self.assertEqual(after != before, banned, after[len(before):])

    def test_trusted_and_untrusted_sources(self):
        with self.running() as rendered:
            self.assertEqual(rendered.count("geo $remote_addr $bot_protection_untrusted"), 1)
            self.assertEqual(rendered.count("if ($bot_protection_block)"), 4)
            for ua in ("python-requests/2.32", "Python-urllib/3.12", "PYTHON-REQUESTS/2.32"):
                for address in ("192.0.2.5", "198.51.100.6", "2001:db8:1::5",
                                "203.0.113.7", "2001:db8::7"):
                    with self.subTest(ua=ua, address=address):
                        self.request(ua, address=address)
                for address in ("203.0.113.5", "203.0.113.6", "2001:db8:2::5",
                                "203.0.113.8", "2001:db8::8"):
                    self.request(ua, address=address, status=403, banned=True)
            self.request(host="direct", status=403, banned=True)
            self.request(host="forwarded", address="192.0.2.5")
            for address in ("192.0.2.5", "203.0.113.5"):
                self.request(ua=BROWSER, address=address)
                self.request(ua="", address=address)
            self.request(ua="Mozilla/5.0", address="203.0.113.5", status=403, banned=True)
            self.request(host="disabled", address="203.0.113.5")
            self.request(address="192.0.2.5", path="/trap", status=403, banned=True)
            self.request(address="192.0.2.5", path="/acl", status=403)
            self.request(address="192.0.2.5", path="/auth", status=401)
        with self.running(networks="127.0.0.1"):
            self.request(host="direct")

    def test_forwarding_headers_need_a_trusted_proxy(self):
        with self.running():
            forged = {"X-Real-IP": "192.0.2.5", "X-Forwarded-For": "198.51.100.6"}
            self.request(host="direct", headers=forged, status=403, banned=True)
            # This source is not trusted by set_real_ip_from.
            self.request(host="untrusted", headers=forged, status=403, banned=True)

    def test_missing_and_empty_networks(self):
        for networks in (None, ""):
            with self.subTest(networks=networks), self.running(networks=networks):
                self.request(address="192.0.2.5", status=403, banned=True)
                self.request(ua=BROWSER, address="192.0.2.5")
                self.request(host="disabled")

    def test_missing_empty_and_changed_bot_expression(self):
        for bots in (None, ""):
            with self.subTest(bots=bots), self.running(bots=bots) as rendered:
                self.assertNotIn("$bot_protection_block", rendered)
                self.request(address="203.0.113.5")
                self.request(host="direct")
        with self.running(bots=r"Custom\sBot,^Scanner/[0-9.]+$"):
            self.request(address="203.0.113.5")
            for ua in ("custom Bot", "Scanner/1.2"):
                self.request(ua, address="203.0.113.5", status=403, banned=True)
                self.request(ua, address="192.0.2.5")
            self.request("OtherScanner/1.2", address="203.0.113.5")
        # Escaping a quoted map key must preserve configured regex characters.
        with self.running(bots='Quoted"Bot,Space Bot'):
            self.request('Quoted"Bot', address="203.0.113.5", status=403, banned=True)
            self.request("Space Bot", address="203.0.113.5", status=403, banned=True)


if __name__ == "__main__":
    unittest.main()
