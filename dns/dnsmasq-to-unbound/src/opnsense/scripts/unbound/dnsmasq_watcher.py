#!/usr/local/bin/python3

"""
    Copyright (c) 2025 C. Hall (chall37@users.noreply.github.com)
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

    --------------------------------------------------------------------------------------

    Watch dnsmasq DHCP leases and static hosts, register them in Unbound DNS.
    Uses kqueue for efficient file watching on FreeBSD.
    Reads configuration from OPNsense config.xml.

    Failure Handling:
    - Pre-flight checks verify dependencies before starting
    - On critical failure, enters idle state (running but doing nothing)
    - Logs failure once, waits for restart
    - Does not modify config or spam logs on failure
"""

import argparse
import json
import os
import signal
import select
import subprocess
import syslog
import time
import sys
import ipaddress
import xml.etree.ElementTree as ET

sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize  # noqa: E402

LEASE_FILE = '/var/db/dnsmasq.leases'
STATIC_HOSTS_FILE = '/var/etc/dnsmasq-hosts'
DNSMASQ_CONF = '/usr/local/etc/dnsmasq.conf'
UNBOUND_CONTROL = '/usr/local/sbin/unbound-control'
UNBOUND_CONF = '/var/unbound/unbound.conf'
OPNSENSE_CONFIG = '/conf/config.xml'

# Maximum consecutive failures before entering permanent idle
MAX_CONSECUTIVE_FAILURES = 5
# How long to wait between retry attempts (seconds)
FAILURE_RETRY_DELAY = 30
# How often to run full reconciliation (seconds)
RECONCILE_INTERVAL = 300  # 5 minutes
# Marker to identify our managed records
MANAGED_MARKER = 'managed-by=dnsmasq-to-unbound'
# Delay before verifying added records (seconds)
VERIFICATION_DELAY = 5
# Status file for UI notifications
STATUS_FILE = '/var/run/dnsmasq_watcher_status.json'


class StatusLevel:
    """Status levels matching OPNsense SystemStatusCode."""
    OK = 2
    NOTICE = 1
    WARNING = 0
    ERROR = -1


class FailureReason:
    """Constants for failure reasons."""
    NONE = None
    DISABLED = 'disabled'
    NO_KQUEUE = 'no_kqueue'
    UNBOUND_NOT_RUNNING = 'unbound_not_running'
    UNBOUND_CONTROL_MISSING = 'unbound_control_missing'
    UNBOUND_CONTROL_DISABLED = 'unbound_control_disabled'
    CONFIG_PARSE_ERROR = 'config_parse_error'
    MAX_FAILURES_EXCEEDED = 'max_failures_exceeded'


