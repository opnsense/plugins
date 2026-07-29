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
import json
import syslog
import urllib.request
import tempfile
import shutil
import subprocess
import time
from pathlib import Path

DESTDIR = "/usr/local/etc/namedb"
UNBOUND_TPL = "/usr/local/opnsense/service/templates/OPNsense/Unbound/core/blocklists.conf"
FETCH_TIMEOUT = 20
STATUS_PATH = "/var/run/bind/dnsbl-status.json"
DOMAIN_RE = re.compile(r'(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$', re.IGNORECASE)


def status_data(stage, domains=0, inc_bytes=0, message='', current_list='',
                completed_lists=0, total_lists=0, updated_at=None):
    """Build the persistent DNSBL operation status payload."""
    return {
        'stage': stage,
        'domains': domains,
        'rpz_records': domains * 2,
        'inc_bytes': inc_bytes,
        'estimated_peak_kb': domains,
        'message': message,
        'current_list': current_list,
        'completed_lists': completed_lists,
        'total_lists': total_lists,
        'updated_at': time.time() if updated_at is None else updated_at,
    }


def write_status(path, stage, domains=0, inc_bytes=0, message='', current_list='',
                 completed_lists=0, total_lists=0, updated_at=None, **_unused):
    """Persist DNSBL refresh facts for the guarded startup workflow."""
    path = str(path)
    status = status_data(
        stage, domains, inc_bytes, message, current_list, completed_lists, total_lists, updated_at,
    )
    directory = os.path.dirname(path)
    if directory:
        os.makedirs(directory, exist_ok=True)
    tmp = path + '.tmp'
    with open(tmp, 'w') as status_file:
        json.dump(status, status_file)
    os.replace(tmp, path)
    return status


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


def normalized_domains(raw_path):
    """Yield normalized domains from a raw hosts or plain-domain blocklist."""
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

            match = host_re.match(line) or ip_re.match(line) or ipv6_re.match(line)
            domain = match.group(1) if match else line.split()[0]

            domain = domain.split("#")[0].strip()
            # Blocklists may use Adblock or wildcard owners for a domain.
            if domain.startswith('||') and domain.endswith('^'):
                domain = domain[2:-1]
            if domain.startswith('*.'):
                domain = domain[2:]
            domain = domain.rstrip(".")
            if domain.startswith("."):
                domain = domain[1:]

            if not domain or domain == "localhost" or not DOMAIN_RE.fullmatch(domain):
                continue

            yield domain.lower()



def write_normalized_domains(raw_path, output_path):
    """Stream normalized domains into a temporary sort input and return its count."""
    domains = 0
    with open(output_path, "w") as output:
        for domain in normalized_domains(raw_path):
            output.write(domain + "\n")
            domains += 1
    return domains


def write_rpz_from_sorted_domains(domains_path, output_path):
    """Write a sorted unique domain stream in RPZ CNAME format and return its count."""
    domains = 0
    with open(domains_path, "r") as source, open(output_path, "w") as output:
        for domain in source:
            domain = domain.rstrip("\n")
            if not domain:
                continue
            output.write("%s CNAME .\n*.%s CNAME .\n" % (domain, domain))
            domains += 1
    return domains


def set_bind_owner(path):
    """Assign the completed include file to the BIND service account when present."""
    try:
        import pwd
        import grp
        uid = pwd.getpwnam("bind").pw_uid
        gid = grp.getgrnam("bind").gr_gid
        os.chown(path, uid, gid)
    except (ImportError, KeyError):
        pass


def download_url(url, raw_path):
    """Download one source list to a local raw file."""
    request = urllib.request.Request(url, headers={'User-Agent': 'OPNsense-BIND-DNSBL'})
    with urllib.request.urlopen(request, timeout=FETCH_TIMEOUT) as response, open(raw_path, 'wb') as output:
        shutil.copyfileobj(response, output)


