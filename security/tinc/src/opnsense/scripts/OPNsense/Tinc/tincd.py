#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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
    reconfigure tincd, using the supplied configuration
"""
import os
import sys
import glob
import pipes
import xml.etree.ElementTree
import shutil
import subprocess
import ipaddress
from lib import objects

def write_file(filename, content, mode=0o600):
    dirname = '/'.join(filename.split('/')[0:-1])
    if not os.path.isdir(dirname):
        os.makedirs(dirname)
    open(filename, 'w').write(content)
    os.chmod(filename, mode)

def read_config(config_filename):
    result = list()
    if os.path.isfile(config_filename):
        for network in xml.etree.ElementTree.parse(config_filename).getroot():
            Network_obj = objects.Network()
            for network_prop in network:
                Network_obj.set(network_prop.tag, network_prop)
            # check if config is complete before collecting output files
            if Network_obj.is_valid():
                # add Network to result
                result.append(Network_obj)

    return result

def deploy(config_filename):
    interfaces = subprocess.run(['/sbin/ifconfig','-l'], capture_output=True, text=True).stdout.split()
    networks = read_config(config_filename)
    # remove previous configuration
    os.system('rm -rf /usr/local/etc/tinc')
    for network in networks:
        # interface name to use
        interface_name = 'tinc%s' % network.get_id()

        # type of interface to use
        interface_type = 'tun'
        if network.get_mode() == 'switch':
            interface_type = 'tap'

        # dump Network and host config
        for conf_obj in network.all():
            if conf_obj.is_valid():
                write_file(conf_obj.filename(), conf_obj.config_text())

        # dump private key
        tmp = network.privkey()
        write_file(tmp['filename'], tmp['content'])
        with open('/etc/resolv.conf') as fin:
            write_file("%s/etc/resolv.conf" % network.get_basepath(), fin.read())

        # write tinc-up file
        interface_address = network.get_local_address()
        interface_network = ipaddress.ip_network(interface_address, False)
        interface_family = "inet6" if interface_network.version == 6 else "inet"
        interface_configd = "newipv6" if interface_network.version == 6 else "newip"

        if_up = list()
        if_up.append("#!/bin/sh")
        if_up.append("ifconfig %s %s %s" % (interface_name, interface_family, pipes.quote(interface_address)))
        if_up.append("configctl interface %s %s" % (interface_configd, interface_name))
        write_file("%s/tinc-up" % network.get_basepath(), '\n'.join(if_up) + "\n", 0o700)

        # write subnet-{up|down} scripts and ship required binaries into the chroot
        chroot_needs = set(['/bin/sh', '/sbin/route', '/libexec/ld-elf.so.1'])
        for item in list(chroot_needs):
            for line in subprocess.run(['/usr/bin/ldd', item],  capture_output=True, text=True).stdout.split('\n'):
                if line.find('=>') > 0:
                    chroot_needs.add(line.split('=>')[1].strip().split()[0])
        for filename in chroot_needs:
            os.makedirs('%s%s' % (network.get_basepath(), os.path.dirname(filename)), exist_ok=True)
            shutil.copy(filename, '%s/%s' % (network.get_basepath(), filename))
        write_file("%s/subnet-up" % network.get_basepath(), '\n'.join([
            "#!/bin/sh",
            "route add $SUBNET -iface %s\n" % interface_name
        ]), 0o700)
        write_file("%s/subnet-down" % network.get_basepath(), '\n'.join([
            "#!/bin/sh",
            "route delete $SUBNET -iface %s\n" % interface_name
        ]), 0o700)

        # configure and rename new tun device, place all in group "tinc" symlink associated tun device
        if interface_name not in interfaces:
            tundev = subprocess.run(['/sbin/ifconfig', interface_type, 'create'],
                                    capture_output=True, text=True).stdout.split()[0]
            subprocess.run(['/sbin/ifconfig',tundev,'name',interface_name])
            subprocess.run(['/sbin/ifconfig',interface_name,'group','tinc'])
            if os.path.islink('/dev/%s' % interface_name):
                os.remove('/dev/%s' % interface_name)
            os.symlink('/dev/%s' % tundev, '/dev/%s' % interface_name)
    return networks

if len(sys.argv) > 1:
    if sys.argv[1] == 'stop':
        for instance in glob.glob('/usr/local/etc/tinc/*'):
            subprocess.run(['/usr/local/sbin/tincd','-n',instance.split('/')[-1], '-k'])
            if os.path.exists('%s/tinc.conf' % instance):
                interface_name  = open('%s/tinc.conf' % instance).read().split('Device=')[-1].split()[0].split('/')[-1]
                if interface_name.startswith('tinc'):
                    subprocess.run(['/sbin/ifconfig',interface_name,'destroy'])
    elif sys.argv[1] == 'start':
        for netwrk in deploy('/usr/local/etc/tinc_deploy.xml'):
            subprocess.run(['/usr/local/sbin/tincd','-n',netwrk.get_network(), '-R', '-d', netwrk.get_debuglevel()])
