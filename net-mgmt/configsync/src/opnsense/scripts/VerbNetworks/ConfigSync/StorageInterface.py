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
import json
import gzip
import time
import random
import shutil
import hashlib
import StringIO
import subprocess
import ConfigParser
from calendar import timegm

from xmltodict import xmltodict


class StorageInterfaceException(Exception):
    pass


class StorageInterface(object):

    system_config_backups_path='/conf/backup'
    system_config_current_file='/conf/config.xml'
    service_config_file='/usr/local/etc/configsync/configsync.conf'

    def read_consistent(self, filename, return_meta=None, __attempt=1, __max_attempts=16):

        if __attempt >= __max_attempts:
            raise StorageInterfaceException('Too many retries while attempting to call read_consistent_local_file()')

        if return_meta is None:
            return_meta = []

        digest_method = 'sha256'
        if 'md5' in return_meta:
            digest_method = 'md5'
        if 'sha1' in return_meta:
            digest_method = 'sha1'

        random_chars = ''.join(random.choice('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') for _ in range(8))
        tempfile_0 = '/tmp/read_consistent.{}.0'.format(random_chars)
        tempfile_1 = '/tmp/read_consistent.{}.1'.format(random_chars)

        subprocess.call(['/bin/sync'])
        shutil.copy(filename, tempfile_0)
        digest_0 = self.file_digest(tempfile_0, digest_method=digest_method, output_type='hexdigest')
        mtime_0 = os.path.getmtime(filename)
        bytes_0 = os.path.getsize(filename)

        time.sleep(0.054193)

        subprocess.call(['/bin/sync'])
        shutil.copy(filename, tempfile_1)
        digest_1 = self.file_digest(tempfile_1, digest_method=digest_method, output_type='hexdigest')

        if digest_0 != digest_1:
            os.unlink(tempfile_0)
            os.unlink(tempfile_1)
            time.sleep(0.104729)
            return self.read_consistent(filename, __attempt=__attempt + 1)

        with open(tempfile_0, 'rb') as f:
            content = f.read()

        os.unlink(tempfile_0)
        os.unlink(tempfile_1)
        if return_meta is not None:
            meta = {}
            if 'md5' in return_meta:
                meta['md5'] = digest_0
            if 'sha1' in return_meta:
                meta['sha1'] = digest_0
            if 'sha256' in return_meta:
                meta['sha256'] = digest_0
            if 'mtime' in return_meta:
                meta['mtime'] = mtime_0
            if 'bytes' in return_meta:
                meta['bytes'] = bytes_0
            return content, meta
        return content

    def file_digest(self, filename, digest_method='md5', output_type='hexdigest', buffer_size=4096):

        if digest_method == 'md5':
            digest = hashlib.md5()
        elif digest_method == 'sha1':
            digest = hashlib.sha1()
        elif digest_method == 'sha256':
            digest = hashlib.sha256()
        else:
            raise StorageInterfaceException('Unsupported digest method', digest_method)

        with open(filename, 'rb') as f:
            while True:
                data = f.read(buffer_size)
                if not data:
                    break
                digest.update(data)

        if output_type == 'base64':
            return digest.digest().encode('base64').strip()
        elif output_type == 'hexdigest':
            return digest.hexdigest()
        raise StorageInterfaceException('Unsupported output type', output_type)

    def content_digest(self, content, digest_method='md5', output_type='hexdigest', buffer_size=4096):

        if digest_method == 'md5':
            digest = hashlib.md5()
        elif digest_method == 'sha1':
            digest = hashlib.sha1()
        elif digest_method == 'sha256':
            digest = hashlib.sha256()
        else:
            raise StorageInterfaceException('Unsupported digest method', digest_method)

        digest.update(content)

        if output_type == 'base64':
            return digest.digest().encode('base64').strip()
        elif output_type == 'hexdigest':
            return digest.hexdigest()
        raise StorageInterfaceException('Unsupported output type', output_type)

    def get_system_hostid(self):
        hostid = '00000000-0000-0000-0000-000000000000'
        if os.path.isfile('/etc/hostid'):
            with open('/etc/hostid', 'r') as f:
                hostid = f.read().strip()
        return hostid

    def get_system_hostname(self):
        return subprocess.check_output(['/bin/hostname', '-s']).strip().lower()

    def gzip_content(self, content):
        out = StringIO.StringIO()
        with gzip.GzipFile(fileobj=out, mode='wb') as f:
            f.write(content)
        return out.getvalue()

    def xml_to_dict(self, xml):
        return xmltodict.parse(xml_input=xml)

    def read_config(self, section):
        if not os.path.isfile(self.service_config_file):
            return None

        config = ConfigParser.ConfigParser()
        config.read(self.service_config_file)

        if section not in config.sections():
            return None

        configuration = {}
        for option, value in config.items(section):
            configuration[str(option).lower()] = str(value)

        return configuration

    def response_output(self, message, status='success', data=None):

        if status.lower() == 'okay' or status.lower() == 'ok':
            status = 'success'

        response_data = {
            'status': status.lower(),
            'message': message,
            'timestamp': time.time()
        }

        if data is not None:
            response_data['data'] = data

        print (json.dumps(response_data))
        return response_data

    def normalize_timestamp(self, input):

        # oh just kill me now :( every part of this is just horrible

        try:
            input = str(input)

            # 2018-08-04T07:46:37.000Z
            if '-' in input and 'T' in input and ':' in input and '.' in input and input.endswith('Z'):
                t = time.strptime(input.split('.')[0], '%Y-%m-%dT%H:%M:%S')

            # 2018-08-04T07:44:45Z
            elif '-' in input and 'T' in input and ':' in input and '.' not in input and input.endswith('Z'):
                t = time.strptime(input, '%Y-%m-%dT%H:%M:%SZ')

            # 20180804T074445Z
            elif '-' not in input and 'T' in input and ':' not in input and '.' not in input and input.endswith(
                    'Z'):
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

        #return time.strftime('%Y-%m-%d %H:%M:%S', t)
        #return time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(time.mktime(t)))
        return time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(timegm(t)))

    def get_cache(self, path, keydata):
        if not os.path.isdir(os.path.dirname(path)):
            return None
        fullpath = path + '.' + self.gen_cache_key(keydata)
        if not os.path.isfile(fullpath):
            return None
        with open(fullpath, 'rb') as f:
            data = json.load(f)
        return data

    def set_cache(self, data, path, keydata):
        if not os.path.isdir(os.path.dirname(path)):
            return False
        fullpath = path + '.' + self.gen_cache_key(keydata)
        with open(fullpath, 'wb') as f:
            json.dump(data, f)
        return True

    def gen_cache_key(self, keydata):
        return self.content_digest(json.dumps(keydata))
