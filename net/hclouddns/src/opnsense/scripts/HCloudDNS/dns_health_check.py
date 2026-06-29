#!/usr/local/bin/python3
"""
Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
All rights reserved.

DNS Health Check for HCloudDNS zones.
Checks NS delegation, SOA consistency, MX reachability, missing security records,
and CNAME at apex.
"""

import json
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from hcloud_api import HCloudAPI

HETZNER_NAMESERVERS = [
    '213.133.100.98',   # hydrogen.ns.hetzner.com
    '88.198.229.192',   # oxygen.ns.hetzner.com
    '193.47.99.3',      # helium.ns.hetzner.de
]

HETZNER_NS_NAMES = [
    'hydrogen.ns.hetzner.com',
    'oxygen.ns.hetzner.com',
    'helium.ns.hetzner.de',
]


def dns_query(fqdn, rdtype, nameserver=None, timeout=5):
    """Query DNS records. Uses dnspython, falls back to drill."""
    results = []
    try:
        import dns.resolver
        import dns.rdatatype

        resolver = dns.resolver.Resolver(configure=False)
        if nameserver:
            resolver.nameservers = [nameserver]
        resolver.lifetime = timeout

        try:
            answer = resolver.resolve(fqdn, dns.rdatatype.from_text(rdtype))
            for rdata in answer:
                results.append(str(rdata))
        except (dns.resolver.NXDOMAIN, dns.resolver.NoAnswer,
                dns.resolver.NoNameservers, dns.exception.Timeout):
            pass
    except ImportError:
        import subprocess
        cmd = ['drill']
        if nameserver:
            cmd.append(f'@{nameserver}')
        cmd.extend([fqdn, rdtype])
        try:
            proc = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout + 5)
            if proc.returncode == 0:
                in_answer = False
                for line in proc.stdout.splitlines():
                    if line.strip() == ';; ANSWER SECTION:':
                        in_answer = True
                        continue
                    if in_answer and line.strip() and not line.startswith(';;'):
                        parts = line.split()
                        if len(parts) >= 5:
                            results.append(parts[-1])
                    elif in_answer and (line.startswith(';;') or not line.strip()):
                        break
        except Exception:
            pass
    return results


def dns_query_full(fqdn, rdtype, nameserver=None, timeout=5):
    """Query DNS returning full record strings (for SOA etc)."""
    results = []
    try:
        import dns.resolver
        import dns.rdatatype

        resolver = dns.resolver.Resolver(configure=False)
        if nameserver:
            resolver.nameservers = [nameserver]
        resolver.lifetime = timeout

        try:
            answer = resolver.resolve(fqdn, dns.rdatatype.from_text(rdtype))
            for rdata in answer:
                results.append(str(rdata))
        except (dns.resolver.NXDOMAIN, dns.resolver.NoAnswer,
                dns.resolver.NoNameservers, dns.exception.Timeout):
            pass
    except ImportError:
        return dns_query(fqdn, rdtype, nameserver, timeout)
    return results


def check_ns_delegation(zone_name):
    """Check if NS delegation points to Hetzner nameservers."""
    ns_records = dns_query(zone_name, 'NS')
    ns_lower = [ns.rstrip('.').lower() for ns in ns_records]

    hetzner_found = 0
    for hns in HETZNER_NS_NAMES:
        if hns.lower() in ns_lower:
            hetzner_found += 1

    if hetzner_found >= 3:
        return {
            'name': 'NS Delegation',
            'status': 'pass',
            'message': f'All {hetzner_found} Hetzner nameservers delegated',
            'details': ns_lower
        }
    elif hetzner_found > 0:
        return {
            'name': 'NS Delegation',
            'status': 'warn',
            'message': f'Only {hetzner_found}/3 Hetzner nameservers found',
            'details': ns_lower
        }
    elif len(ns_records) > 0:
        return {
            'name': 'NS Delegation',
            'status': 'warn',
            'message': f'NS records found but not pointing to Hetzner ({", ".join(ns_lower[:3])})',
            'details': ns_lower
        }
    else:
        return {
            'name': 'NS Delegation',
            'status': 'fail',
            'message': 'No NS records found - domain may not be delegated',
            'details': []
        }


