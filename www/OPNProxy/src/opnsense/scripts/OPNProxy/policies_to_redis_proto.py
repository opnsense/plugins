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
import time
import ujson
from lib import Policy
import redis


def redis_proto_parser(*args):
    """
    https://redis.io/topics/protocol
    :return:
    """
    response = ["*%d\r\n$%d\r\n%s\r\n" % (len(args), len(args[0]), args[0])]
    for item in args[1:]:
        response.append("$%d\r\n%s\r\n" % (len(item), item))
    return "".join(response)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--redis_host',
        help='redis hostname to read keys from (default: 127.0.0.1)',
        default='127.0.0.1'
    )
    parser.add_argument(
        '--redis_port',
        help='redis port number (default: 6379)',
        type=int,
        default=6379
    )
    parser.add_argument(
        '--proxy_policies',
        help='proxy policies configuration file',
        default='/usr/local/etc/squid/proxy_policies.conf'
    )
    parser.add_argument('--output', help='output filename', default='/dev/stdout')

    cmd_args = parser.parse_args()

    try:
        lck = open('/tmp/policies_to_redis_proto.LCK', 'w+')
        fcntl.flock(lck, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except IOError:
        # already running, exit status 99
        sys.exit(99)

    policy = Policy(cmd_args.proxy_policies)

    # fetch current domain keys from redis
    try:
        existing_domains = redis.StrictRedis(
            host=cmd_args.redis_host, port=cmd_args.redis_port, db=0, decode_responses=True
        ).keys('domain:*')
    except (redis.exceptions.ConnectionError, redis.exceptions.BusyLoadingError) as e:
        existing_domains = list()

    with open(cmd_args.output, 'w') as output_stream:
        statistics = {'domains': 0, 'policies': 0, 'generated': time.time()}
        # generate delete statements for non existing keys
        for domain in existing_domains:
            domain = domain.split(':')[-1]
            if not policy.exists(domain):
                output_stream.write(redis_proto_parser("DEL", "domain:%s" % domain))

        # generate set statements for new data (upsert)
        for item in policy:
            statistics['domains'] += 1
            statistics['policies'] += len(item['items'])
            output_stream.write(redis_proto_parser("SET", "domain:%s" % item['domain'], ujson.dumps(item)))

        output_stream.write(redis_proto_parser("SET", "domain_statistics", ujson.dumps(statistics)))
