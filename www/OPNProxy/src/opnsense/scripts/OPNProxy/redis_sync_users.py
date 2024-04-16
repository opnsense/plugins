#!/usr/local/bin/python3
# -*- coding: utf-8 -*-
"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
import argparse
import fcntl
import sys
import syslog
import redis
import ujson
import xml.etree.ElementTree as ET


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--redis_host', help='redis hostname (default: 127.0.0.1)', default='127.0.0.1')
    parser.add_argument('--redis_port', help='redis port number (default: 6379)', type=int, default=6379)
    parser.add_argument('username', help='optional username', nargs='?', default=None)
    args = parser.parse_args()

    # wait for other redis_sync_users sync events to complete
    lck = open('/tmp/redis_sync_users.LCK', 'w+')
    fcntl.flock(lck, fcntl.LOCK_EX)

    redisdb = redis.Redis(host=args.redis_host, port=args.redis_port, db=0)

    # ideally we would flush config data using the template system first, but since user settings may change
    # more rappidly we opt to read the raw source here.
    try:
        tree = ET.parse('/conf/config.xml')
        xmlroot = tree.getroot()
    except (FileNotFoundError, ET.ParseError):
        syslog.syslog(syslog.LOG_ERR, 'enable to open /conf/config.xml')
        sys.exit(1)

    # merge group membership into user object and flush to redis
    membership = dict()
    for group in xmlroot.findall('./system/group'):
        for member in group.findall('member'):
            if member.text not in membership:
                membership[member.text] = list()
            membership[member.text].append(group.findtext('name'))

    for user in xmlroot.findall('./system/user'):
        if args.username is None or args.username == user.findtext('name'):
            user_object = dict()
            user_object['uid'] = user.findtext('name')
            user_object['id'] = user.findtext('uid')
            user_object['applies_on'] = ["u:%s" % user.findtext('name')]
            if user_object['id'] in membership:
                for group in membership[user_object['id']]:
                    user_object['applies_on'].append("g:%s" % group)
            redisdb.set('user:%s' % user_object['uid'], ujson.dumps(user_object))