def check_soa_consistency(zone_name):
    """Check if SOA serial is consistent across all nameservers."""
    serials = {}
    for ns in HETZNER_NAMESERVERS:
        soa_records = dns_query_full(zone_name, 'SOA', ns)
        if soa_records:
            parts = soa_records[0].split()
            if len(parts) >= 3:
                serials[ns] = parts[2]

    if not serials:
        return {
            'name': 'SOA Consistency',
            'status': 'warn',
            'message': 'Could not query SOA from nameservers',
            'details': []
        }

    unique_serials = set(serials.values())
    if len(unique_serials) == 1:
        serial = list(unique_serials)[0]
        return {
            'name': 'SOA Consistency',
            'status': 'pass',
            'message': f'Serial {serial} consistent across {len(serials)} nameservers',
            'details': serials
        }
    else:
        return {
            'name': 'SOA Consistency',
            'status': 'warn',
            'message': f'Serial mismatch: {", ".join(unique_serials)}',
            'details': serials
        }


def check_mx_records(zone_name, records):
    """Check if MX records exist and are resolvable."""
    mx_records = [r for r in records if r.get('type') == 'MX']
    if not mx_records:
        return {
            'name': 'MX Records',
            'status': 'pass',
            'message': 'No MX records (domain may not handle email)',
            'details': []
        }

    resolvable = 0
    details = []
    for mx in mx_records:
        value = mx.get('value', '')
        parts = value.split()
        hostname = parts[-1] if parts else value
        hostname = hostname.rstrip('.')

        a_records = dns_query(hostname, 'A')
        aaaa_records = dns_query(hostname, 'AAAA')
        if a_records or aaaa_records:
            resolvable += 1
            details.append(f'{hostname}: OK')
        else:
            details.append(f'{hostname}: unresolvable')

    if resolvable == len(mx_records):
        return {
            'name': 'MX Records',
            'status': 'pass',
            'message': f'{len(mx_records)} MX record(s), all resolvable',
            'details': details
        }
    else:
        return {
            'name': 'MX Records',
            'status': 'warn',
            'message': f'{resolvable}/{len(mx_records)} MX records resolvable',
            'details': details
        }


def check_spf_record(records):
    """Check if SPF record exists."""
    for r in records:
        if r.get('type') == 'TXT':
            val = r.get('value', '').strip().strip('"').strip("'")
            if val.lower().startswith('v=spf1'):
                return {
                    'name': 'SPF Record',
                    'status': 'pass',
                    'message': 'SPF record found',
                    'details': [val]
                }
    return {
        'name': 'SPF Record',
        'status': 'warn',
        'message': 'No SPF record found - email spoofing possible',
        'details': []
    }


def check_dmarc_record(zone_name, records):
    """Check if DMARC record exists."""
    for r in records:
        if r.get('type') == 'TXT' and r.get('name', '') == '_dmarc':
            val = r.get('value', '').strip().strip('"').strip("'")
            if val.lower().startswith('v=dmarc1'):
                return {
                    'name': 'DMARC Record',
                    'status': 'pass',
                    'message': 'DMARC record found',
                    'details': [val]
                }
    return {
        'name': 'DMARC Record',
        'status': 'warn',
        'message': 'No DMARC record found - email authentication incomplete',
        'details': []
    }


def check_caa_record(records):
    """Check if CAA record exists."""
    caa_records = [r for r in records if r.get('type') == 'CAA']
    if caa_records:
        details = [r.get('value', '') for r in caa_records]
        return {
            'name': 'CAA Record',
            'status': 'pass',
            'message': f'{len(caa_records)} CAA record(s) found',
            'details': details
        }
    return {
        'name': 'CAA Record',
        'status': 'warn',
        'message': 'No CAA record - any CA can issue certificates',
        'details': []
    }


def check_cname_at_apex(records):
    """Check if there's a CNAME at the zone apex (invalid)."""
    for r in records:
        if r.get('type') == 'CNAME' and r.get('name', '') == '@':
            return {
                'name': 'CNAME at Apex',
                'status': 'fail',
                'message': 'CNAME at zone apex detected - this breaks DNS!',
                'details': [r.get('value', '')]
            }
    return {
        'name': 'CNAME at Apex',
        'status': 'pass',
        'message': 'No CNAME at zone apex',
        'details': []
    }


