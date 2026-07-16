"""BIND dynamic-update execution for DHCP leases."""

import ipaddress
import os
import subprocess
import syslog
import tempfile

from dhcpwatcher import lease as lease_helpers


def run_nsupdate(mapping, commands, zone=None):
    """Execute nsupdate with TSIG authentication via a temporary key file."""
    keyfile = None
    try:
        keyfile = tempfile.NamedTemporaryFile(
            mode='w', prefix='nsupdate-key-', delete=False
        )
        os.chmod(keyfile.name, 0o600)
        keyfile.write('key "{}" {{\n'.format(mapping['tsigkey_name']))
        keyfile.write('    algorithm {};\n'.format(mapping['tsigkey_algo']))
        keyfile.write('    secret "{}";\n'.format(mapping['tsigkey_secret']))
        keyfile.write('};\n')
        keyfile.close()

        nsupdate_input = 'server {} {}\n'.format(
            mapping['nsupdate_address'], mapping['nsupdate_port']
        )
        if zone:
            nsupdate_input += 'zone {}\n'.format(zone)
        for cmd in commands:
            nsupdate_input += cmd + '\n'
        nsupdate_input += 'send\n'

        result = subprocess.run(
            ['/usr/local/bin/nsupdate', '-k', keyfile.name],
            input=nsupdate_input,
            text=True,
            capture_output=True,
            timeout=10,
        )

        if result.returncode != 0:
            stderr = result.stderr.lower() if result.stderr else ''
            if 'refused' in stderr:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'nsupdate REFUSED: key={} zone={}'.format(
                        mapping['tsigkey_name'], mapping['hostname_suffix']
                    )
                )
            elif 'notauth' in stderr:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    'nsupdate NOTAUTH: key={} zone={} (zone may not exist or '
                    'key lacks permissions)'.format(
                        mapping['tsigkey_name'], mapping['hostname_suffix']
                    )
                )
            elif 'servfail' in stderr:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'nsupdate SERVFAIL: key={} zone={}'.format(
                        mapping['tsigkey_name'], mapping['hostname_suffix']
                    )
                )
            elif 'connection refused' in stderr:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'nsupdate connection refused: server={}:{}'.format(
                        mapping['nsupdate_address'], mapping['nsupdate_port']
                    )
                )
            elif 'timed out' in stderr:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'nsupdate timed out: server={}:{}'.format(
                        mapping['nsupdate_address'], mapping['nsupdate_port']
                    )
                )
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'nsupdate failed (rc={}): {}'.format(
                        result.returncode, result.stderr.strip()
                    )
                )
            return False
        return True

    except subprocess.TimeoutExpired:
        syslog.syslog(
            syslog.LOG_ERR,
            'nsupdate timed out (timeout=10s): server={}:{}'.format(
                mapping['nsupdate_address'], mapping['nsupdate_port']
            )
        )
        return False
    except Exception as error:
        syslog.syslog(syslog.LOG_ERR, 'nsupdate exception: {}'.format(error))
        return False
    finally:
        if keyfile and os.path.exists(keyfile.name):
            try:
                os.unlink(keyfile.name)
            except OSError:
                pass


class BindUpdater:
    """Translate lease records into nsupdate commands and execute them."""

    def __init__(self, run_nsupdate_func=None):
        self._run_nsupdate = run_nsupdate_func or run_nsupdate

    def add_records(self, mapping, lease):
        """Add A/AAAA + PTR for a lease. Returns True if forward succeeded."""
        fqdn = lease_helpers.build_fqdn(
            lease['hostname'], mapping['hostname_suffix']
        )
        addr = lease['address']
        fwd = lease_helpers.forward_commands('add', addr, fqdn)
        rev = lease_helpers.reverse_commands('add', addr, fqdn)
        fwd_ok = self._run_nsupdate(mapping, fwd, mapping['hostname_suffix'])
        reverse_zone = self._reverse_zone(mapping, addr)
        if reverse_zone is None:
            return fwd_ok
        return fwd_ok and self._run_nsupdate(mapping, rev, reverse_zone)

    def delete_records(self, mapping, lease):
        """Delete A/AAAA + PTR for a lease or state entry."""
        suffix = lease.get('suffix', mapping['hostname_suffix'])
        fqdn = lease_helpers.build_fqdn(lease['hostname'], suffix)
        addr = ipaddress.ip_address(lease['address'])
        fwd = lease_helpers.forward_commands('delete', addr, fqdn)
        rev = lease_helpers.reverse_commands('delete', addr, fqdn)
        fwd_ok = self._run_nsupdate(mapping, fwd, suffix)
        reverse_zone = lease.get('reverse_zone') or self._reverse_zone(mapping, addr)
        if not reverse_zone:
            return fwd_ok
        return fwd_ok and self._run_nsupdate(mapping, rev, reverse_zone)

    @staticmethod
    def _reverse_zone(mapping, address):
        if mapping.get('reverse_zone'):
            return mapping['reverse_zone']
        reverse_zone = lease_helpers.select_reverse_zone(
            address, mapping.get('reverse_zones', [])
        )
        return reverse_zone['zone'] if reverse_zone else None
