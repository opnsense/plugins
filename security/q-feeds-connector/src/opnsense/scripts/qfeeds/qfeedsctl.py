#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Deciso B.V.
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
import sys
import ujson
from requests.exceptions import HTTPError, Timeout
from lib import QFeedsActions
from lib.api import QFeedsConfig


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--target_dir', default='/var/db/qfeeds-tables')
    parser.add_argument('-f', help='forced (auto index)' , default=False, action='store_true')
    parser.add_argument('-v', help='verbose output' , default=False, action='store_true')
    parser.add_argument('-l', help='lock operation' , default=False, action='store_true')
    parser.add_argument("action", choices=QFeedsActions.list_actions(), nargs='*')
    args = parser.parse_args()

    fhandle = None
    if args.l:
        lck_filename = '/tmp/qfeeds_prc.LCK'
        fhandle = open(lck_filename, 'a+')
        try:
            fcntl.flock(fhandle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            print('already busy, exit')
            sys.exit(0)

    if args.v:
        # verbose mode
        import http.client as http_client
        http_client.HTTPConnection.debuglevel = 1
    try:
        actions = QFeedsActions(args.target_dir, args.f)
        for action in args.action:
            for msg in getattr(actions, action)():
                print(msg)
    except HTTPError as exc:
        print('exit with HTTPError %d (%s)' % (exc.response.status_code, exc.response.text))
        if exc.response.status_code == 401 and 'update' in args.action:
            print('batch mode - wait for configuration update or timeout')
            t_start = time.time()
            while not QFeedsConfig.has_changed():
                if time.time() - t_start > 3600:
                    print('timeout waiting for config change')
                    break
                time.sleep(5)
        sys.exit(-1)
    except Timeout as exc:
        print('timeout reaching api endpoint')
        sys.exit(-1)
    except IOError as e:
        print("output filename locked or missing")
        sys.exit(-1)
    except ujson.JSONDecodeError:
        print("JSON decode error")
        sys.exit(-1)
    finally:
        if fhandle:
            fcntl.flock(fhandle, fcntl.LOCK_UN)