class DnsmasqLeaseWatcher:
    def __init__(self, lease_file=LEASE_FILE, static_hosts_file=STATIC_HOSTS_FILE,
                 dnsmasq_conf=DNSMASQ_CONF):
        self.lease_file = lease_file
        self.static_hosts_file = static_hosts_file
        self.dnsmasq_conf = dnsmasq_conf
        # registered_records: fqdn -> {'ip': str, 'source': str, 'expiry': int or None}
        self.registered_records = {}
        # pending_verification: fqdn -> (record, added_time) - records to verify after delay
        self.pending_verification = {}
        self.kq = None
        self.watched_fds = {}  # fd -> filepath
        # Config values (loaded from config.xml)
        self.enabled = True
        self.watch_leases = True
        self.watch_static = True
        self.domain_filter = set()  # Empty = all domains
        # Dnsmasq domain config (loaded from dnsmasq.conf)
        self.global_domain = None  # Global default domain
        self.domain_ranges = []  # List of (start_ip, end_ip, domain) tuples
        # Failure tracking
        self.failed = False
        self.failure_reason = FailureReason.NONE
        self.consecutive_failures = 0
        self.running = True
        # Status tracking for UI notifications
        self.status_level = StatusLevel.OK
        self.status_message = None
        self.skipped_records_notified = False  # Only notify once per run

    def log(self, message, priority=syslog.LOG_INFO):
        syslog.syslog(priority, f"dnsmasq_watcher: {message}")

    def set_status(self, level, message):
        """Set current status and write to status file for UI consumption."""
        self.status_level = level
        self.status_message = message
        self.write_status_file()

    def write_status_file(self):
        """Write current status to file for PHP status class to read."""
        status = {
            'level': self.status_level,
            'message': self.status_message,
            'timestamp': int(time.time()),
            'registered_count': len(self.registered_records)
        }
        try:
            with open(STATUS_FILE, 'w') as f:
                json.dump(status, f)
        except IOError as e:
            self.log(f"Failed to write status file: {e}", syslog.LOG_WARNING)

    def clear_status_file(self):
        """Remove status file (service stopping or OK status)."""
        try:
            if os.path.exists(STATUS_FILE):
                os.unlink(STATUS_FILE)
        except IOError:
            pass

    def enter_failed_state(self, reason, message):
        """Enter failed/idle state. Log once, then wait for restart."""
        self.failed = True
        self.failure_reason = reason
        self.log(f"FAILED: {message} - entering idle state (restart to retry)", syslog.LOG_ERR)
        self.set_status(StatusLevel.ERROR, message)

    def preflight_checks(self):
        """
        Verify all dependencies are available before starting.
        Returns True if all checks pass, False otherwise.
        """
        # Check 1: kqueue availability (FreeBSD-specific)
        if not hasattr(select, 'kqueue'):
            self.enter_failed_state(
                FailureReason.NO_KQUEUE,
                "kqueue not available (requires FreeBSD)"
            )
            return False

        # Check 2: unbound-control executable exists
        if not os.path.isfile(UNBOUND_CONTROL):
            self.enter_failed_state(
                FailureReason.UNBOUND_CONTROL_MISSING,
                f"unbound-control not found at {UNBOUND_CONTROL}"
            )
            return False

        if not os.access(UNBOUND_CONTROL, os.X_OK):
            self.enter_failed_state(
                FailureReason.UNBOUND_CONTROL_MISSING,
                f"unbound-control not executable at {UNBOUND_CONTROL}"
            )
            return False

        # Check 3: unbound is running and controllable
        try:
            result = subprocess.run(
                [UNBOUND_CONTROL, '-c', UNBOUND_CONF, 'status'],
                capture_output=True, text=True, timeout=10
            )
            if result.returncode != 0:
                stderr = result.stderr.strip()
                if 'control-enable' in stderr or 'Connection refused' in stderr:
                    self.enter_failed_state(
                        FailureReason.UNBOUND_CONTROL_DISABLED,
                        "Unbound remote control not enabled. "
                        "Enable 'Remote Control' in Services > Unbound DNS > General"
                    )
                    return False
                else:
                    self.enter_failed_state(
                        FailureReason.UNBOUND_NOT_RUNNING,
                        f"Unbound not responding: {stderr or result.stdout.strip()}"
                    )
                    return False
        except subprocess.TimeoutExpired:
            self.enter_failed_state(
                FailureReason.UNBOUND_NOT_RUNNING,
                "Unbound control timeout - service may be unresponsive"
            )
            return False
        except FileNotFoundError:
            self.enter_failed_state(
                FailureReason.UNBOUND_CONTROL_MISSING,
                f"unbound-control not found: {UNBOUND_CONTROL}"
            )
            return False
        except Exception as e:
            self.enter_failed_state(
                FailureReason.UNBOUND_NOT_RUNNING,
                f"Error checking Unbound status: {e}"
            )
            return False

        # Check 4: At least one watch source should exist or be expected
        if not self.watch_leases and not self.watch_static:
            self.log("Warning: Both lease and static watching disabled", syslog.LOG_WARNING)

        self.log("Pre-flight checks passed")
        return True

    def load_config(self):
        """
        Load configuration from OPNsense config.xml.
        Returns True on success, False on critical failure.
        """
        if not os.path.exists(OPNSENSE_CONFIG):
            self.log("Config file not found, using defaults", syslog.LOG_WARNING)
            return True  # Not critical, use defaults

        try:
            tree = ET.parse(OPNSENSE_CONFIG)
            root = tree.getroot()

            config = root.find('.//OPNsense/DnsmasqToUnbound')
            if config is None:
                self.log("No DnsmasqToUnbound config found, using defaults")
                return True  # Not critical, use defaults

            enabled = config.find('enabled')
            if enabled is not None:
                self.enabled = enabled.text == '1'

            watch_leases = config.find('watchleases')
            if watch_leases is not None:
                self.watch_leases = watch_leases.text == '1'

            watch_static = config.find('watchstatic')
            if watch_static is not None:
                self.watch_static = watch_static.text == '1'

            domains = config.find('domains')
            if domains is not None and domains.text:
                # Parse comma-separated domains, normalize (strip whitespace and leading dots)
                raw_domains = domains.text.split(',')
                self.domain_filter = set()
                for d in raw_domains:
                    d = d.strip().lstrip('.')
                    if d:
                        self.domain_filter.add(d)

            self.log(f"Config loaded: enabled={self.enabled}, leases={self.watch_leases}, "
                     f"static={self.watch_static}, domains={self.domain_filter or 'all'}")
            return True

        except ET.ParseError as e:
            self.enter_failed_state(
                FailureReason.CONFIG_PARSE_ERROR,
                f"Error parsing config.xml: {e}"
            )
            return False
        except Exception as e:
            self.log(f"Error loading config: {e}", syslog.LOG_ERR)
            return True  # Non-critical, continue with defaults

    def load_dnsmasq_config(self):
        """
        Load domain configuration from dnsmasq.conf.
        Parses 'domain=' lines to extract global domain and IP-range-specific domains.
        Returns True if a domain is configured, False otherwise.
        """
        self.global_domain = None
        self.domain_ranges = []

        if not os.path.exists(self.dnsmasq_conf):
            msg = f"dnsmasq.conf not found at {self.dnsmasq_conf}"
            self.log(msg, syslog.LOG_ERR)
            self.set_status(StatusLevel.ERROR, msg)
            return False

        try:
            with open(self.dnsmasq_conf, 'r') as f:
                for line in f:
                    line = line.strip()
                    if not line.startswith('domain='):
                        continue

                    # Parse domain= line
                    # Format: domain=<domain> or domain=<domain>,<start_ip>,<end_ip>
                    value = line[7:]  # Strip 'domain='
                    parts = value.split(',')

                    if len(parts) == 1:
                        # Global domain (first one wins if multiple)
                        if self.global_domain is None:
                            self.global_domain = parts[0].strip()
                    elif len(parts) >= 3:
                        # Range-specific domain
                        domain = parts[0].strip()
                        try:
                            start_ip = ipaddress.ip_address(parts[1].strip())
                            end_ip = ipaddress.ip_address(parts[2].strip())
                            self.domain_ranges.append((start_ip, end_ip, domain))
                        except ValueError as e:
                            self.log(f"Invalid IP in domain range: {line} ({e})", syslog.LOG_WARNING)

            if self.global_domain or self.domain_ranges:
                self.log(f"Dnsmasq config: global_domain={self.global_domain}, "
                         f"ranges={len(self.domain_ranges)}")
                return True
            else:
                msg = "No domain configured in dnsmasq.conf - cannot register DHCP leases"
                self.log(msg, syslog.LOG_ERR)
                self.set_status(StatusLevel.ERROR, msg)
                return False

        except IOError as e:
            msg = f"Error reading dnsmasq.conf: {e}"
            self.log(msg, syslog.LOG_ERR)
            self.set_status(StatusLevel.ERROR, msg)
            return False

    def get_domain_for_ip(self, ip_str):
        """
        Get the domain for an IP address based on dnsmasq config.
        Checks range-specific domains first, then falls back to global domain.
        Returns None if no domain can be determined.
        """
        try:
            ip = ipaddress.ip_address(ip_str)
        except ValueError:
            return None

        # Check range-specific domains first
        for start_ip, end_ip, domain in self.domain_ranges:
            if start_ip <= ip <= end_ip:
                return domain

        # Fall back to global domain
        return self.global_domain

    def parse_lease_line(self, line):
        """
        Parse a dnsmasq lease line.
        Format: <expiry_timestamp> <mac_address> <ip_address> <hostname> <client_id>
        """
        parts = line.strip().split()
        if len(parts) < 4:
            return None

        try:
            expiry = int(parts[0])
        except ValueError:
            return None

        ip = parts[2]
        hostname = parts[3] if parts[3] != '*' else None

        if not hostname:
            return None

        return {
            'expiry': expiry,
            'ip': ip,
            'hostname': hostname
        }

    def parse_hosts_line(self, line):
        """
        Parse a hosts file line.
        Format: <ip_address> <hostname> [aliases...]
        Returns hostname and any domain suffix found.
        """
        line = line.strip()
        if not line or line.startswith('#'):
            return None

        parts = line.split()
        if len(parts) < 2:
            return None

        ip = parts[0]
        hostname = parts[1]
        domain = None

        # Extract domain if present
        if '.' in hostname:
            parts_name = hostname.split('.', 1)
            hostname = parts_name[0]
            domain = parts_name[1]

        return {
            'ip': ip,
            'hostname': hostname,
            'domain': domain
        }

    def get_domains_to_register(self, source_domain=None, ip=None):
        """
        Determine which domains to register a host under.

        Args:
            source_domain: Domain from the source record (e.g., from static host entry)
            ip: IP address (used to look up domain from dnsmasq config if no source_domain)

        Returns:
            List of domains to register under, or empty list if none can be determined.
        """
        # Determine the effective domain
        if source_domain:
            effective_domain = source_domain
        elif ip:
            effective_domain = self.get_domain_for_ip(ip)
        else:
            effective_domain = self.global_domain

        if not effective_domain:
            # No domain can be determined - don't register
            return []

        if self.domain_filter:
            # Filter mode: only register if domain matches filter
            if effective_domain in self.domain_filter:
                return [effective_domain]
            else:
                # Domain doesn't match filter, skip
                return []
        else:
            # No filter: register under the effective domain
            return [effective_domain]

    def read_leases(self):
        """
        Read and parse all leases from the lease file.
        Returns dict keyed by FQDN with full record metadata.
        """
        records = {}
        if not self.watch_leases:
            return records
        if not os.path.exists(self.lease_file):
            return records

        current_time = int(time.time())
        try:
            with open(self.lease_file, 'r') as f:
                for line in f:
                    lease = self.parse_lease_line(line)
                    if lease:
                        # Skip expired leases (expiry of 0 means infinite)
                        if lease['expiry'] != 0 and lease['expiry'] < current_time:
                            continue
                        # Leases don't have domain suffix, determine from IP
                        domains = self.get_domains_to_register(None, lease['ip'])
                        if not domains:
                            self.log(f"Skipping lease {lease['hostname']} ({lease['ip']}): "
                                     "no domain configured for this IP range", syslog.LOG_WARNING)
                            if not self.skipped_records_notified:
                                self.skipped_records_notified = True
                                self.set_status(
                                    StatusLevel.WARNING,
                                    "Some records skipped - IP not in any configured domain range. "
                                    "Check system log for details."
                                )
                            continue
                        for domain in domains:
                            fqdn = f"{lease['hostname']}.{domain}"
                            fqdn_lower = fqdn.lower()  # DNS is case-insensitive
                            new_record = {
                                'ip': lease['ip'],
                                'source': 'lease',
                                'expiry': lease['expiry'],
                                'hostname': lease['hostname'],
                                'domain': domain,
                                'fqdn': fqdn  # Preserve original case for display
                            }
                            # Handle duplicates within leases: prefer later expiry
                            if fqdn_lower in records:
                                existing = records[fqdn_lower]
                                if self._should_replace(existing, new_record):
                                    records[fqdn_lower] = new_record
                            else:
                                records[fqdn_lower] = new_record
        except IOError as e:
            self.log(f"Error reading lease file: {e}", syslog.LOG_ERR)

        return records

    def read_static_hosts(self):
        """
        Read and parse static hosts file.
        Returns dict keyed by FQDN with full record metadata.
        """
        records = {}
        if not self.watch_static:
            return records
        if not os.path.exists(self.static_hosts_file):
            return records

        try:
            with open(self.static_hosts_file, 'r') as f:
                for line in f:
                    host = self.parse_hosts_line(line)
                    if host:
                        # Register under appropriate domains (use IP lookup if no explicit domain)
                        domains = self.get_domains_to_register(host['domain'], host['ip'])
                        if not domains:
                            self.log(f"Skipping static host {host['hostname']} ({host['ip']}): "
                                     "no domain configured for this IP range", syslog.LOG_WARNING)
                            if not self.skipped_records_notified:
                                self.skipped_records_notified = True
                                self.set_status(
                                    StatusLevel.WARNING,
                                    "Some records skipped - IP not in any configured domain range. "
                                    "Check system log for details."
                                )
                            continue
                        for domain in domains:
                            fqdn = f"{host['hostname']}.{domain}"
                            fqdn_lower = fqdn.lower()  # DNS is case-insensitive
                            new_record = {
                                'ip': host['ip'],
                                'source': 'static',
                                'expiry': None,  # Static entries have no expiry
                                'hostname': host['hostname'],
                                'domain': domain,
                                'fqdn': fqdn  # Preserve original case for display
                            }
                            # For static duplicates, first one wins (earlier in file)
                            if fqdn_lower not in records:
                                records[fqdn_lower] = new_record
        except IOError as e:
            self.log(f"Error reading static hosts file: {e}", syslog.LOG_ERR)

        return records

    def _should_replace(self, existing, new):
        """
        Determine if new record should replace existing record for same FQDN.

        Rules:
        1. If both have expiry timestamps, prefer later expiry (newer lease)
        2. Otherwise, static entries take precedence over leases
        3. If both are same type with no expiry info, keep existing
        """
        existing_expiry = existing.get('expiry')
        new_expiry = new.get('expiry')
        existing_source = existing.get('source')
        new_source = new.get('source')

        # Both have expiry - prefer later expiry (newer)
        if existing_expiry is not None and new_expiry is not None:
            # expiry=0 means infinite, treat as very far future
            existing_cmp = existing_expiry if existing_expiry != 0 else float('inf')
            new_cmp = new_expiry if new_expiry != 0 else float('inf')
            return new_cmp > existing_cmp

        # Static takes precedence over lease when we can't compare timestamps
        if existing_source == 'static' and new_source == 'lease':
            return False
        if existing_source == 'lease' and new_source == 'static':
            return True

        # Same source type, keep existing
        return False

    def _merge_records(self, static_records, lease_records):
        """
        Merge static and lease records, deduplicating by FQDN (case-insensitive).
        Returns dict keyed by lowercase FQDN with winning record for each.
        """
        merged = {}

        # Add all static records first (keys are already lowercase)
        for fqdn_lower, record in static_records.items():
            merged[fqdn_lower] = record

        # Add lease records, applying conflict resolution
        for fqdn_lower, record in lease_records.items():
            if fqdn_lower in merged:
                if self._should_replace(merged[fqdn_lower], record):
                    self.log(f"Lease overriding existing record for {record.get('fqdn', fqdn_lower)}", syslog.LOG_DEBUG)
                    merged[fqdn_lower] = record
                # else: keep existing (static wins or existing is newer)
            else:
                merged[fqdn_lower] = record

        return merged

    def unbound_control(self, *args):
        """
        Execute unbound-control command.
        Tracks consecutive failures and enters failed state if threshold exceeded.
        """
        cmd = [UNBOUND_CONTROL, '-c', UNBOUND_CONF] + list(args)
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
            if result.returncode != 0:
                stderr = result.stderr.strip()
                self.consecutive_failures += 1
                if 'control-enable' in stderr or 'Connection refused' in stderr:
                    if self.consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
                        self.enter_failed_state(
                            FailureReason.UNBOUND_CONTROL_DISABLED,
                            "Unbound remote control not enabled or Unbound stopped"
                        )
                    else:
                        self.log(
                            f"unbound-control failed ({self.consecutive_failures}/"
                            f"{MAX_CONSECUTIVE_FAILURES}): {stderr}", syslog.LOG_WARNING
                        )
                else:
                    self.log(f"unbound-control error: {stderr}", syslog.LOG_WARNING)
                return False
            # Success - reset failure counter
            self.consecutive_failures = 0
            return True
        except subprocess.TimeoutExpired:
            self.consecutive_failures += 1
            if self.consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
                self.enter_failed_state(
                    FailureReason.UNBOUND_NOT_RUNNING,
                    "Unbound repeatedly timing out"
                )
            else:
                self.log(
                    f"unbound-control timeout ({self.consecutive_failures}/"
                    f"{MAX_CONSECUTIVE_FAILURES})", syslog.LOG_WARNING
                )
            return False
        except Exception as e:
            self.consecutive_failures += 1
            if self.consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
                self.enter_failed_state(
                    FailureReason.UNBOUND_NOT_RUNNING,
                    f"Repeated unbound-control failures: {e}"
                )
            else:
                self.log(
                    f"unbound-control exception ({self.consecutive_failures}/"
                    f"{MAX_CONSECUTIVE_FAILURES}): {e}", syslog.LOG_ERR
                )
            return False

    def add_dns_record(self, fqdn_key, record):
        """Add TXT marker, A, and PTR records to Unbound in a single batch call."""
        ip = record['ip']
        source = record['source']
        # Use lowercase fqdn for DNS (case-insensitive)
        fqdn = fqdn_key.lower()
        display_fqdn = record.get('fqdn', fqdn)  # Original case for logging
        ttl = 3600 if source == 'static' else 300
        ptr_name = '.'.join(reversed(ip.split('.'))) + '.in-addr.arpa'

        # Batch all records: TXT first (marker), then A, then PTR
        # Note: trailing newline required - without it, EOF gets interpreted as part of the record
        txt_value = f"{MANAGED_MARKER};source={source}"
        records = '\n'.join([
            f'{fqdn}. {ttl} IN TXT "{txt_value}"',
            f'{fqdn}. {ttl} IN A {ip}',
            f'{ptr_name}. {ttl} IN PTR {fqdn}.'
        ]) + '\n'

        try:
            result = subprocess.run(
                [UNBOUND_CONTROL, '-c', UNBOUND_CONF, 'local_datas'],
                input=records, text=True, capture_output=True, timeout=10
            )
            if result.returncode != 0:
                self.log(f"Failed to add records for {display_fqdn}: {result.stderr.strip()}", syslog.LOG_ERR)
                self.consecutive_failures += 1
                return False
        except Exception as e:
            self.log(f"Exception adding records for {display_fqdn}: {e}", syslog.LOG_ERR)
            self.consecutive_failures += 1
            return False

        self.consecutive_failures = 0
        self.log(f"Added record ({source}): {display_fqdn} -> {ip}")
        self.registered_records[fqdn_key] = record
        # Queue for verification after delay
        self.pending_verification[fqdn_key] = (record, time.time())
        return True

    def verify_pending_records(self):
        """Verify records that were added VERIFICATION_DELAY seconds ago."""
        if not self.pending_verification:
            return

        now = time.time()
        verified = []
        failed = []

        for fqdn_key, (record, added_time) in list(self.pending_verification.items()):
            if now - added_time < VERIFICATION_DELAY:
                continue  # Not ready for verification yet

            display_fqdn = record.get('fqdn', fqdn_key)
            # Query Unbound for this record (use lowercase)
            try:
                result = subprocess.run(
                    [UNBOUND_CONTROL, '-c', UNBOUND_CONF, 'lookup', fqdn_key.lower()],
                    capture_output=True, text=True, timeout=5
                )
                # Check if our IP is in the response
                if record['ip'] in result.stdout:
                    verified.append(fqdn_key)
                else:
                    failed.append((fqdn_key, record))
            except Exception as e:
                self.log(f"Verification lookup failed for {display_fqdn}: {e}", syslog.LOG_WARNING)
                failed.append((fqdn_key, record))

        # Remove verified from pending
        for fqdn_key in verified:
            del self.pending_verification[fqdn_key]

        # Re-add failed records
        for fqdn_key, record in failed:
            del self.pending_verification[fqdn_key]
            display_fqdn = record.get('fqdn', fqdn_key)
            self.log(f"Verification failed for {display_fqdn}, re-adding", syslog.LOG_WARNING)
            # Remove from registered so add_dns_record can re-add
            self.registered_records.pop(fqdn_key, None)
            self.add_dns_record(fqdn_key, record)

    def get_managed_fqdns_from_unbound(self):
        """
        Query Unbound for all FQDNs we manage (identified by TXT marker).
        Returns dict of fqdn -> {'ip': str, 'has_ptr': bool} for records with our marker.
        """
        managed = {}
        try:
            result = subprocess.run(
                [UNBOUND_CONTROL, '-c', UNBOUND_CONF, 'list_local_data'],
                capture_output=True, text=True, timeout=30
            )
            if result.returncode != 0:
                self.log(f"Failed to list local data: {result.stderr.strip()}", syslog.LOG_ERR)
                return managed

            # Parse output: "name. TTL IN TYPE value"
            txt_fqdns = set()  # FQDNs with our marker
            a_records = {}     # fqdn -> ip
            ptr_targets = set()  # Set of FQDNs that have PTR records pointing to them

            for line in result.stdout.strip().split('\n'):
                if not line:
                    continue
                parts = line.split()
                if len(parts) < 5:
                    continue

                # Normalize to lowercase for case-insensitive comparison
                name = parts[0].rstrip('.').lower()
                rtype = parts[3]

                if rtype == 'TXT' and MANAGED_MARKER in line:
                    txt_fqdns.add(name)
                elif rtype == 'A':
                    a_records[name] = parts[4]
                elif rtype == 'PTR':
                    # PTR value is the FQDN it points to (strip trailing dot)
                    ptr_target = parts[4].rstrip('.').lower()
                    ptr_targets.add(ptr_target)

            # Return only A records that have our TXT marker, including PTR status
            for fqdn in txt_fqdns:
                if fqdn in a_records:
                    managed[fqdn] = {
                        'ip': a_records[fqdn],
                        'has_ptr': fqdn in ptr_targets
                    }

        except Exception as e:
            self.log(f"Exception querying Unbound: {e}", syslog.LOG_ERR)

        return managed

    def remove_dns_record(self, fqdn_key, record):
        """Remove A/TXT and PTR records from Unbound in a single batch call."""
        ip = record['ip']
        # Use lowercase fqdn for DNS (case-insensitive)
        fqdn = fqdn_key.lower()
        display_fqdn = record.get('fqdn', fqdn)  # Original case for logging
        ptr_name = '.'.join(reversed(ip.split('.'))) + '.in-addr.arpa'

        # Batch removal of both names (trailing newline required)
        names = '\n'.join([f'{fqdn}.', f'{ptr_name}.']) + '\n'

        try:
            subprocess.run(
                [UNBOUND_CONTROL, '-c', UNBOUND_CONF, 'local_datas_remove'],
                input=names, text=True, capture_output=True, timeout=10
            )
        except Exception as e:
            self.log(f"Exception removing records for {display_fqdn}: {e}", syslog.LOG_ERR)

        self.log(f"Removed record: {display_fqdn} -> {ip}")
        self.registered_records.pop(fqdn_key, None)

    def sync_records(self):
        """Sync DNS records with current lease and static host state."""
        # Gather all current records from both sources with deduplication
        static_records = self.read_static_hosts()
        lease_records = self.read_leases()
        current_records = self._merge_records(static_records, lease_records)

        # Add new records or update changed records
        for fqdn, record in current_records.items():
            existing = self.registered_records.get(fqdn)
            if existing is None:
                # New record
                self.add_dns_record(fqdn, record)
            elif existing['ip'] != record['ip'] or existing['source'] != record['source']:
                # Record changed (different IP or source) - remove old, add new
                self.log(f"Updating record for {fqdn}: {existing['ip']} -> {record['ip']}")
                self.remove_dns_record(fqdn, existing)
                self.add_dns_record(fqdn, record)

        # Remove stale records
        for fqdn in list(self.registered_records.keys()):
            if fqdn not in current_records:
                self.remove_dns_record(fqdn, self.registered_records[fqdn])

    def reconcile(self):
        """
        Full reconciliation: compare Unbound state with dnsmasq state.
        Handles orphans from crashes, Unbound restarts, etc.
        """
        self.log("Running reconciliation")

        # Get what should exist (from dnsmasq)
        static_records = self.read_static_hosts()
        lease_records = self.read_leases()
        expected = self._merge_records(static_records, lease_records)

        # Get what actually exists in Unbound (with our marker)
        # Returns dict of fqdn -> {'ip': str, 'has_ptr': bool}
        actual = self.get_managed_fqdns_from_unbound()

        # Find orphans (in Unbound but not in dnsmasq)
        orphan_count = 0
        for fqdn, info in actual.items():
            if fqdn not in expected:
                self.log(f"Removing orphan: {fqdn} -> {info['ip']}")
                # Create minimal record for removal
                self.remove_dns_record(fqdn, {'ip': info['ip']})
                orphan_count += 1

        # Find missing or incomplete records (in dnsmasq but not fully in Unbound)
        missing_count = 0
        ptr_repair_count = 0
        for fqdn, record in expected.items():
            if fqdn not in actual:
                # Completely missing - add all records
                self.log(f"Adding missing: {fqdn} -> {record['ip']}")
                self.add_dns_record(fqdn, record)
                missing_count += 1
            else:
                actual_info = actual[fqdn]
                if actual_info['ip'] != record['ip']:
                    # IP mismatch - update
                    self.log(f"Fixing IP mismatch: {fqdn} {actual_info['ip']} -> {record['ip']}")
                    self.remove_dns_record(fqdn, {'ip': actual_info['ip']})
                    self.add_dns_record(fqdn, record)
                    missing_count += 1
                elif not actual_info['has_ptr']:
                    # A record exists but PTR is missing - re-add all records
                    # (local_datas is idempotent, so TXT and A will just be updated)
                    self.log(f"Repairing missing PTR for: {fqdn}")
                    self.add_dns_record(fqdn, record)
                    ptr_repair_count += 1

        # Rebuild registered_records from expected
        self.registered_records = {fqdn: record for fqdn, record in expected.items()}

        if orphan_count or missing_count or ptr_repair_count:
            self.log(f"Reconciliation complete: removed {orphan_count} orphans, "
                     f"added {missing_count} missing, repaired {ptr_repair_count} PTRs")
        else:
            self.log("Reconciliation complete: no changes needed")

    def setup_kqueue(self):
        """Set up kqueue watchers for lease, static hosts, and dnsmasq config files."""
        self.kq = select.kqueue()
        self.watched_fds = {}

        for filepath in [self.lease_file, self.static_hosts_file, self.dnsmasq_conf]:
            self._watch_file(filepath)

    def _watch_file(self, filepath):
        """Add a file to kqueue watch list."""
        if not os.path.exists(filepath):
            return

        try:
            fd = os.open(filepath, os.O_RDONLY)
            ev = select.kevent(
                fd,
                filter=select.KQ_FILTER_VNODE,
                flags=select.KQ_EV_ADD | select.KQ_EV_CLEAR,
                fflags=select.KQ_NOTE_WRITE | select.KQ_NOTE_DELETE | select.KQ_NOTE_RENAME
            )
            self.kq.control([ev], 0)
            self.watched_fds[fd] = filepath
            self.log(f"Watching {filepath} (fd={fd})")
        except OSError as e:
            self.log(f"Error watching {filepath}: {e}", syslog.LOG_ERR)

    def _rewatch_file(self, filepath):
        """Re-establish watch on a file (after delete/rename)."""
        # Remove old fd if exists
        for fd, path in list(self.watched_fds.items()):
            if path == filepath:
                try:
                    os.close(fd)
                except OSError:
                    pass
                del self.watched_fds[fd]
                break

        # Re-add watch
        self._watch_file(filepath)

    def idle_loop(self):
        """
        Idle loop for failed state.
        Stays running but does nothing until terminated.
        """
        self.log(f"Entering idle mode (reason: {self.failure_reason})")
        while self.running:
            # Sleep in chunks to respond to signals promptly
            time.sleep(60)

    def handle_signal(self, signum, frame):
        """Handle termination signals gracefully."""
        sig_name = signal.Signals(signum).name if hasattr(signal, 'Signals') else str(signum)
        self.log(f"Received signal {sig_name}, shutting down")
        self.running = False

    def run(self):
        """Main entry point with pre-flight checks and failure handling."""
        syslog.openlog('dnsmasq_watcher', syslog.LOG_PID, syslog.LOG_DAEMON)
        self.log("Starting dnsmasq lease watcher")

        # Set up signal handlers
        signal.signal(signal.SIGTERM, self.handle_signal)
        signal.signal(signal.SIGINT, self.handle_signal)

        # Load configuration first
        if not self.load_config():
            self.idle_loop()
            return

        # Load dnsmasq domain configuration
        if not self.load_dnsmasq_config():
            self.failed = True
            self.idle_loop()
            return

        # Check if service is disabled
        if not self.enabled:
            self.log("Service disabled in configuration")
            self.clear_status_file()
            return

        # Run pre-flight checks
        if not self.preflight_checks():
            self.idle_loop()
            return

        # Initial reconciliation (cleans up orphans from previous runs, adds current records)
        try:
            self.reconcile()
            self.log(f"Initial reconciliation complete: {len(self.registered_records)} records")
            last_reconcile = time.time()
        except Exception as e:
            self.enter_failed_state(
                FailureReason.UNBOUND_NOT_RUNNING,
                f"Initial reconciliation failed: {e}"
            )
            self.idle_loop()
            return

        # Check if we entered failed state during initial sync
        if self.failed:
            self.idle_loop()
            return

        # Set up file watchers
        try:
            self.setup_kqueue()
        except Exception as e:
            self.enter_failed_state(
                FailureReason.NO_KQUEUE,
                f"Failed to set up file watchers: {e}"
            )
            self.idle_loop()
            return

        self.log("Entering main watch loop")

        # Set OK status if no warnings/errors were set during init
        if self.status_level == StatusLevel.OK:
            self.set_status(StatusLevel.OK, None)

        # Main watch loop
        while self.running and not self.failed:
            try:
                # Wait for events (timeout every 60s to check for new files)
                events = self.kq.control(None, 10, 60)

                files_changed = set()
                for ev in events:
                    filepath = self.watched_fds.get(ev.ident)
                    if filepath:
                        files_changed.add(filepath)
                        # Handle file deletion/rename - need to rewatch
                        if ev.fflags & (select.KQ_NOTE_DELETE | select.KQ_NOTE_RENAME):
                            self.log(f"File {filepath} deleted/renamed, re-establishing watch")
                            time.sleep(0.5)  # Brief wait for file to be recreated
                            self._rewatch_file(filepath)

                if files_changed:
                    self.log(f"Files changed: {files_changed}")
                    # If dnsmasq.conf changed, reload domain config and do full reconcile
                    if self.dnsmasq_conf in files_changed:
                        self.log("Dnsmasq config changed, reloading domain configuration")
                        self.load_dnsmasq_config()
                        self.reconcile()
                        last_reconcile = time.time()
                    else:
                        self.sync_records()

                # Periodically check for files that may not exist yet
                for filepath in [self.lease_file, self.static_hosts_file]:
                    if filepath not in self.watched_fds.values() and os.path.exists(filepath):
                        self._watch_file(filepath)

                # Verify pending records (non-blocking, checks after VERIFICATION_DELAY)
                self.verify_pending_records()

                # Periodic reconciliation (handles Unbound restarts, missed events, etc.)
                if time.time() - last_reconcile >= RECONCILE_INTERVAL:
                    self.reconcile()
                    last_reconcile = time.time()

                # Check if we entered failed state during sync
                if self.failed:
                    break

            except Exception as e:
                self.consecutive_failures += 1
                if self.consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
                    self.enter_failed_state(
                        FailureReason.MAX_FAILURES_EXCEEDED,
                        f"Too many errors in watch loop: {e}"
                    )
                    break
                else:
                    self.log(
                        f"Error in watch loop ({self.consecutive_failures}/"
                        f"{MAX_CONSECUTIVE_FAILURES}): {e}", syslog.LOG_ERR
                    )
                    time.sleep(FAILURE_RETRY_DELAY)

        # If we exited due to failure, enter idle loop
        if self.failed:
            self.idle_loop()

        self.log("Shutting down")
        self.clear_status_file()


def main():
    parser = argparse.ArgumentParser(description='Watch dnsmasq leases and register in Unbound')
    parser.add_argument('-l', '--lease-file', default=LEASE_FILE,
                        help=f'Path to dnsmasq lease file (default: {LEASE_FILE})')
    parser.add_argument('-s', '--static-hosts', default=STATIC_HOSTS_FILE,
                        help=f'Path to static hosts file (default: {STATIC_HOSTS_FILE})')
    parser.add_argument('-f', '--foreground', action='store_true',
                        help='Run in foreground (do not daemonize)')
    parser.add_argument('-p', '--pid', default='/var/run/dnsmasq_watcher.pid',
                        help='PID file location')
    args = parser.parse_args()

    watcher = DnsmasqLeaseWatcher(
        lease_file=args.lease_file,
        static_hosts_file=args.static_hosts
    )

    if args.foreground:
        watcher.run()
    else:
        daemon = Daemonize(
            app="dnsmasq_watcher",
            pid=args.pid,
            action=watcher.run,
            foreground=False
        )
        daemon.start()


if __name__ == '__main__':
    main()
