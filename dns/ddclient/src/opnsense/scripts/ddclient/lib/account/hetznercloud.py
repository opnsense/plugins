"""
Copyright (c) 2026 Juergen Wilbois
SPDX-License-Identifier: BSD-2-Clause
"""

import syslog
import requests

from . import BaseAccount


class HetznerCloudDNS(BaseAccount):
    _priority = 65535
    _services = {"hetznercloud": "Hetzner Cloud DNS"}

    API_BASE = "https://api.hetzner.cloud/v1"

    @staticmethod
    def known_services():
        return HetznerCloudDNS._services

    @staticmethod
    def match(account):
        return account.get("service") in HetznerCloudDNS._services

    def _headers(self):
        token = (self.settings.get("password") or "").strip()
        return {
            "User-Agent": "OPNsense-ddclient-native",
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
        }

    def _zone_id(self, zone_name: str) -> str | None:
        # Hetzner Community beschreibt: /zones?name=<zone> → zone.id :contentReference[oaicite:4]{index=4}
        url = f"{self.API_BASE}/zones?name={zone_name}"
        r = requests.get(url, headers=self._headers(), timeout=20)
        if not (200 <= r.status_code < 300):
            syslog.syslog(syslog.LOG_ERR, f"HetznerCloud: zones lookup failed [{r.status_code}] {r.text}")
            return None
        zones = (r.json() or {}).get("zones") or []
        for z in zones:
            if z.get("name") == zone_name and z.get("id"):
                return z["id"]
        return None

    @staticmethod
    def _split_hostnames(hostnames: str) -> list[str]:
        # erlaubt: "a.example.com, b.example.com" oder "a.example.com b.example.com"
        raw = hostnames.replace(",", " ").split()
        return [h.strip().rstrip(".") for h in raw if h.strip()]

    @staticmethod
    def _rr_name_from_fqdn(fqdn: str, zone: str) -> str:
        fqdn = fqdn.rstrip(".")
        zone = zone.rstrip(".")
        if fqdn == zone:
            return "@"
        if fqdn.endswith("." + zone):
            return fqdn[: -(len(zone) + 1)]
        # falls jemand "home" statt "home.example.com" einträgt:
        if "." not in fqdn:
            return fqdn
        return fqdn

    def _set_rrset(self, zone_id: str, rr_name: str, rr_type: str, value: str) -> bool:
        # RRset ersetzen via set_records :contentReference[oaicite:5]{index=5}
        url = f"{self.API_BASE}/zones/{zone_id}/rrsets/{rr_name}/{rr_type}/actions/set_records"
        body = {"records": [{"value": value}]}
        r = requests.post(url, headers=self._headers(), json=body, timeout=20)
        if 200 <= r.status_code < 300:
            return True
        syslog.syslog(syslog.LOG_ERR, f"HetznerCloud: set_records failed [{r.status_code}] {r.text}")
        return False

    def execute(self):
        if not super().execute():
            return False

        zone_name = (self.settings.get("zone") or "").strip().rstrip(".")
        hostnames = (self.settings.get("hostnames") or "").strip()
        if not zone_name or not hostnames:
            syslog.syslog(syslog.LOG_ERR, f"Account {self.description} missing zone/hostnames")
            return False

        zone_id = self._zone_id(zone_name)
        if not zone_id:
            syslog.syslog(syslog.LOG_ERR, f"Account {self.description} cannot resolve zone '{zone_name}'")
            return False

        addr = str(self.current_address)
        rr_type = "AAAA" if ":" in addr else "A"

        ok_all = True
        for fqdn in self._split_hostnames(hostnames):
            rr_name = self._rr_name_from_fqdn(fqdn, zone_name)
            ok = self._set_rrset(zone_id, rr_name, rr_type, addr)
            if ok:
                syslog.syslog(syslog.LOG_NOTICE, f"HetznerCloud: {rr_type} {fqdn} -> {addr}")
            ok_all = ok_all and ok

        if ok_all:
            self.update_state(address=self.current_address)
            return True
        return False
