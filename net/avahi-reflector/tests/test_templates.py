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

Renders the plugin templates the way configd does (jinja2, trim_blocks, the
do and loopcontrols extensions) against synthetic config data. Needs jinja2:
uvx --python 3.13 --with jinja2 pytest tests
"""
import pytest
from conftest import TEMPLATES

jinja2 = pytest.importorskip("jinja2")

# core's templates/OPNsense/Macros/interface.macro, which ships with OPNsense
INTERFACE_MACRO = """{% macro physical_interface(name) -%}
{% if helpers.exists('interfaces.'+name+'.if')
%}{{
      helpers.getNodeByTag('interfaces.'+name+'.if')
}}{%
else %}{{name}}{%
endif
%}{%- endmacro %}"""


class Helpers:
    def __init__(self, data):
        self._data = data

    def getNodeByTag(self, tag):
        node = self._data
        for item in tag.split("."):
            if isinstance(node, dict) and item in node:
                node = node[item]
            else:
                return None
        return node

    def exists(self, tag):
        return self.getNodeByTag(tag) is not None


def render(name, avahi):
    env = jinja2.Environment(
        loader=jinja2.ChoiceLoader([
            jinja2.DictLoader({"OPNsense/Macros/interface.macro": INTERFACE_MACRO}),
            jinja2.FileSystemLoader(TEMPLATES),
        ]),
        trim_blocks=True,
        extensions=["jinja2.ext.do", "jinja2.ext.loopcontrols"],
    )
    data = {
        "interfaces": {"lan": {"if": "igb1"}, "opt1": {"if": "vlan0.10"}, "wan": {"if": "igb0"}},
        # configd drops empty elements, so an unset field is simply absent
        "OPNsense": {"AvahiReflector": {k: v for k, v in avahi.items() if v is not None}},
    }
    # configd links helpers as an environment global, which imported macros see
    env.globals["helpers"] = Helpers(data)
    return env.get_template("OPNsense/AvahiReflector/" + name).render(**data)


ENABLED = {
    "enabled": "1",
    "interfaces": "lan,opt1",
    "domain_name": "local",
    "use_ipv4": "1",
    "use_ipv6": "1",
    "enable_reflector": "1",
    "reflect_ipv": "0",
    "reflect_filters": "_ipp._tcp,_hap._tcp",
}


def settings(conf):
    """{section: {key: value}} of a rendered avahi-daemon.conf."""
    out, section = {}, None
    for line in conf.splitlines():
        line = line.strip()
        if line.startswith("[") and line.endswith("]"):
            section = out.setdefault(line[1:-1], {})
        elif "=" in line:
            key, _, val = line.partition("=")
            assert section is not None, line
            assert key not in section, "duplicate " + key
            section[key] = val
    return out


def test_enabled_renders_physical_allow_interfaces():
    s = settings(render("avahidaemon.conf", ENABLED))
    assert s["server"]["allow-interfaces"] == "igb1,vlan0.10"


def test_enabled_disables_dbus_publishing_and_wide_area():
    s = settings(render("avahidaemon.conf", ENABLED))
    assert s["server"]["enable-dbus"] == "no"
    assert s["publish"]["disable-publishing"] == "yes"
    assert s["wide-area"]["enable-wide-area"] == "no"


def test_enabled_renders_reflector_settings():
    s = settings(render("avahidaemon.conf", ENABLED))
    assert s["server"]["use-ipv4"] == "yes"
    assert s["server"]["use-ipv6"] == "yes"
    assert s["reflector"] == {
        "enable-reflector": "yes",
        "reflect-ipv": "no",
        "reflect-filters": "_ipp._tcp,_hap._tcp",
    }


def test_empty_filters_are_not_rendered():
    s = settings(render("avahidaemon.conf", dict(ENABLED, reflect_filters=None)))
    assert "reflect-filters" not in s["reflector"]


def test_rc_enabled_with_interfaces():
    assert render("rc.conf.d", ENABLED).strip() == 'avahi_daemon_enable="YES"'


@pytest.mark.parametrize("avahi", [
    dict(ENABLED, enabled="0"),
    dict(ENABLED, enabled=None),
    dict(ENABLED, interfaces=None),        # fail closed: never bind every interface
    dict(ENABLED, interfaces=","),
])
def test_disabled_or_no_interfaces_renders_nothing_and_rc_off(avahi):
    assert render("avahidaemon.conf", avahi).strip() == ""
    assert render("rc.conf.d", avahi).strip() == 'avahi_daemon_enable="NO"'


@pytest.mark.parametrize("avahi", [
    ENABLED,
    dict(ENABLED, interfaces="wan"),
    dict(ENABLED, interfaces="optX"),
    dict(ENABLED, interfaces=None),
    dict(ENABLED, enabled="0"),
])
def test_server_section_never_lacks_allow_interfaces(avahi):
    conf = render("avahidaemon.conf", avahi)
    rc = render("rc.conf.d", avahi)
    s = settings(conf)
    if "YES" in rc:
        assert s["server"]["allow-interfaces"] != ""
    if s:
        assert s["server"]["allow-interfaces"] != ""
        assert s["server"]["enable-dbus"] == "no"
        assert s["publish"]["disable-publishing"] == "yes"
        assert s["wide-area"]["enable-wide-area"] == "no"
