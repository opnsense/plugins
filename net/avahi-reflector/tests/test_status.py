"""
Copyright (C) 2026 cayossarian (Bill Flood)
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
"""
import json
import os
from datetime import date

import pytest
import status

TODAY = date(2026, 9, 24)
SLOT = "No slot available for legacy unicast reflection"


def rfc5424(stamp, message, program="avahi-daemon"):
    return f'<28>1 {stamp} OPNsense.example.arpa {program} 86657 - [meta sequenceId="1"] {message}\n'


TODAY_1 = rfc5424("2026-09-24T00:05:00-07:00", SLOT)
TODAY_2 = rfc5424("2026-09-24T10:30:45-07:00", SLOT)
YESTERDAY = rfc5424("2026-09-23T23:59:59-07:00", SLOT)
UNRELATED = rfc5424("2026-09-24T11:00:00-07:00", "Server startup complete. Host name is x.local.")


# --- slot error summary ----------------------------------------------------

def test_todays_rfc5424_lines_count_as_slot_errors():
    s = status.summarize_slot_errors([TODAY_1, UNRELATED, TODAY_2], TODAY)
    assert s == {
        "status": "degraded",
        "slot_errors_today": 2,
        "last_slot_error": "2026-09-24T10:30:45-07:00",
    }


def test_yesterdays_lines_do_not_count_but_are_remembered():
    s = status.summarize_slot_errors([YESTERDAY, UNRELATED], TODAY)
    assert s == {
        "status": "warning",
        "slot_errors_today": 0,
        "last_slot_error": "2026-09-23T23:59:59-07:00",
    }


def test_no_slot_lines_is_healthy():
    s = status.summarize_slot_errors([UNRELATED], TODAY)
    assert s == {"status": "healthy", "slot_errors_today": 0, "last_slot_error": None}


@pytest.mark.parametrize("line", [
    SLOT + "\n",                                                   # no header at all
    "Sep 24 10:30:45 host avahi-daemon[1]: " + SLOT + "\n",        # BSD format
    "<28>2 2026-09-24T10:30:45-07:00 host avahi-daemon 1 - - " + SLOT + "\n",  # wrong version
    "<28>1 \n",
    "\x00\x01\x02 " + SLOT,
    "",
])
def test_garbage_lines_are_ignored(line):
    s = status.summarize_slot_errors([line], TODAY)
    assert s["slot_errors_today"] == 0
    assert s["last_slot_error"] is None


def test_read_slot_summary_survives_undecodable_bytes(tmp_path):
    log = tmp_path / "latest.log"
    log.write_bytes(b"\xff\xfe\x80garbage\n" + TODAY_1.encode() + b"\xc3\x28 trailing\n")
    s = status.read_slot_summary(str(log), TODAY)
    assert s["slot_errors_today"] == 1


def test_read_slot_summary_missing_file_is_healthy(tmp_path):
    s = status.read_slot_summary(str(tmp_path / "absent.log"), TODAY)
    assert s == {"status": "healthy", "slot_errors_today": 0, "last_slot_error": None}


def test_read_slot_summary_unreadable_path_is_healthy(tmp_path):
    # a directory raises IsADirectoryError (an OSError), not FileNotFoundError
    s = status.read_slot_summary(str(tmp_path), TODAY)
    assert s["slot_errors_today"] == 0


# --- log path resolution ---------------------------------------------------

def test_resolve_log_prefers_latest_symlink(tmp_path):
    (tmp_path / "avahi_20260923.log").write_text("a")
    (tmp_path / "avahi_20260924.log").write_text("b")
    os.symlink(tmp_path / "avahi_20260923.log", tmp_path / "latest.log")
    assert status.resolve_log(str(tmp_path / "latest.log")) == str(tmp_path / "latest.log")


def test_resolve_log_falls_back_to_newest_dated_file(tmp_path):
    # syslog log_archive creates latest.log only on its hourly run
    (tmp_path / "avahi_20260923.log").write_text("a")
    (tmp_path / "avahi_20260924.log").write_text("b")
    assert status.resolve_log(str(tmp_path / "latest.log")) == str(tmp_path / "avahi_20260924.log")


def test_resolve_log_without_any_file_returns_the_path(tmp_path):
    assert status.resolve_log(str(tmp_path / "latest.log")) == str(tmp_path / "latest.log")


# --- UDP 5353 conflict detection -------------------------------------------

SOCKSTAT_JSON_AVAHI = json.dumps({"__version": "1", "sockstat": {"socket": [
    {"user": "avahi", "command": "avahi-daemon", "pid": 86657, "fd": 14, "proto": "udp4",
     "local": {"address": "*", "port": 5353}, "foreign": {"address": "*", "port": 0}},
    {"user": "avahi", "command": "avahi-daemon", "pid": 86657, "fd": 15, "proto": "udp6",
     "local": {"address": "*", "port": 5353}, "foreign": {"address": "*", "port": 0}},
]}})

SOCKSTAT_JSON_CONFLICT = json.dumps({"__version": "1", "sockstat": {"socket": [
    {"user": "avahi", "command": "avahi-daemon", "pid": 86657, "fd": 14, "proto": "udp4",
     "local": {"address": "*", "port": 5353}, "foreign": {"address": "*", "port": 0}},
    {"user": "root", "command": "mdns-repeater", "pid": 4242, "fd": 3, "proto": "udp4",
     "local": {"address": "*", "port": 5353}, "foreign": {"address": "*", "port": 0}},
    {"user": "root", "command": "udpbroadcastrelay", "pid": 4343, "fd": 4, "proto": "udp4",
     "local": {"address": "192.0.2.1", "port": 5353}, "foreign": {"address": "*", "port": 0}},
    {"user": "root", "command": "mdns-repeater", "pid": 4242, "fd": 4, "proto": "udp4",
     "local": {"address": "*", "port": 5353}, "foreign": {"address": "*", "port": 0}},
]}})

