#!/usr/local/bin/python3
# Copyright (c) 2026 OpenAI
# SPDX-License-Identifier: BSD-2-Clause
"""Bounded initial readiness for an enabled, explicitly loopback apcupsd."""
import ipaddress
import json
from pathlib import Path
import socket
import struct
import subprocess
import sys
import time
import xml.etree.ElementTree as ET

CONFIG = Path('/conf/config.xml')
RC = Path('/usr/local/etc/rc.d')
TIMEOUT = 30
DRIVERS = ('usbhid', 'apcsmart', 'bcmxcpusb', 'blazerusb', 'blazerser', 'qx', 'riello', 'snmp')


class NotReady(Exception):
    pass


def selected(path=CONFIG):
    content = path.read_bytes()
    if b'<!DOCTYPE' in content or b'<!ENTITY' in content:
        raise NotReady('invalid_configuration')
    root = ET.fromstring(content)
    nut, apc = root.find('OPNsense/Nut'), root.find('OPNsense/apcupsd/general')
    if nut is None or apc is None:
        return None
    if nut.findtext('general/enable') != '1' or nut.findtext('general/mode', 'standalone') != 'standalone' or \
            nut.findtext('apcupsd/enable') != '1' or apc.findtext('Enabled') != '1':
        return None
    host = nut.findtext('apcupsd/hostname', 'localhost').strip('[]')
    if host not in ('localhost', '127.0.0.1', '::1'):
        return None
    if any(nut.findtext(driver + '/enable') == '1' for driver in DRIVERS):
        raise NotReady('multiple_local_drivers')
    port = int(nut.findtext('apcupsd/port') or '3551')
    if not 1 <= port <= 65535 or int(apc.findtext('NetserverPort', '3551')) != port or \
            apc.findtext('Netserver', '1') != '1':
        raise NotReady('apcupsd_nis_configuration_mismatch')
    return host, port, apc.findtext('NetserverAddress', '127.0.0.1')


def left(end):
    remaining = end - time.monotonic()
    if remaining <= 0:
        raise NotReady('readiness_timeout')
    return remaining


def endpoint(config, end):
    host, port, bind = config
    if host == 'localhost':
        code = ('import json,socket,sys; print(json.dumps(socket.getaddrinfo('
                'sys.argv[1],int(sys.argv[2]),type=socket.SOCK_STREAM)))')
        result = subprocess.run([sys.executable, '-c', code, host, str(port)],
                                capture_output=True, text=True, timeout=left(end))
        if result.returncode:
            raise NotReady('localhost_resolution_failed')
        rows = json.loads(result.stdout)
        if not rows or any(not ipaddress.ip_address(row[4][0]).is_loopback for row in rows):
            raise NotReady('localhost_resolution_not_loopback')
        family, _, _, _, target = rows[0]
        target = tuple(target)
    else:
        family = socket.AF_INET6 if ':' in host else socket.AF_INET
        target = (host, port, 0, 0) if family == socket.AF_INET6 else (host, port)
    address, listener = ipaddress.ip_address(target[0]), ipaddress.ip_address(bind)
    if address.version != listener.version or not (listener.is_unspecified or address == listener):
        raise NotReady('apcupsd_nis_bind_mismatch')
    return family, target


def exact(sock, count, end):
    result = bytearray()
    while len(result) < count:
        sock.settimeout(min(2, left(end)))
        chunk = sock.recv(count - len(result))
        if not chunk:
            raise NotReady('incomplete_nis_response')
        result.extend(chunk)
    return bytes(result)


def probe(target, end):
    family, address = target
    with socket.socket(family, socket.SOCK_STREAM) as sock:
        sock.settimeout(min(2, left(end)))
        sock.connect(address)
        sock.settimeout(min(2, left(end)))
        sock.sendall(struct.pack('!H', 6) + b'status')
        payload = bytearray()
        while True:
            size = struct.unpack('!H', exact(sock, 2, end))[0]
            if not size:
                break
            if len(payload) + size > 65536:
                raise NotReady('oversized_nis_response')
            payload.extend(exact(sock, size, end))
    fields = {}
    for line in payload.decode('utf-8', errors='replace').splitlines():
        if ':' in line:
            key, value = line.split(':', 1)
            fields[key.strip()] = value.strip()
    flags = set(fields.get('STATUS', '').split())
    if not {'APC', 'STATUS', 'END APC'}.issubset(fields) or not flags:
        raise NotReady('invalid_nis_status')
    if flags & {'COMMLOST', 'ERROR', 'NETWORK'}:
        raise NotReady('apcupsd_communication_lost')
    if not flags & {'ONLINE', 'ONBATT', 'LOWBATT', 'SHUTTING', 'SELFTEST'}:
        raise NotReady('invalid_nis_status')


def wait_ready(config):
    end = time.monotonic() + TIMEOUT
    target = endpoint(config, end)
    status = subprocess.run([str(RC / 'apcupsd'), 'status'], stdout=subprocess.DEVNULL,
                            stderr=subprocess.DEVNULL, timeout=left(end))
    if status.returncode == 1:
        start = subprocess.run([str(RC / 'apcupsd'), 'start'], stdout=subprocess.DEVNULL,
                               stderr=subprocess.DEVNULL, timeout=left(end))
        if start.returncode:
            raise NotReady('apcupsd_start_failed')
    elif status.returncode:
        raise NotReady('apcupsd_status_failed')
    while True:
        try:
            probe(target, end)
            return
        except (OSError, NotReady):
            time.sleep(min(1, left(end)))


def start_services(managed):
    # Preserve legacy non-target start semantics, including netclient.
    server = subprocess.call([str(RC / 'nut'), 'start'])
    if managed and server:
        return server
    return subprocess.call([str(RC / 'nut_upsmon'), 'start'])


def main(argv=None):
    argv = sys.argv[1:] if argv is None else argv
    if argv not in ([], ['--start']):
        return 2
    try:
        config = selected()
        if argv:
            return start_services(config is not None)
        if config is not None:
            wait_ready(config)
        return 0
    except (NotReady, OSError, ValueError, ET.ParseError, subprocess.TimeoutExpired) as error:
        reason = str(error) if isinstance(error, NotReady) else 'configuration_or_readiness_failed'
        print('nut: local apcupsd startup refused: ' + reason, file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
