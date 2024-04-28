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
import decimal
import sys
import syslog
import traceback
from urllib.parse import urlparse
import redis
import ujson
import ipaddress


class RedisAuth:
    def __init__(self, host, port):
        self._redis = redis.Redis(host=host, port=port, db=0)

    def domain_policy_iterator(self, r_fqdn):
        """ traverse domain policies
        :param r_fqdn: fqdn
        :return:
        """
        try:
            tmp = self._redis.get("domain:%s" % r_fqdn)
            if tmp:
                domain_policy = ujson.loads(tmp.decode())
            else:
                return
        except Exception as e:
            # connectivity or parse issue, log and return
            syslog.syslog(syslog.LOG_ERR, traceback.format_exc().replace('\n', ' '))
            return

        if type(domain_policy.get('items', None)) is list:
            for policy in domain_policy['items']:
                if type(policy) is dict:
                    for fieldname in ['id', 'path', 'wildcard', 'action', 'applies_on', 'source_net']:
                        if fieldname not in policy:
                            policy[fieldname] = None
                    yield policy

    def get_user(self, uid):
        if uid == "-":
            return {'applies_on': set('-')}
        try:
            tmp = self._redis.get("user:%s" % uid)
            if not tmp:
                return None
            udata = ujson.loads(tmp.decode())
            # cleanse data
            udata['applies_on'] = set(udata['applies_on']) if 'applies_on' in udata else set()
        except Exception:
            syslog.syslog(syslog.LOG_ERR, traceback.format_exc().replace('\n', ' '))
            return None

        return udata

def in_network(src, networks):
    if networks is None or type(networks) is not list or src == '-':
        return True
    try:
        src_net = ipaddress.ip_network(src)
    except ValueError:
        syslog.syslog(syslog.LOG_ERR, traceback.format_exc().replace('\n', ' '))
        return False
    for network in networks:
        try:
            if src_net.overlaps(ipaddress.ip_network(network)):
                return True
        except ValueError:
            syslog.syslog(syslog.LOG_ERR, traceback.format_exc().replace('\n', ' '))

    return False

def match_policy(acl, ident, src, method, uri, sslurlonly=False):
    # default response, invalid user
    match_res = {'message': "ERR message=\"no (valid) IDENT %s\"\n" % ident}
    if uri.find('://') == -1:
        base_domain = uri.split(':')[0]
        request_path = '/'
    else:
        uri_parsed = urlparse(uri)
        base_domain = uri_parsed.netloc.split(':')[0]
        request_path = uri_parsed.path if uri_parsed.path else '/'

    syslog.syslog(
        syslog.LOG_NOTICE,
        "ACL-REQ |%s| |%s| |%s| |%s| |%s| %s" % (acl, ident, src, method, uri, 'SNI only' if sslurlonly else '')
    )
    fqdn = base_domain
    user_data = redis_auth.get_user(ident)
    if user_data:
        acl_decisions = dict()
        # traverse domain upwards until either a policy is found or no matches are possible
        # matches are prioritized on best path match and accept (higher) or deny.
        while len(acl_decisions) == 0:
            for this_policy in redis_auth.domain_policy_iterator(fqdn):
                is_parent = base_domain != fqdn
                match_parent = this_policy['path'] == '/' and is_parent and this_policy['wildcard']
                match_main = request_path.find(this_policy['path']) == 0 and not is_parent
                if (match_parent or match_main) and set(this_policy['applies_on']) & user_data['applies_on']:
                    if not in_network(src, this_policy['source_net']):
                        continue
                    tp = 0 if this_policy['action'] == 'deny' else 1
                    this_prio = decimal.Decimal("%d.%d" % (len(this_policy['path']), tp))
                    acl_decisions[this_prio] = this_policy
                    acl_decisions[this_prio]['domain'] = fqdn

            if fqdn.find('.') == -1:
                if fqdn == '*':
                    break
                else:
                    # top level wildcard (add extra level)
                    fqdn = '*'
            else:
                fqdn = fqdn.split('.', maxsplit=1)[1]

        match_res['user'] = user_data
        match_res['user']['applies_on'] = list(user_data['applies_on'])

        if not sslurlonly and method.lower() == 'connect':
            # skip connect when full ssl bump is enabled
            match_res['policy'] = {'action': 'allow', 'policy_type': 'fallback'}
            match_res['message'] = "OK user=\"%s\"\n" % ident
        elif len(acl_decisions) > 0:
            acl_decision = acl_decisions[sorted(acl_decisions.keys(), reverse=True)[0]]
            match_res['policy'] = acl_decision
            if match_res['policy']['action'] == 'deny':
                 match_res['message'] = "ERR message=\"reason:%s policy_type:%s\" user=\"%s\"\n" % (
                    acl_decision['id'], acl_decision['policy_type'], ident
                )
            else:
                match_res['message'] = "OK message=\"whitelisted %s\" user=\"%s\"\n" % (acl_decision['id'], ident)
        elif ident != '-':
            # network only authentication needs an explicit policy, user-based allows by default
            match_res['policy'] = {'action': 'allow', 'policy_type': 'fallback'}
            match_res['message'] = "OK user=\"%s\"\n" % ident

    return match_res


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--test_user', help='test mode (singleshot), username')
    parser.add_argument('--test_uri', help='test mode (singleshot), uri')
    parser.add_argument('--test_src', help='test mode (singleshot), source address', default='-')
    parser.add_argument('--redis_host', help='redis hostname (default: 127.0.0.1)', default='127.0.0.1')
    parser.add_argument('--redis_port', help='redis port number (default: 6379)', type=int, default=6379)
    parser.add_argument('--sslurlonly', help='Log SNI information only enabled', action="store_true", default=False)
    parser.add_argument(
        '--no_ident',
        help='Do not expect iden/user information in the message line',
        action="store_true",
        default=False
    )

    args = parser.parse_args()
    syslog.openlog('squid', facility=syslog.LOG_LOCAL2)
    redis_auth = RedisAuth(args.redis_host, args.redis_port)
    if args.test_user and args.test_uri:
        # test mode, dump raw json object to stdout
        result = match_policy(acl='-', ident=args.test_user, src=args.test_src, method='-', uri=args.test_uri)
        print (ujson.dumps(result))
    else:
        # squid worker mode
        while True:
            try:
                # accept messages like:
                # my_ext_acl user 127.0.0.2 GET https://requested.domain/path/
                line = sys.stdin.readline().strip()
                if line == "":
                    sys.exit()
                if line:
                    try:
                        acl_parts = line.split()
                    except ValueError:
                        sys.stdout.write("ERR message=\"missing input\"\n")
                        break
                    offset = -1 if args.no_ident else 0
                    result = match_policy(
                        acl=acl_parts[0],
                        ident='-' if args.no_ident else acl_parts[1],
                        src=acl_parts[2+offset],
                        method=acl_parts[3+offset],
                        uri=acl_parts[4+offset],
                        sslurlonly=args.sslurlonly
                    )
                    sys.stdout.write(result['message'])

                sys.stdout.flush()
            except IOError:
                pass
