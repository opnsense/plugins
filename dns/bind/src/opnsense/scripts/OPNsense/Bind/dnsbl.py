#!/usr/bin/env python3


"""
    Copyright (c) 2026 Bryan Wiegand <inbox@kw-ventures.com>
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

    Usage:
      dnsbl.py [codes]      — fetch blocklists (comma-separated shortcodes)
"""

import re
import sys
import os
import syslog
import urllib.request
import tempfile
import shutil

DESTDIR = "/usr/local/etc/namedb"
UNBOUND_TPL = "/usr/local/opnsense/service/templates/OPNsense/Unbound/core/blocklists.conf"
FETCH_TIMEOUT = 20
DOMAIN_RE = re.compile(r'(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$', re.IGNORECASE)


def load_url_map():
    """Parse Unbound's blocklists.conf template to build shortcode -> URL map.
    The template contains a predefined Jinja2 dict: "shortcode": "URL",
    Returns dict, empty if the template is unreadable.
    """
    url_map = {}
    if not os.path.isfile(UNBOUND_TPL):
        syslog.syslog(syslog.LOG_ERR, "dnsbl: %s not found" % UNBOUND_TPL)
        return url_map

    with open(UNBOUND_TPL, "r") as f:
        for line in f:
            m = re.match(r'\s*"([a-z][a-z0-9]*)":\s*"([^"]*)"', line)
            if m:
                url = m.group(2).replace('&amp;', '&')
                url_map[m.group(1)] = url
    syslog.syslog(syslog.LOG_NOTICE, "dnsbl: loaded %d URLs from %s" % (len(url_map), UNBOUND_TPL))
    return url_map


def normalize_domains(raw_path):
    """Extract domain names from a raw blocklist file.
    Handles both hosts-file format (IP domain) and plain domain-list format.
    Returns a set of cleaned domain strings.
    """
    domains = set()
    host_re = re.compile(
        r'^\s*(?:0\.0\.0\.0|127\.0\.0\.1|255\.255\.255\.255|'
        r'::1|fe80::|ff00::|ff02::)'
        r'\s+(\S+)'
    )
    ip_re = re.compile(r'^\s*\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\s+(.*)')
    ipv6_re = re.compile(r'^\s*[0-9a-fA-F:]+\s+(.*)')

    with open(raw_path, "r") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue

            domain = None
            m = host_re.match(line)
            if m:
                domain = m.group(1)
            else:
                m = ip_re.match(line)
                if m:
                    domain = m.group(1)
                else:
                    m = ipv6_re.match(line)
                    if m:
                        domain = m.group(1)
                    else:
                        domain = line.split()[0]

            if not domain:
                continue

            domain = domain.split("#")[0].strip()
            # Unbound's Hagezi wildcard sources use Adblock-style owners.
            if domain.startswith('||') and domain.endswith('^'):
                domain = domain[2:-1]
            domain = domain.rstrip(".")
            if domain.startswith("."):
                domain = domain[1:]

            if not domain or domain == "localhost":
                continue
            if not DOMAIN_RE.fullmatch(domain):
                continue

            domains.add(domain.lower())

    return domains


def write_rpz(domains, output_path):
    """Write domains in RPZ CNAME format (the .inc file included by blacklist.db)."""
    tmp = output_path + ".tmp"
    with open(tmp, "w") as f:
        for d in sorted(domains):
            f.write("%s CNAME .\n*." % d)
            f.write("%s CNAME .\n" % d)
    shutil.move(tmp, output_path)
    try:
        import pwd
        import grp
        uid = pwd.getpwnam("bind").pw_uid
        gid = grp.getgrnam("bind").gr_gid
        os.chown(output_path, uid, gid)
    except (ImportError, KeyError):
        pass


def main():
    if len(sys.argv) > 1:
        dnsbl_codes = sys.argv[1]
    else:
        dnsbl_codes = None
        rc_conf = "/etc/rc.conf.d/named"
        if os.path.isfile(rc_conf):
            with open(rc_conf) as f:
                for line in f:
                    m = re.match(r'^named_dnsbl="(.+)"', line)
                    if m:
                        dnsbl_codes = m.group(1)
                        break

    if not dnsbl_codes:
        syslog.syslog(syslog.LOG_NOTICE, "dnsbl: no lists configured, nothing to do")
        return

    codes = [c.strip() for c in dnsbl_codes.split(",") if c.strip()]
    url_map = load_url_map()

    if not url_map:
        syslog.syslog(syslog.LOG_ERR, "dnsbl: no URL map available, aborting")
        sys.exit(1)

    workdir = tempfile.mkdtemp(prefix="binddnsbl.")

    try:
        all_domains = set()
        successful_fetches = 0
        for code in codes:
            url = url_map.get(code)
            if not url:
                syslog.syslog(syslog.LOG_ERR,
                              "dnsbl: unknown shortcode '%s' - skipping" % code)
                continue

            syslog.syslog(syslog.LOG_NOTICE,
                          "dnsbl: fetching '%s' from %s" % (code, url))

            try:
                raw_path = os.path.join(workdir, "%s-raw" % code)
                request = urllib.request.Request(url, headers={'User-Agent': 'OPNsense-BIND-DNSBL'})
                with urllib.request.urlopen(request, timeout=FETCH_TIMEOUT) as response, open(raw_path, 'wb') as output:
                    shutil.copyfileobj(response, output)
                domains = normalize_domains(raw_path)
                successful_fetches += 1
                syslog.syslog(syslog.LOG_NOTICE,
                              "dnsbl: '%s' got %d domains" % (code, len(domains)))
                all_domains.update(domains)
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR,
                              "dnsbl: failed to fetch '%s' (%s) - %s" % (code, url, e))

        inc_path = os.path.join(DESTDIR, "dnsbl.inc")
        if all_domains or not os.path.exists(inc_path):
            syslog.syslog(syslog.LOG_NOTICE,
                          "dnsbl: writing %d total domains to %s" % (len(all_domains), inc_path))
            write_rpz(all_domains, inc_path)
        else:
            syslog.syslog(syslog.LOG_WARNING, "dnsbl: no domains fetched, dnsbl.inc unchanged")
        if successful_fetches == 0:
            syslog.syslog(syslog.LOG_ERR, "dnsbl: all selected downloads failed")
            sys.exit(1)
    finally:
        shutil.rmtree(workdir, ignore_errors=True)


if __name__ == "__main__":
    main()
