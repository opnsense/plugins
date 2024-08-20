"""
    Copyright (c) 2022-2023 Ad Schellevis <ad@opnsense.org>
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
import subprocess
import re
import ipaddress
from urllib.parse import urlparse

checkip_service_list = {
  'cloudflare': '%s://one.one.one.one/cdn-cgi/trace',
  'cloudflare-ipv4': '%s://1.1.1.1/cdn-cgi/trace',
  'cloudflare-ipv6': '%s://[2606:4700:4700::1111]/cdn-cgi/trace',
  'dyndns': '%s://checkip.dyndns.org/',
  'freedns': '%s://freedns.afraid.org/dynamic/check.php',
  'he': '%s://checkip.dns.he.net/',
  'icanhazip': '%s://icanhazip.com/',
  'ip4only.me': '%s://ip4only.me/api/',
  'ip6only.me': '%s://ip6only.me/api/',
  'ipify-ipv4': '%s://api.ipify.org/',
  'ipify-ipv6': '%s://api6.ipify.org/',
  'loopia': '%s://dns.loopia.se/checkip/checkip.php',
  'myonlineportal': '%s://myonlineportal.net/checkip',
  'noip-ipv4': '%s://ip1.dynupdate.no-ip.com/',
  'noip-ipv6': '%s://ip1.dynupdate6.no-ip.com/',
  'nsupdate.info-ipv4': '%s://ipv4.nsupdate.info/myip',
  'nsupdate.info-ipv6': '%s://ipv6.nsupdate.info/myip',
  'zoneedit': '%s://dynamic.zoneedit.com/checkip.html'
}


def extract_address(host, txt):
    """ Extract first IPv4 or IPv6 address from provided string
        :param txt: text blob
        :return: str
    """
    for regexp in [r'(?:\d{1,3}\.){3}\d{1,3}', r'([a-f0-9:]+:+)+[a-f0-9]+']:
        matches = re.finditer(regexp, txt)
        for match in matches:
            if match.group() != host:
                try:
                    ipaddress.ip_address(match.group())
                    return match.group()
                except ValueError:
                    pass
    return ""


def checkip(service, proto='https', timeout='10', interface=None):
    """ find ip address using external services defined in checkip_service_list
        :param proto: protocol
        :param timeout: timeout in seconds
        :param interface: bind to interface
        :return: str
    """
    if service.startswith('web_'):
        # configuration name, strip web_ part
        service = service[4:]
    if service in checkip_service_list:
        params = ['/usr/local/bin/curl', '-m', timeout]
        if interface is not None:
            params.append("--interface")
            params.append(interface)
        url = checkip_service_list[service] % proto
        params.append(url)
        return extract_address(urlparse(url).hostname,
            subprocess.run(params, capture_output=True, text=True).stdout)
    elif service in ['if', 'if6'] and interface is not None:
        # return first non private IPv[4|6] interface address
        ifcfg = subprocess.run(['/sbin/ifconfig', interface], capture_output=True, text=True).stdout
        for line in ifcfg.split('\n'):
            if line.startswith('\tinet'):
                parts = line.split()
                if (parts[0] == 'inet' and service == 'if') or (parts[0] == 'inet6' and service == 'if6'):
                    try:
                        address = ipaddress.ip_address(parts[1])
                        if address.is_global:
                            return str(address)
                    except ValueError:
                        continue
    else:
        return ""
