#!/usr/local/bin/python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

Parse BIND zonefile for importing into HCloudDNS.
Only parses - does not create records. Frontend handles selection and creation.
"""

import json
import re
import sys


def parse_zonefile(content):
    """Parse BIND-format zonefile content into records."""
    records = []
    origin = ''
    default_ttl = 300

    for line in content.split('\n'):
        line = line.strip()

        # Skip empty lines and comments
        if not line or line.startswith(';'):
            continue

        # Handle $ORIGIN
        if line.upper().startswith('$ORIGIN'):
            origin = line.split(None, 1)[1].rstrip('.') if len(line.split(None, 1)) > 1 else ''
            continue

        # Handle $TTL
        if line.upper().startswith('$TTL'):
            try:
                default_ttl = int(line.split(None, 1)[1])
            except (ValueError, IndexError):
                pass
            continue

        # Skip other directives
        if line.startswith('$'):
            continue

        # Parse record line
        record = parse_record_line(line, default_ttl)
        if record:
            records.append(record)

    return records


def parse_record_line(line, default_ttl):
    """Parse a single DNS record line."""
    # Remove inline comments
    if ';' in line and '"' not in line.split(';')[0]:
        line = line.split(';')[0].strip()
    elif ';' in line:
        # Handle TXT records with semicolons inside quotes
        in_quotes = False
        clean = []
        for char in line:
            if char == '"':
                in_quotes = not in_quotes
            if char == ';' and not in_quotes:
                break
            clean.append(char)
        line = ''.join(clean).strip()

    if not line:
        return None

    # Tokenize respecting quoted strings
    tokens = tokenize(line)
    if len(tokens) < 3:
        return None

    name = ''
    ttl = default_ttl
    rclass = 'IN'
    rtype = ''
    value = ''

    idx = 0

    # First token: name or empty (continuation)
    if tokens[0] not in ('IN', 'CH', 'HS') and not is_record_type(tokens[0]) and not tokens[0].isdigit():
        name = tokens[0]
        idx = 1
    else:
        name = '@'

    # Next: optional TTL
    if idx < len(tokens) and tokens[idx].isdigit():
        ttl = int(tokens[idx])
        idx += 1

    # Next: optional class
    if idx < len(tokens) and tokens[idx].upper() in ('IN', 'CH', 'HS'):
        rclass = tokens[idx].upper()
        idx += 1

    # Next: record type
    if idx < len(tokens) and is_record_type(tokens[idx]):
        rtype = tokens[idx].upper()
        idx += 1
    else:
        return None

    # Rest is the value
    value = ' '.join(tokens[idx:])

    # Clean up TXT values
    if rtype == 'TXT' and value.startswith('"') and value.endswith('"'):
        value = value[1:-1]

    # Clean trailing dots from hostnames
    if rtype in ('CNAME', 'NS') and value.endswith('.'):
        value = value[:-1]

    # Clean name
    if name.endswith('.'):
        name = name[:-1]

    if not rtype or not value:
        return None

    return {
        'name': name,
        'type': rtype,
        'value': value,
        'ttl': ttl
    }


def tokenize(line):
    """Split line into tokens, respecting quoted strings."""
    tokens = []
    current = []
    in_quotes = False

    for char in line:
        if char == '"':
            in_quotes = not in_quotes
            current.append(char)
        elif char in (' ', '\t') and not in_quotes:
            if current:
                tokens.append(''.join(current))
                current = []
        else:
            current.append(char)

    if current:
        tokens.append(''.join(current))

    return tokens


def is_record_type(token):
    """Check if token is a known DNS record type."""
    return token.upper() in (
        'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA',
        'SOA', 'PTR', 'TLSA', 'DNSKEY', 'DS', 'NAPTR', 'SSHFP'
    )


def main():
    # Read zonefile content from stdin
    content = sys.stdin.read()

    if not content.strip():
        print(json.dumps({
            'status': 'error',
            'message': 'No zonefile content provided'
        }))
        sys.exit(1)

    records = parse_zonefile(content)

    print(json.dumps({
        'status': 'ok',
        'records': records,
        'count': len(records)
    }))


if __name__ == '__main__':
    main()
