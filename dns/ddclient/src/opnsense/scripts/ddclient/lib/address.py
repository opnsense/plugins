"""
    Copyright (c) 2022-2025 Ad Schellevis <ad@opnsense.org>
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
import xml.etree.ElementTree as ET
import dns.resolver
import dns.rdataclass
from urllib.parse import urlparse

checkip_service_list = {
  'akamai': '%s://whatismyip.akamai.com',
  'akamai-ipv4': '%s://ipv4.whatismyip.akamai.com',
  'akamai-ipv6': '%s://ipv6.whatismyip.akamai.com',
  'cloudflare': '%s://one.one.one.one/cdn-cgi/trace',
  'cloudflare-ipv4': '%s://1.1.1.1/cdn-cgi/trace',
  'cloudflare-ipv6': '%s://[2606:4700:4700::1111]/cdn-cgi/trace',
  'dynu-ipv4': '%s://ipcheck.dynu.com/',
  'dynu-ipv6': '%s://ipcheckv6.dynu.com/',
  'freedns': '%s://freedns.afraid.org/dynamic/check.php',
  'he': '%s://checkip.dns.he.net/',
  'icanhazip': '%s://icanhazip.com/',
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

checkip_dns_list = {
   'cloudflare-dns': {
       'nameservers': ['1.1.1.1','1.0.0.1'],
       'resolve_params': {
           'qname': 'whoami.cloudflare',
           'rdtype': 'TXT',
           'rdclass': dns.rdataclass.from_text('CH')
       }
   }
}

def registered_services():
    return list(checkip_service_list.keys()) + list(checkip_dns_list.keys())

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


def transform_ip(ip, ipv6host=None):
    """ Changes ipv6 addresses if interface identifier is given
        :param ip: ip address
        :param ipv6host: 64 bit interface identifier
        :return ipaddress.IPv4Address|ipaddress.IPv6Address
        :raises ValueError: If the input can not be converted to an IPaddress
    """
    if ipv6host and ip.find(':') > 0:
        # extract 64 bit long prefix and add ipv6host [64]bits
        return ipaddress.ip_address(
            ipaddress.ip_network("%s/64" % ip, strict=False).network_address.exploded[0:19] +
            ipaddress.ip_address(ipv6host).exploded[19:]
        )
    else:
        return ipaddress.ip_address(ip)


def find_virtual_ip(uuid, address_family=4, config_filename='/conf/config.xml'):
    """ Find the current address of a CARP virtual IP by UUID and address family.
        :param uuid: virtual IP UUID
        :param address_family: IP version (4 or 6)
        :param config_filename: OPNsense configuration file
        :return: str
    """
    try:
        config = ET.parse(config_filename).getroot()
    except (OSError, ET.ParseError):
        return ""

    for virtual_ip in config.findall('./virtualip/vip'):
        if virtual_ip.get('uuid') == uuid and virtual_ip.findtext('mode') == 'carp':
            try:
                address = ipaddress.ip_address(virtual_ip.findtext('subnet', ''))
                return str(address) if address.version == address_family else ""
            except ValueError:
                return ""
    return ""


def checkip(service, proto='https', timeout='10', interface=None, dynipv6host=None):
    """ find ip address using external web services defined in checkip_service_list
        or dns services defined in checkip_dns_list
        :param proto: protocol
        :param timeout: timeout in seconds
        :param interface: bind to interface
        :param dynipv6host: optional partial ipv6 address
        :return: str
    """
    if interface is not None and interface.startswith('vip:') and service not in ['if', 'if6']:
        return ""
    if service.lstrip('web_') in checkip_service_list:
        # configuration name, strip web_ part
        service = service.lstrip('web_')
        params = ['/usr/local/bin/curl', '-m', timeout]
        if interface is not None:
            params.append("--interface")
            params.append(interface)
        url = checkip_service_list[service] % proto
        params.append(url)
        extracted_address = extract_address(urlparse(url).hostname,
            subprocess.run(params, capture_output=True, text=True).stdout)
        try:
            return str(transform_ip(extracted_address, dynipv6host))
        except ValueError:
            # invalid address
            return ""
    elif service in ['if', 'if6'] and interface is not None:
        if interface.startswith('vip:'):
            source = interface.split(':', 2)
            address_family = 6 if service == 'if6' else 4
            if len(source) != 3 or source[1] != str(address_family) or not source[2]:
                return ""
            return find_virtual_ip(source[2], address_family)
        # return first non private IPv[4|6] interface address
        ifcfg = subprocess.run(['/sbin/ifconfig', interface], capture_output=True, text=True).stdout
        for line in ifcfg.split('\n'):
            if line.startswith('\tinet'):
                parts = line.split()
                if (parts[0] == 'inet' and service == 'if') or (parts[0] == 'inet6' and service == 'if6'):
                    try:
                        address = transform_ip(parts[1], dynipv6host)
                        if address.is_global:
                            return str(address)
                    except ValueError:
                        continue
    elif service.lstrip('dns_') in checkip_dns_list:
        svc_info = checkip_dns_list[service.lstrip('dns_')]
        resolve_params = svc_info['resolve_params']
        dns_resolver = dns.resolver.Resolver()
        dns_resolver.nameservers = svc_info['nameservers']
        try:
            dns_response = dns_resolver.resolve(**resolve_params)
            return dns_response[0].to_text().strip('"')
        except:
            return ""
    else:
        return ""
