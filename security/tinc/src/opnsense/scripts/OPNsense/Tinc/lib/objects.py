"""
    Copyright (c) 2016 Ad Schellevis <ad@opnsense.org>
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

class NetwConfObject(object):
    def __init__(self):
        self._payload = dict()
        self._payload['hostname'] = None
        self._payload['network'] = None
        self._payload['address'] = ''
        self._payload['port'] = None

    def is_valid(self):
        for key in self._payload:
            if self._payload[key] is None:
                return False
        return True

    def set(self, prop, value):
        if ('set_%s' % prop) in dir(self):
            getattr(self,'set_%s' % prop)(value)
        elif value.text is not None:
            # default copy propery to _payload
            self._payload[prop] = value.text

    def get_hostname(self):
        return self._payload['hostname']

    def get_network(self):
        return self._payload['network']

    def get_basepath(self):
        return '/usr/local/etc/tinc/%(network)s' % self._payload

    def get_addresses(self):
        if not self._payload['address']:
            return
        yield from self._payload['address'].split(',')

class Network(NetwConfObject):
    def __init__(self):
        super(Network, self).__init__()
        self._payload['id'] = None
        self._payload['privkey'] = None
        self._payload['intaddress'] = None
        self._payload['debuglevel'] = 'd0'
        self._payload['mode'] = 'switch'
        self._payload['PMTUDiscovery'] = 'yes'
        self._payload['StrictSubnets'] = 'no'
        self._hosts = list()

    def get_id(self):
        return self._payload['id']

    def get_local_address(self):
        return self._payload['intaddress']

    def get_mode(self):
        return self._payload['mode']

    def get_debuglevel(self):
        return self._payload['debuglevel'][1] if len(self._payload['debuglevel']) > 1 else '0'

    def set_hosts(self, hosts):
        for host in hosts:
            hostObj = Host()
            for host_prop in host:
                hostObj.set(host_prop.tag, host_prop)
            self._hosts.append(hostObj)

    def set_PMTUDiscovery(self, value):
        self._payload['PMTUDiscovery'] = 'no' if value.text != '1' else 'yes'

    def set_StrictSubnets(self, value):
        self._payload['StrictSubnets'] = 'no' if value.text != '1' else 'yes'

    def config_text(self):
        result = list()
        result.append('AddressFamily=any')
        result.append('Mode=%(mode)s' % self._payload)
        result.append('PMTUDiscovery=%(PMTUDiscovery)s' % self._payload)
        result.append('Port=%(port)s' % self._payload)
        result.append('PingTimeout=%(pingtimeout)s' % self._payload)
        result.append('StrictSubnets=%(StrictSubnets)s' % self._payload)
        for host in self._hosts:
            if host.connect_to_this_host():
                result.append('ConnectTo = %s' % (host.get_hostname(),))
        result.append('Device=/dev/tinc%(id)s' % self._payload)
        result.append('Name=%(hostname)s' % self._payload)
        return '\n'.join(result) + '\n'

    def filename(self):
        return self.get_basepath() + '/tinc.conf'

    def privkey(self):
        return {'filename': self.get_basepath() + '/rsa_key.priv', 'content': self._payload['privkey']}

    def all(self):
        yield self
        for host in self._hosts:
            yield host

class Host(NetwConfObject):
    def __init__(self):
        super(Host, self).__init__()
        self._connectTo = "0"
        self._payload['pubkey'] = None
        self._payload['cipher'] = None

    def connect_to_this_host(self):
        if self.is_valid() and self._payload['address'] and self._connectTo == "1":
            return True
        else:
            return False

    def set_connectto(self, value):
        self._connectTo = value.text

    def get_subnets(self):
        if not 'subnet' in self._payload:
            return
        yield from self._payload['subnet'].split(',')

    def config_text(self):
        result = list()
        for address in self.get_addresses():
            result.append('Address=%s %s' % (address, self._payload['port']))
        for network in self.get_subnets():
            result.append('Subnet=%s' % network)
        result.append('Cipher=%(cipher)s'%self._payload)
        result.append('Digest=sha256')
        result.append(self._payload['pubkey'])
        return '\n'.join(result) + '\n'

    def filename(self):
        return '%s/hosts/%s' % (self.get_basepath(), self._payload['hostname'])
