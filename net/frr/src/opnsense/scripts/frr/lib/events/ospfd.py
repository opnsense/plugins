"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import syslog
from configparser import ConfigParser
from ..base import BaseEventHandler


class OspfdEventHandler(BaseEventHandler):
    _config = '/usr/local/etc/frr/ospfd_carp.conf'

    @property
    def should_run(self):
        return self.vtysh.is_running('ospfd')

    def _read_config(self):
        result = dict()
        if os.path.isfile(self._config):
            cnf = ConfigParser()
            cnf.read(self._config)
            not_empty = lambda x, y: cnf.has_option(x, y) and cnf.get(x, y) != '' and cnf.get(x, y) != '0'
            for section in cnf.sections():
                if not_empty(section, 'interface') and not_empty(section, 'interface') \
                        and not_empty(section, 'demoted_cost') and not_empty(section, 'carp_depend_on'):
                    default_cost = cnf.getint(section, 'default_cost') if not_empty(section, 'default_cost') else None
                    result[cnf.get(section, 'interface')] = {
                        'demoted_cost': cnf.getint(section, 'demoted_cost'),
                        'carp_depend_on': cnf.get(section, 'carp_depend_on'),
                        'default_cost': default_cost,
                    }

        return result

    def execute(self):
        if os.path.isfile(self._config):
            ospf_interfaces = self.vtysh.execute('show ip ospf interface json')
            config_interfaces = self._read_config()
            for intf in config_interfaces:
                if 'interfaces' in ospf_interfaces and intf in ospf_interfaces['interfaces']:
                    ospf_intf_cost = ospf_interfaces['interfaces'][intf]['cost']
                    is_intf_master = self.ifstatus.address_status(config_interfaces[intf]['carp_depend_on']) == 'master'
                    is_ospf_dem = ospf_intf_cost == config_interfaces[intf]['demoted_cost']
                    if is_intf_master and is_ospf_dem:
                        # promote ospf interface
                        conf_cost = config_interfaces[intf]['default_cost']
                        if conf_cost is None:
                            syslog.syslog(
                                syslog.LOG_NOTICE, 'ospfd promote interface %s (no default cost configured).' % intf
                            )
                            self.vtysh.execute(
                                ['interface %s' % intf, 'no ip ospf cost'], translate=None, configure=True
                            )
                        elif conf_cost != ospf_intf_cost:
                            syslog.syslog(
                                syslog.LOG_NOTICE, 'ospfd promote interface %s (cost %d).' % (intf, conf_cost)
                            )
                            self.vtysh.execute(
                                ['interface %s' % intf, 'ip ospf cost %d' % conf_cost],
                                translate=None, configure=True
                            )
                    elif not is_intf_master and not is_ospf_dem:
                        # demote ospf interface
                        conf_cost = config_interfaces[intf]['demoted_cost']
                        syslog.syslog(
                            syslog.LOG_NOTICE, 'ospfd demote interface %s (cost %d).' % (intf, conf_cost)
                        )
                        self.vtysh.execute(
                            ['interface %s' % intf, 'ip ospf cost %d' % conf_cost],
                            translate=None, configure=True
                        )
