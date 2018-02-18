#!/usr/bin/env python2.7
# -*- coding: utf-8 -*-

#~ Copyright © 2017 Giuseppe De Marco <giuseppe.demarco@unical.it>
#~ All rights reserved.
#~
#~ Redistribution and use in source and binary forms, with or without modification,
#~ are permitted provided that the following conditions are met:
#~
#~ 1.  Redistributions of source code must retain the above copyright notice,
#~ this list of conditions and the following disclaimer.
#~
#~ 2.  Redistributions in binary form must reproduce the above copyright notice,
#~ this list of conditions and the following disclaimer in the documentation
#~ and/or other materials provided with the distribution.
#~
#~ THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
#~ INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
#~ AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
#~ AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
#~ OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
#~ SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
#~ INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
#~ CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
#~ ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
#~ POSSIBILITY OF SUCH DAMAGE.

import socket
import fcntl
import struct
import array
import subprocess

# get_all_interfaces
from collections import namedtuple
import re
import subprocess
import json

# From linux/sockios.h
#~ SIOCGIFCONF = 0x8912
#~ SIOCGIFINDEX = 0x8933
SIOCGIFFLAGS =  0x8913
#~ SIOCSIFFLAGS =  0x8914
SIOCGIFHWADDR = 0x8927
SIOCSIFHWADDR = 0x8924
SIOCGIFADDR = 0x8915
#~ SIOCSIFADDR = 0x8916
#~ SIOCGIFNETMASK = 0x891B
#~ SIOCSIFNETMASK = 0x891C
#~ SIOCETHTOOL = 0x8946

# From linux/if.h
IFF_UP       = 0x1


# From linux/socket.h
AF_UNIX      = 1
AF_INET      = 2

class IPtools(object):

    @staticmethod
    def get_ip_address(ifname):
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        return socket.inet_ntoa(fcntl.ioctl(
            s.fileno(),
            SIOCGIFADDR,
            struct.pack('256s', ifname[:15])
        )[20:24])

    @staticmethod
    def get_netmask(ifname):
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        return socket.inet_ntoa(fcntl.ioctl(
                s.fileno(),
                35099,
                struct.pack('256s', ifname))[20:24])

    @staticmethod
    def is_up(ifname):
        ''' Return True if the interface is up, False otherwise. '''
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        # Get existing device flags
        ifreq = struct.pack('16sh', ifname, 0)
        flags = struct.unpack('16sh',
            fcntl.ioctl(s.fileno(),
                        SIOCGIFFLAGS,
                        ifreq))[1]

        # Set new flags
        if flags & IFF_UP:
            return True
        else:
            return False

    @staticmethod
    def get_mac(ifname):
        ''' Obtain the device's mac address. '''
        # python2 only
        ifname = str.encode(ifname)
        #
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        ifreq = struct.pack('16sH14s', ifname, AF_UNIX, b'\x00'*14)
        res = fcntl.ioctl(s.fileno(), SIOCGIFHWADDR, ifreq)
        address = struct.unpack('16sH14s', res)[2]
        mac = struct.unpack('6B8x', address)

        return ":".join(['%02X' % i for i in mac])

    @staticmethod
    def set_mac(ifname, newmac):
        ''' Set the device's mac address. Device must be down for this to
            succeed. '''
        # python2 only
        ifname = str.encode(ifname)
        #
        macbytes = [int(i, 16) for i in newmac.split(':')]
        ifreq = struct.pack('16sH6B8x', ifname, AF_UNIX, *macbytes)
        fcntl.ioctl(s.fileno(), SIOCSIFHWADDR, ifreq)


    @staticmethod
    def get_interfaces(json_output=False):
        """
        Get a list of network interfaces on Linux.
        """
        name_pattern = "^(\w+)\s"
        mac_pattern = ".*?HWaddr[ ]([0-9A-Fa-f:]{17})"
        ip_pattern = ".*?\n\s+inet[ ]addr:((?:\d+\.){3}\d+)"
        pattern = re.compile("".join((name_pattern,
                                      mac_pattern,
                                      ip_pattern,
                                      )),
                             flags=re.MULTILINE)

        ifconfig = subprocess.check_output("ifconfig").decode()
        interfaces = pattern.findall(ifconfig)
        Interface = namedtuple("Interface", "name {mac} {ip}".format(
            mac="mac",
            ip="ip"))
        res = [Interface(*interface) for interface in interfaces]
        if json_output: return json.dumps(res)
        return res

    @staticmethod
    def get_interfaces_bsd(json_output=False):
        """
        Get a list of network interfaces on BSD.
        """
        name_pattern = "^(\w+).+[\n\t\s]*.*[\n\t\s]*"
        mac_pattern = ".*?ether[ ]([0-9A-Fa-f:]{17})[\n\t\s]*"
        ip_pattern = ".*?\n\s+inet[ ]((?:\d+\.){3}\d+)"
        pattern = re.compile("".join((name_pattern,
                                      mac_pattern,
                                      ip_pattern,
                                      )),
                             flags=re.MULTILINE)

        ifconfig_raw = subprocess.check_output(["ifconfig", "-u"]).decode()
        ifconfig = '\n'.join([ i for i in ifconfig_raw.splitlines() if 'inet6' not in i])
        interfaces = pattern.findall(ifconfig)
        Interface = namedtuple("Interface", "name {mac} {ip}".format(
            mac="mac",
            ip="ip"))
        res = [Interface(*interface) for interface in interfaces]
        if json_output: return json.dumps(res)
        return res

if __name__ == '__main__':
    print(IPtools.get_interfaces_bsd(json_output=True))