SOCKSTAT_TEXT_AVAHI = """\
USER  COMMAND      PID FD PROTO LOCAL ADDRESS         FOREIGN ADDRESS
avahi avahi-daem 86657 14 udp4  *:5353                *:*
avahi avahi-daem 86657 15 udp6  *:5353                *:*
"""

SOCKSTAT_TEXT_CONFLICT = """\
USER  COMMAND      PID FD PROTO LOCAL ADDRESS         FOREIGN ADDRESS
avahi avahi-daem 86657 14 udp4  *:5353                *:*
root  mdns-repea  4242  3 udp4  *:5353                *:*
root  udpbroadca  4343  4 udp4  192.0.2.1:5353        *:*
"""


def test_sockstat_json_avahi_only_is_no_conflict():
    assert status.parse_sockstat_json(SOCKSTAT_JSON_AVAHI) == []


def test_sockstat_json_names_every_conflicting_process_once():
    assert status.parse_sockstat_json(SOCKSTAT_JSON_CONFLICT) == ["mdns-repeater", "udpbroadcastrelay"]


def test_sockstat_json_nothing_listening_is_no_conflict():
    assert status.parse_sockstat_json(json.dumps({"__version": "1", "sockstat": {"socket": []}})) == []
    assert status.parse_sockstat_json(json.dumps({"__version": "1", "sockstat": {}})) == []


def test_sockstat_json_rejects_non_json():
    with pytest.raises(ValueError):
        status.parse_sockstat_json(SOCKSTAT_TEXT_AVAHI)


def test_sockstat_text_avahi_only_is_no_conflict():
    assert status.parse_sockstat_text(SOCKSTAT_TEXT_AVAHI) == []


def test_sockstat_text_names_conflicts_as_sockstat_prints_them():
    # plain sockstat truncates COMMAND to 10 characters
    assert status.parse_sockstat_text(SOCKSTAT_TEXT_CONFLICT) == ["mdns-repea", "udpbroadca"]


def test_sockstat_text_header_only_is_no_conflict():
    assert status.parse_sockstat_text(SOCKSTAT_TEXT_AVAHI.splitlines()[0] + "\n") == []
    assert status.parse_sockstat_text("") == []


# --- avahi-daemon.conf parsing ---------------------------------------------

def test_parse_conf_reads_rendered_settings():
    conf = status.parse_conf([
        "[server]\n", "domain-name=local\n", "use-ipv4=yes\n", "use-ipv6=yes\n",
        "allow-interfaces=vlan0.10,vlan0.20\n", "enable-dbus=no\n",
        "[reflector]\n", "enable-reflector=yes\n", "reflect-ipv=no\n",
        "reflect-filters=_ipp._tcp,_hap._tcp\n",
    ])
    assert conf == {
        "configured": True,
        "domain": "local",
        "interfaces": "vlan0.10,vlan0.20",
        "reflector_enabled": True,
        "use_ipv4": True,
        "use_ipv6": True,
        "reflect_ipv": False,
        "reflect_filters": "_ipp._tcp,_hap._tcp",
    }


def test_parse_conf_empty_file_is_not_configured():
    assert status.parse_conf([])["configured"] is False
    assert status.parse_conf(["\n", "# comment\n"])["configured"] is False


# --- main always prints one JSON object --------------------------------------

@pytest.fixture
def paths(tmp_path, monkeypatch):
    monkeypatch.setattr(status, "PID_FILE", str(tmp_path / "pid"))
    monkeypatch.setattr(status, "CONF_FILE", str(tmp_path / "avahi-daemon.conf"))
    monkeypatch.setattr(status, "LOG_FILE", str(tmp_path / "log" / "latest.log"))
    monkeypatch.setattr(status, "mdns_conflicts", lambda: [])
    return tmp_path


def _one_json_object(capsys):
    out = capsys.readouterr().out
    assert out.count("\n") == 1
    data = json.loads(out)
    assert isinstance(data, dict)
    return data


def test_main_with_nothing_on_disk_prints_one_object(paths, capsys):
    status.main()
    data = _one_json_object(capsys)
    assert data["running"] is False
    assert data["configured"] is False
    assert data["conflicts"] == []
    assert data["health"]["status"] == "healthy"


def test_main_with_garbage_everywhere_prints_one_object(paths, capsys):
    (paths / "pid").write_bytes(b"\xff not a pid")
    (paths / "avahi-daemon.conf").write_bytes(b"\xff\xfe[server]\nallow-interfaces=\xc3\x28\n")
    (paths / "log").mkdir()
    (paths / "log" / "latest.log").write_bytes(b"\x00\xff" + TODAY_1.encode())
    status.main()
    data = _one_json_object(capsys)
    assert data["running"] is False
    assert data["configured"] is True


def test_main_reports_an_internal_failure_as_one_error_object(paths, monkeypatch, capsys):
    def boom():
        raise RuntimeError("unexpected")
    monkeypatch.setattr(status, "collect", boom)
    status.main()
    data = _one_json_object(capsys)
    assert data["status"] == "error"
    assert "unexpected" in data["message"]