def run_health_check(token, zone_id, zone_name=None):
    """Run all health checks for a zone."""
    api = HCloudAPI(token)
    ALL_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'SOA']
    records = api.list_records(zone_id, ALL_TYPES)

    if zone_name is None:
        zones = api.list_zones()
        for z in zones:
            if z.get('id') == zone_id:
                zone_name = z.get('name', zone_id)
                break
        else:
            zone_name = zone_id

    checks = []
    checks.append(check_ns_delegation(zone_name))
    checks.append(check_soa_consistency(zone_name))
    checks.append(check_mx_records(zone_name, records))
    checks.append(check_spf_record(records))
    checks.append(check_dmarc_record(zone_name, records))
    checks.append(check_caa_record(records))
    checks.append(check_cname_at_apex(records))

    # DNSSEC check
    dnssec = check_dnssec(zone_name)
    if dnssec['signed'] and dnssec['delegated']:
        checks.append({
            'name': 'DNSSEC',
            'status': 'pass',
            'message': f'DNSSEC active ({dnssec["dnskey_count"]} DNSKEY, DS delegated)',
            'details': dnssec['ds_records']
        })
    elif dnssec['signed']:
        checks.append({
            'name': 'DNSSEC',
            'status': 'warn',
            'message': 'Zone is signed but no DS record at parent (not fully delegated)',
            'details': []
        })
    else:
        checks.append({
            'name': 'DNSSEC',
            'status': 'warn',
            'message': 'DNSSEC not enabled (optional but recommended)',
            'details': []
        })

    score = sum(1 for c in checks if c['status'] == 'pass')
    max_score = len(checks)

    return {
        'status': 'ok',
        'zone': zone_name,
        'checks': checks,
        'score': score,
        'maxScore': max_score
    }


def check_dnssec(zone_name):
    """Check DNSSEC status for a zone via DNS queries."""
    result = {
        'signed': False,
        'delegated': False,
        'dnskey_count': 0,
        'ds_records': []
    }

    # Check for DNSKEY records (zone is signed)
    dnskeys = dns_query(zone_name, 'DNSKEY')
    if dnskeys:
        result['signed'] = True
        result['dnskey_count'] = len(dnskeys)

    # Check for DS records at parent (delegation)
    ds_records = dns_query(zone_name, 'DS')
    if ds_records:
        result['delegated'] = True
        result['ds_records'] = ds_records

    return result


def run_dnssec_check(zone_name):
    """Run DNSSEC check for a zone."""
    dnssec = check_dnssec(zone_name)

    return {
        'status': 'ok',
        'zone': zone_name,
        'dnssec': dnssec
    }


def run_propagation_check(token, zone_id):
    """Check propagation for all records in a zone."""
    api = HCloudAPI(token)
    ALL_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA']
    records = api.list_records(zone_id, ALL_TYPES)

    zones = api.list_zones()
    zone_name = zone_id
    for z in zones:
        if z.get('id') == zone_id:
            zone_name = z.get('name', zone_id)
            break

    results = []
    for rec in records:
        rec_name = rec.get('name', '@')
        rec_type = rec.get('type', 'A')
        expected = rec.get('value', '')

        # Skip SOA and NS
        if rec_type in ('SOA', 'NS'):
            continue

        fqdn = f"{rec_name}.{zone_name}" if rec_name != '@' else zone_name

        ns_results = {}
        for ns in HETZNER_NAMESERVERS:
            answers = dns_query(fqdn, rec_type, ns)
            if answers:
                ns_results[ns] = answers[0]
            else:
                ns_results[ns] = None

        propagated = any(
            val is not None and val.rstrip('.') == expected.rstrip('.')
            for val in ns_results.values()
        )
        # For TXT/MX records with quotes or complex values, be more lenient
        if not propagated and rec_type in ('TXT', 'MX', 'CAA'):
            propagated = any(
                val is not None
                for val in ns_results.values()
            )

        results.append({
            'name': rec_name,
            'type': rec_type,
            'expected': expected,
            'nsResults': ns_results,
            'propagated': propagated
        })

    return {
        'status': 'ok',
        'zone': zone_name,
        'records': results,
        'total': len(results),
        'propagated': sum(1 for r in results if r['propagated'])
    }


def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: dns_health_check.py <mode> <token> <zone_id> [zone_name]'
        }))
        sys.exit(1)

    mode = sys.argv[1].strip()
    token = sys.argv[2].strip()

    if mode == 'health':
        if len(sys.argv) < 4:
            print(json.dumps({'status': 'error', 'message': 'zone_id required'}))
            sys.exit(1)
        zone_id = sys.argv[3].strip()
        zone_name = sys.argv[4].strip() if len(sys.argv) > 4 else None
        result = run_health_check(token, zone_id, zone_name)
    elif mode == 'propagation':
        if len(sys.argv) < 4:
            print(json.dumps({'status': 'error', 'message': 'zone_id required'}))
            sys.exit(1)
        zone_id = sys.argv[3].strip()
        result = run_propagation_check(token, zone_id)
    elif mode == 'dnssec':
        if len(sys.argv) < 4:
            print(json.dumps({'status': 'error', 'message': 'zone_name required'}))
            sys.exit(1)
        zone_name = sys.argv[3].strip()
        result = run_dnssec_check(zone_name)
    else:
        result = {'status': 'error', 'message': f'Unknown mode: {mode}'}

    print(json.dumps(result))


if __name__ == '__main__':
    main()
