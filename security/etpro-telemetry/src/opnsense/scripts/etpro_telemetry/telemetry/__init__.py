"""
    Copyright (c) 2018-2019 Ad Schellevis <ad@opnsense.org>
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
import os
import subprocess
import tempfile
import collections
try:
    from configparser import ConfigParser
except ImportError:
    from ConfigParser import ConfigParser
import netaddr
import ujson


BASE_URL = 'https://opnsense.emergingthreats.net'
RELATED_SIDS_FILE = '/usr/local/etc/suricata/rules/telemetry_sids.txt'
UNFILTERED_OUTPUT_FIELDS = [
        'timestamp', 'flow_id', 'in_iface', 'event_type', 'vlan',
        'src_port', 'dest_port', 'proto', 'alert', 'tls', 'http', 'app_proto'
]
# remove from output, either sensitive or irrelevant
CLEANUP_OUTPUT_FIELDS = [
        'alert.category', 'alert.severity', 'alert.gid', 'alert.signature', 'alert.metadata',
        'http.http_user_agent', 'http.url', 'http.redirect'
]

def get_config(rule_update_config):
    """
    retrieve device token, since we align our telemetry data to the existing rule download feature in OPNsense
    it should be safe to assume rule-updater.config contains the token that is used.
    :param rule_update_config: path to OPNsense rule update configuration
    :return: token id or None if not found
    """
    response = collections.namedtuple('sensor', 'token')
    if os.path.exists(rule_update_config):
        cnf = ConfigParser()
        cnf.read(rule_update_config)
        if cnf.has_section('__properties__'):
            if cnf.has_option('__properties__', 'et_telemetry.token'):
                response.token = cnf.get('__properties__', 'et_telemetry.token')

    return response


class EventCollector(object):
    """ Event collector, responsible for extracting and anonymising from an eve.json stream
    """
    def __init__(self):
        self._tmp_handle = tempfile.NamedTemporaryFile()
        self._local_networks = list()
        self._our_sids = set()
        self._get_local_networks()
        self._get_our_sids()

    def _get_our_sids(self):
        """ collect sids of interest, which are part of the ET-Telemetry delivery
        :return: None
        """
        if os.path.isfile(RELATED_SIDS_FILE):
            for line in open(RELATED_SIDS_FILE, 'r'):
                if line.strip().isdigit():
                    self._our_sids.add(int(line.strip()))

    def _is_rule_of_interest(self, record):
        """ check if rule is of interest for delivery
        :param record: parsed eve log record
        :return: boolean
        """
        if not self._our_sids:
            return True
        elif 'alert' in record and 'signature_id' in record['alert']:
            if record['alert']['signature_id'] in self._our_sids:
                return True
        return False

    def _get_local_networks(self):
        """ collect local attached networks for anonymization purposes
        :return: None
        """
        if os.path.isfile('/usr/local/etc/suricata/suricata.yaml'):
            # home nets are considered local
            with open('/usr/local/etc/suricata/suricata.yaml') as f_in:
                parts = f_in.read().split('HOME_NET:')
                if len(parts) > 1:
                    for net in parts[1].split("\n")[0].strip('" [ ]').split(','):
                        try:
                            self._local_networks.append(netaddr.IPNetwork(net))
                        except netaddr.core.AddrFormatError:
                            pass

        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.call(['ifconfig', '-a'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
            output_stream.seek(0)
            for line in output_stream:
                if line.startswith(b'\tinet'):
                    parts = line.split()
                    if len(parts) > 3:
                        if parts[0] == 'inet6' and parts[2] == 'prefixlen':
                            # IPv6 addresses
                            self._local_networks.append(
                                netaddr.IPNetwork("%s/%s" % (parts[1].split('%')[0], parts[3]))
                            )
                        elif parts[0] == 'inet' and len(parts) > 3 and parts[2] == 'netmask':
                            # IPv4 addresses
                            mask = int(parts[3], 16)
                            self._local_networks.append(
                                netaddr.IPNetwork("%s/%s" % (netaddr.IPAddress(parts[1]), netaddr.IPAddress(mask)))
                            )

    def is_local_address(self, address):
        """ check if provided address is local for this device
        :param address: address (string)
        :return: boolean
        """
        addr_to_check = netaddr.IPAddress(address)
        for local_network in self._local_networks:
            if addr_to_check in local_network:
                return True
        return False

    def push(self, record):
        """ cleanup and write record
        :param record: parsed eve log record
        :return: None
        """
        if self._is_rule_of_interest(record):
            to_push = dict()
            for address in ['src_ip', 'dest_ip']:
                if address in record:
                    if self.is_local_address(record[address]):
                        if record[address].find(':') > -1:
                            # replace local IPv6 address
                            to_push[address] = 'xxxx:xxxx:%s' % ':'.join(record[address].split(':')[-2:])
                        else:
                            to_push[address] = 'xxx.xxx.xxx.%s' % record[address].split('.')[-1]
                    else:
                        # non local address
                        to_push[address] = record[address]

            # unfiltered output fields
            for attr in UNFILTERED_OUTPUT_FIELDS:
                if attr in record:
                    to_push[attr] = record[attr]

            # exclude partial fields
            for attr in CLEANUP_OUTPUT_FIELDS:
                to_push_ref = to_push
                attr_parts = attr.split('.')
                for item in attr_parts[:-1]:
                    if item in to_push_ref:
                        to_push_ref = to_push_ref[item]
                    else:
                        to_push_ref = None
                        continue
                if to_push_ref and attr_parts[-1] in to_push_ref:
                    del to_push_ref[attr_parts[-1]]

            self._tmp_handle.write(("%s\n" % ujson.dumps(to_push)).encode())

    def get(self):
        """ fetch all data from temp
        :return:
        """
        self._tmp_handle.seek(0)
        return self._tmp_handle.read()

    def __iter__(self):
        """ Iterate parsed events
        :return:
        """
        self._tmp_handle.seek(0)
        for line in self._tmp_handle:
            yield line
