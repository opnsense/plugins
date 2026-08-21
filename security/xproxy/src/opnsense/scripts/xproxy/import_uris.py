#!/usr/local/bin/python3

"""
Parse proxy URI strings (vless://, vmess://, ss://, trojan://) into
structured server definitions for the Xproxy OPNsense plugin.

Usage: import_uris.py <file_with_uris>
Output: JSON on stdout with {"servers": [...], "errors": [...]}
"""

import sys
import json
import base64
from urllib.parse import parse_qs, unquote

MAX_INPUT_BYTES = 2 * 1024 * 1024


def pad_b64(s):
    return s + '=' * (-len(s) % 4)


def parse_vless(uri):
    """Parse vless://uuid@host:port?params#description"""
    rest = uri[len('vless://'):]
    fragment = ''
    if '#' in rest:
        rest, fragment = rest.rsplit('#', 1)
        fragment = unquote(fragment)

    if '@' not in rest:
        raise ValueError("vless URI missing '@'")

    userinfo, hostport = rest.split('@', 1)
    query = ''
    if '?' in hostport:
        hostport, query = hostport.split('?', 1)

    if ':' in hostport:
        host, port = hostport.rsplit(':', 1)
    else:
        host, port = hostport, '443'

    params = parse_qs(query)

    def p(k, d=''):
        return params.get(k, [d])[0]

    flow_raw = p('flow', '')

    return {
        'enabled': '1',
        'protocol': 'vless',
        'description': fragment or host,
        'address': host,
        'port': port,
        'user_id': userinfo,
        'encryption': p('encryption', 'none'),
        'flow': flow_raw.replace('-', '_') if flow_raw else '',
        'transport': p('type', 'tcp'),
        'transport_host': p('host'),
        'transport_path': p('path'),
        'security': p('security', 'none'),
        'sni': p('sni'),
        'fingerprint': p('fp', 'chrome'),
        'alpn': p('alpn'),
        'reality_pubkey': p('pbk'),
        'reality_short_id': p('sid'),
        'raw_uri': uri,
    }


def parse_vmess(uri):
    """Parse vmess://base64json"""
    encoded = uri[len('vmess://'):]
    try:
        decoded = base64.b64decode(pad_b64(encoded)).decode('utf-8')
        cfg = json.loads(decoded)
    except Exception:
        raise ValueError("Invalid vmess base64 payload")

    transport = cfg.get('net', 'tcp')
    security = 'tls' if cfg.get('tls') == 'tls' else 'none'

    return {
        'enabled': '1',
        'protocol': 'vmess',
        'description': cfg.get('ps', cfg.get('add', '')),
        'address': cfg.get('add', ''),
        'port': str(cfg.get('port', 443)),
        'user_id': cfg.get('id', ''),
        'encryption': cfg.get('scy', 'auto'),
        'flow': '',
        'transport': transport,
        'transport_host': cfg.get('host', ''),
        'transport_path': cfg.get('path', ''),
        'security': security,
        'sni': cfg.get('sni', cfg.get('host', '')),
        'fingerprint': cfg.get('fp', 'chrome'),
        'alpn': cfg.get('alpn', ''),
        'reality_pubkey': '',
        'reality_short_id': '',
        'raw_uri': uri,
    }


def parse_shadowsocks(uri):
    """Parse ss://base64(method:password)@host:port#description or ss://base64(...)#desc"""
    rest = uri[len('ss://'):]
    fragment = ''
    if '#' in rest:
        rest, fragment = rest.rsplit('#', 1)
        fragment = unquote(fragment)

    if '@' in rest:
        userinfo, hostport = rest.split('@', 1)
        try:
            decoded = base64.b64decode(pad_b64(userinfo)).decode('utf-8')
        except Exception:
            decoded = userinfo
        if ':' in decoded:
            method, password = decoded.split(':', 1)
        else:
            method, password = 'aes-256-gcm', decoded
        if ':' in hostport:
            host, port = hostport.rsplit(':', 1)
        else:
            host, port = hostport, '443'
    else:
        try:
            decoded = base64.b64decode(pad_b64(rest)).decode('utf-8')
        except Exception:
            raise ValueError("Invalid ss base64 payload")
        if '@' in decoded:
            cred, hostport = decoded.split('@', 1)
            method, password = cred.split(':', 1) if ':' in cred else ('aes-256-gcm', cred)
            host, port = hostport.rsplit(':', 1) if ':' in hostport else (hostport, '443')
        else:
            raise ValueError("Cannot parse ss URI")

    return {
        'enabled': '1',
        'protocol': 'shadowsocks',
        'description': fragment or host,
        'address': host,
        'port': port,
        'user_id': '',
        'password': password,
        'encryption': method,
        'flow': '',
        'transport': 'tcp',
        'transport_host': '',
        'transport_path': '',
        'security': 'none',
        'sni': '',
        'fingerprint': '',
        'alpn': '',
        'reality_pubkey': '',
        'reality_short_id': '',
        'raw_uri': uri,
    }


def parse_trojan(uri):
    """Parse trojan://password@host:port?params#description"""
    rest = uri[len('trojan://'):]
    fragment = ''
    if '#' in rest:
        rest, fragment = rest.rsplit('#', 1)
        fragment = unquote(fragment)

    if '@' not in rest:
        raise ValueError("trojan URI missing '@'")

    password, hostport = rest.split('@', 1)
    query = ''
    if '?' in hostport:
        hostport, query = hostport.split('?', 1)

    if ':' in hostport:
        host, port = hostport.rsplit(':', 1)
    else:
        host, port = hostport, '443'

    params = parse_qs(query)

    def p(k, d=''):
        return params.get(k, [d])[0]

    security = p('security', 'tls')
    if security not in ('tls', 'reality', 'none'):
        security = 'tls'

    return {
        'enabled': '1',
        'protocol': 'trojan',
        'description': fragment or host,
        'address': host,
        'port': port,
        'user_id': '',
        'password': password,
        'encryption': '',
        'flow': '',
        'transport': p('type', 'tcp'),
        'transport_host': p('host'),
        'transport_path': p('path'),
        'security': security,
        'sni': p('sni', host),
        'fingerprint': p('fp', 'chrome'),
        'alpn': p('alpn'),
        'reality_pubkey': p('pbk'),
        'reality_short_id': p('sid'),
        'raw_uri': uri,
    }


PARSERS = {
    'vless://': parse_vless,
    'vmess://': parse_vmess,
    'ss://': parse_shadowsocks,
    'trojan://': parse_trojan,
}


def parse_uri(line):
    line = line.strip()
    if not line:
        return None
    for prefix, parser in PARSERS.items():
        if line.startswith(prefix):
            return parser(line)
    raise ValueError("Unknown URI scheme: " + line[:20])


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"servers": [], "errors": ["No input file specified."]}))
        sys.exit(0)

    filepath = sys.argv[1]
    try:
        with open(filepath, 'r', errors='replace') as f:
            content = f.read(MAX_INPUT_BYTES + 1)
    except IOError as e:
        print(json.dumps({"servers": [], "errors": [str(e)]}))
        sys.exit(0)

    if len(content) > MAX_INPUT_BYTES:
        print(json.dumps({"servers": [], "errors": ["Input file too large (max 2 MiB)."]}))
        sys.exit(0)

    servers = []
    errors = []
    for line in content.strip().splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            srv = parse_uri(line)
            if srv:
                servers.append(srv)
        except Exception as e:
            errors.append("Failed to parse: %s (%s)" % (line[:60], str(e)))

    print(json.dumps({"servers": servers, "errors": errors}))


if __name__ == '__main__':
    main()