def compile_dnsbl(codes, destination, url_map, status_writer, fetch=None):
    """Build a DNSBL include with disk-backed deduplication and atomic promotion."""
    fetch = fetch or (lambda code, raw_path: download_url(url_map[code], raw_path))
    total_lists = len(codes)
    completed_lists = 0
    normalized_domains_count = 0
    successful_fetches = 0

    with tempfile.TemporaryDirectory(prefix="binddnsbl.") as workdir:
        fragments = []
        for list_index, code in enumerate(codes, start=1):
            status_writer(status_data(
                'fetching', normalized_domains_count, message='Downloading and normalizing DNSBL %s.' % code,
                current_list=code, completed_lists=list_index, total_lists=total_lists,
            ))
            if code not in url_map:
                syslog.syslog(syslog.LOG_ERR, "dnsbl: unknown shortcode '%s' - skipping" % code)
                continue

            syslog.syslog(syslog.LOG_NOTICE, "dnsbl: fetching '%s' from %s" % (code, url_map[code]))
            raw_path = Path(workdir) / ("%s-raw" % code)
            fragment_path = Path(workdir) / ("%s-domains" % code)
            try:
                fetch(code, raw_path)
                domains = write_normalized_domains(raw_path, fragment_path)
            except (OSError, UnicodeError, urllib.error.URLError) as error:
                syslog.syslog(syslog.LOG_ERR, "dnsbl: failed to fetch '%s' (%s) - %s" % (code, url_map[code], error))
                continue

            fragments.append(fragment_path)
            successful_fetches += 1
            completed_lists = list_index
            normalized_domains_count += domains
            syslog.syslog(syslog.LOG_NOTICE, "dnsbl: '%s' got %d domains" % (code, domains))

        if successful_fetches == 0:
            return None

        sorted_domains = os.path.join(workdir, "domains.sorted")
        staged_include = str(destination) + ".new"
        try:
            subprocess.run(["sort", "-u", "-o", sorted_domains, *fragments], check=True)
            domains = write_rpz_from_sorted_domains(sorted_domains, staged_include)
            set_bind_owner(staged_include)
            os.replace(staged_include, destination)
        except (OSError, subprocess.CalledProcessError):
            try:
                os.unlink(staged_include)
            except FileNotFoundError:
                pass
            return None

    status_writer(status_data(
        'fetched', domains, os.path.getsize(destination),
        'DNSBLs downloaded and ready for BIND startup.', completed_lists=completed_lists,
        total_lists=total_lists,
    ))
    return domains


def write_rpz(domains, output_path):
    """Write a set of domains in RPZ CNAME format for compatibility callers."""
    tmp = output_path + ".tmp"
    with open(tmp, "w") as f:
        for d in sorted(domains):
            f.write("%s CNAME .\n*.%s CNAME .\n" % (d, d))
    shutil.move(tmp, output_path)
    set_bind_owner(output_path)


def main():
    syslog.openlog(ident='named', logoption=0, facility=syslog.LOG_DAEMON)
    write_status(STATUS_PATH, 'fetching', message='Downloading and normalizing DNSBLs.')
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
        write_status(STATUS_PATH, 'idle', message='No DNSBLs are configured.')
        syslog.syslog(syslog.LOG_NOTICE, "dnsbl: no lists configured, nothing to do")
        return

    codes = [c.strip() for c in dnsbl_codes.split(",") if c.strip()]
    url_map = load_url_map()

    if not url_map:
        write_status(STATUS_PATH, 'failed', message='No DNSBL URL map is available.')
        syslog.syslog(syslog.LOG_ERR, "dnsbl: no URL map available, aborting")
        sys.exit(1)

    inc_path = os.path.join(DESTDIR, "dnsbl.inc")

    def persist_status(status):
        write_status(STATUS_PATH, **status)

    if compile_dnsbl(codes, inc_path, url_map, persist_status) is None:
        write_status(STATUS_PATH, 'failed', message='All selected DNSBL downloads failed.')
        syslog.syslog(syslog.LOG_ERR, "dnsbl: all selected DNSBL downloads or compilation failed")
        sys.exit(1)


if __name__ == "__main__":
    main()
