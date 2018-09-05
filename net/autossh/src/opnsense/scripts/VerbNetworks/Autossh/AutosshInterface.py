#!/usr/local/bin/python2.7

"""
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
"""

import os
import re
import time
import json
import base64
import random
import syslog
import argparse
import subprocess
from calendar import timegm

from AutosshConfigHelper import AutosshConfigHelper, AutosshConfigHelperException


class AutosshInterfaceException(Exception):
    pass


class AutosshInterface(object):

    name = 'autossh-interface'

    ssh_config_file = None
    system_config_file = None
    data_model_file = None

    def __init__(self, ssh_config_file=None, system_config_file=None, data_model_file=None):
        syslog.openlog(self.name, logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)

        if ssh_config_file is None:
            self.ssh_config_file = '/usr/local/etc/autossh/autossh.conf'

        if system_config_file is None:
            self.system_config_file = '/conf/config.xml'

        if data_model_file is None:
            self.data_model_file = '/usr/local/opnsense/mvc/app/models/VerbNetworks/Autossh/Autossh.xml'

    def main(self):
        parser = argparse.ArgumentParser(description='Autossh tunnel management interface')
        parser.add_argument('action',
            type=str,
            choices=['key_gen', 'config_helper', 'host_keys', 'connection_status'],
            help='AutosshInterface action request'
        )

        # Actions: key_gen
        parser.add_argument('--key_type', type=str, help='Type of SSH key to generate')

        # Actions: config_helper
        parser.add_argument('--ssh_config', type=str, help='Overwrites the default ssh_config file (autossh.conf) file location')
        parser.add_argument('--system_config', type=str, help='Overwrites the default system config file (config.xml) file location')
        parser.add_argument('--data_model', type=str, help='Overwrites the default autossh data model file (Autossh.xml) file location')

        # Actions: host_keys, connection_status
        parser.add_argument('--connection_uuid', type=str, help='UUID of the Autossh tunnel connection to use')

        args = parser.parse_args()

        if args.ssh_config is not None:
            self.ssh_config_file = args.ssh_config

        if args.system_config is not None:
            self.system_config_file = args.system_config

        if args.data_model is not None:
            self.data_model_file = args.data_model

        if args.action == 'key_gen' and args.key_type is not None:
            return self.key_gen(key_type=args.key_type)

        elif args.action == 'config_helper':
            return self.config_helper()

        elif args.action == 'host_keys' and args.connection_uuid is not None:
            return self.host_keys(connection_uuid=args.connection_uuid)

        elif args.action == 'connection_status' and args.connection_uuid is not None:
            return self.connection_status(connection_uuid=args.connection_uuid)

        return {'status': 'fail', 'message': 'Unable to invoke AutosshInterface, incorrect arguments'}

    def key_gen(self, key_type, fingerprint_type='md5'):
        temp_keyfile = os.path.join(
            '/tmp',
            'autossh-keygen.{}'.format(''.join(random.choice('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') for _ in range(8)))
        )

        key_comment = 'host_id:{}'.format(self.get_system_hostid())

        key_generators = {
            'dsa1024': 'ssh-keygen -t dsa -b 1024 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'ecdsa256': 'ssh-keygen -t ecdsa -b 256 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'ecdsa384': 'ssh-keygen -t ecdsa -b 384 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'ecdsa521': 'ssh-keygen -t ecdsa -b 521 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'ed25519': 'ssh-keygen -t ed25519 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'rsa1024': 'ssh-keygen -t rsa -b 1024 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'rsa2048': 'ssh-keygen -t rsa -b 2048 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
            'rsa4096': 'ssh-keygen -t rsa -b 4096 -q -N "" -f "{}" -C "{}"'.format(temp_keyfile, key_comment),
        }

        if key_type.lower() not in key_generators.keys():
            return {'status': 'fail', 'message': 'key_type not supported', 'data': key_type}

        self.shell_command(key_generators[key_type.lower()])
        if not os.path.isfile(temp_keyfile) or not os.path.isfile(temp_keyfile + '.pub'):
            return {'status': 'fail', 'message': 'unable to correctly generate ssh material'}

        fingerprint = self.shell_command('ssh-keygen -f "{}" -l -E {}'.format(temp_keyfile + '.pub', fingerprint_type)).strip()
        if len(fingerprint) < 1:
            return {'status': 'fail', 'message': 'unable to correctly generate ssh key signature'}

        with open(temp_keyfile, 'r') as f:
            private_key_data = f.read()
        os.unlink(temp_keyfile)

        with open(temp_keyfile + '.pub', 'r') as f:
            public_key_data = f.read()
        os.unlink(temp_keyfile + '.pub')

        return {
            'status': 'success',
            'message': 'SSH keypair successfully created',
            'data': {
                'key_private': base64.b64encode(private_key_data),
                'key_public': base64.b64encode(public_key_data),
                'key_fingerprint': fingerprint,
                'timestamp': time.time()
            }
        }

    def config_helper(self):
        try:
            AutosshConfigHelper(self.ssh_config_file, self.system_config_file, self.data_model_file).process()
        except AutosshConfigHelperException as e:
            return {'status': 'fail', 'message': 'unable to config_helper the ssh_config file', 'data': str(e)}
        return {'status': 'success', 'message': 'config_helper of ssh_config file complete'}

    def host_keys(self, connection_uuid, filemask='/var/db/autossh/{}.known_hosts'):
        filename = filemask.format(connection_uuid)
        if not os.path.isfile(filename):
            return {
                'status': 'fail',
                'message': 'No known_hosts file found for {}, is this a new connection?'.format(connection_uuid),
            }
        with open(filename, 'r') as f:
            known_host_lines = f.readlines()

        known_host_keys = []
        for known_host_line in known_host_lines:
            values = known_host_line.rstrip().split(' ')
            values.pop(0)
            known_host_keys.append(' '.join(values))

        return {
            'status': 'success',
            'message': 'Found {} host keys found for {}'.format(len(known_host_keys), connection_uuid),
            'data': known_host_keys
        }

    def connection_status(self, connection_uuid, filemask='/var/run/autossh.{}.{}'):

        status = {
            'enabled': False,
            'pids': {
                'daemon': None,
                'autossh': None,
                'ssh': None
            },
            'starts': None,
            'uptime': None,
            'last_healthy': None,
            'tunnel_interface': None
        }

        infofile = filemask.format(connection_uuid, 'info')
        pidfile = filemask.format(connection_uuid, 'pid')

        # enabled
        if os.path.isfile(self.ssh_config_file):
            with open(self.ssh_config_file, 'r') as f:
                for line in f.readlines():
                    if line.startswith('Host ') and connection_uuid in line:
                        status['enabled'] = True
                        break

        # pids - daemon
        daemon_command = None
        if os.path.isfile(pidfile):
            with open(pidfile, 'r') as f:
                pid = f.read()
                daemon_command = self.get_pid_command(pid)
                if daemon_command is not None and len(daemon_command) > 0:
                    status['pids']['daemon'] = int(pid)

        # pids - autossh
        autossh_command = None
        if daemon_command is not None and len(daemon_command) > 0:
            result = re.search('\[(.*)\]', daemon_command)
            if result is not None:
                pid = result.group(1)
                autossh_command = self.get_pid_command(pid)
                if autossh_command is not None and len(autossh_command) > 0:
                    status['pids']['autossh'] = int(pid)

        # pids - ssh & starts
        if autossh_command is not None and len(autossh_command) > 0:
            result = re.search('parent of (\d+?) \((\d+?)\) ', autossh_command)
            if result is not None:
                pid = result.group(1)
                status['starts'] = int(result.group(2))
                ssh_command = self.get_pid_command(pid)
                if ssh_command is not None and len(ssh_command) > 0:
                    status['pids']['ssh'] = int(pid)

        # uptime & tunnel_interface
        if os.path.isfile(infofile):
            with open(infofile, 'r') as f:
                for infoline in f.readlines():
                    if infoline.startswith('timestamp'):
                        parts = infoline.strip().split(' ')
                        status['uptime'] = int(time.time() - int(parts[1]))
                    elif infoline.startswith('tunnel_interface') and 'NONE' not in infoline:
                        parts = infoline.strip().split(' ')
                        status['tunnel_interface'] = parts[1]

        # last_healthy
        log_search_command = 'clog /var/log/autossh.log | grep "autosshd\[{}\]" | grep "autossh\[{}\]" | ' \
                             'grep "connection ok" | tail -n1'.format(status['pids']['daemon'], status['pids']['autossh'])
        log_line = self.shell_command(log_search_command)
        result = re.search('^(.*) (.*?) autosshd\[', log_line)
        if result is not None:
            status['last_healthy'] = result.group(1)

        return {
            'status': 'success',
            'message': 'Connection data collected',
            'data': status
        }

    def get_system_hostid(self):
        hostid = '00000000-0000-0000-0000-000000000000'
        if os.path.isfile('/etc/hostid'):
            with open('/etc/hostid', 'r') as f:
                hostid = f.read().strip()
        return hostid

    def shell_command(self, command):
        process = subprocess.Popen(command, shell=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        stdout, stderr = process.communicate()
        if stderr is not None and len(stderr) > 0:
            raise AutosshInterfaceException(stderr.strip())
        return stdout

    def get_pid_command(self, pid):
        return self.shell_command('ps -o command= -p {}'.format(pid))

    def response_output(self, message, status='success', data=None, log_fails=True):

        if status.lower() == 'okay' or status.lower() == 'ok':
            status = 'success'

        response_data = {
            'status': status.lower(),
            'message': message,
            'timestamp': self.normalize_timestamp(time.time())
        }

        if data is not None:
            response_data['data'] = data

        print (json.dumps(response_data))

        if log_fails is True and status == 'fail':
            self.log('error', message, data=data)

        return response_data

    def normalize_timestamp(self, input):

        # oh just kill me now :( every part of this is just horrible

        try:
            input = str(input)

            # 2018-08-04T07:46:37.000Z
            if '-' in input and 'T' in input and ':' in input and '.' in input and input.endswith('Z'):
                t = time.strptime(input.split('.')[0], '%Y-%m-%dT%H:%M:%S')

            # 2018-08-14 07:28:05+00:00
            elif '-' in input and ' ' in input and ':' in input and '.' not in input and input.endswith('+00:00'):
                t = time.strptime(input, '%Y-%m-%d %H:%M:%S+00:00')

            # 2018-08-04T07:44:45Z
            elif '-' in input and 'T' in input and ':' in input and '.' not in input and input.endswith('Z'):
                t = time.strptime(input, '%Y-%m-%dT%H:%M:%SZ')

            # 20180804T074445Z
            elif '-' not in input and 'T' in input and ':' not in input and '.' not in input and input.endswith('Z'):
                t = time.strptime(input, '%Y%m%dT%H%M%SZ')

            # 20180804Z074445
            elif '-' not in input and 'T' not in input and ':' not in input and '.' not in input and 'Z' in input:
                t = time.strptime(input, '%Y%m%dZ%H%M%S')

            # 1533373930.983988
            elif '-' not in input and 'T' not in input and ':' not in input and '.' in input and 'Z' not in input:
                t = time.gmtime(int(input.split('.')[0]))

            # 1533378888
            elif '-' not in input and 'T' not in input and ':' not in input and '.' not in input and 'Z' not in input:
                t = time.gmtime(int(input))

        except ValueError as e:
            return input

        return time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(timegm(t)))

    def log(self, level, message, data=None):
        level = level.lower()

        if level not in ['debug', 'info', 'warn', 'error', 'fatal']:
            level = 'info'

        log_event = {
            'level': level,
            'message': message,
            'timestamp': time.strftime("%Y%m%dT%H%M%SZ", time.gmtime())
        }
        syslog_message = message

        if data is not None:
            log_event['data'] = data
            if type(data) is dict and 'data' in data:
                syslog_message += ' {}'.format(json.dumps(data['data']))
            else:
                syslog_message += ' {}'.format(json.dumps(data))

        syslog_map = {
            'debug': syslog.LOG_DEBUG,
            'info': syslog.LOG_INFO,
            'warn': syslog.LOG_WARNING,
            'error': syslog.LOG_ERR,
            'fatal': syslog.LOG_CRIT,
        }

        syslog.syslog(syslog_map[level], syslog_message)
        return log_event


if __name__ == '__main__':
    Interface = AutosshInterface()
    Interface.response_output(**Interface.main())
