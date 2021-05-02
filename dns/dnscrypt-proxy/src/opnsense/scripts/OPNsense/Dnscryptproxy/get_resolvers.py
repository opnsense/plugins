#!/usr/local/bin/python3
# -*- coding: utf-8 -*-

"""
    Copyright (c) 2014-2019 Ad Schellevis <ad@opnsense.org>
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

    package : Dnscryptproxy
    function: returns a list or dict of the currently available relays from the
        enabled sources files.
"""

import sys
import json
import subprocess

args = sys.argv

def main():
    """ Main function for building the relay list.
    """
    if len(args) == 2:
        if args[1] == "route":
            mode = "route"
        elif args[1] == "names":
            mode = "names"
    else:
        mode = ""

    # Use a different data structure depending on expected output.
    if mode in ("names", "route"):
        resolvers = {}
    else:
        resolvers = []

    if mode in ("route", "names"):
        cmd = "/usr/local/sbin/dnscrypt-proxy " \
            + "-child -list-all " \
            + "-config /usr/local/etc/dnscrypt-proxy/dnscrypt-proxy.toml"
    else:
        cmd = "/usr/local/sbin/dnscrypt-proxy " \
            + "-child -list-all " \
            + "-json -config /usr/local/etc/dnscrypt-proxy/dnscrypt-proxy.toml"

    # Only do the rest if we have something to do.
    if cmd is not None:
        with subprocess.Popen(
                cmd, shell=True, stdout=subprocess.PIPE
                ) as sub_process:
            # out = []
            out = sub_process.communicate()

        if mode in ("route", "names"):
            resolvers_out = out[0].decode('utf-8').splitlines()
        else:
            resolvers_out = json.loads(out[0].decode('utf-8'))

        if mode == "route":
            resolvers.update({"*": "*"})

        for each in enumerate(resolvers_out):
            line = each[1]
            if len(args) == 1:
                # Set these bits integer values to
                # work with the bootgrid boolean formatter.
                for key in ['ipv6', 'dnssec', 'nolog', 'nofilter']:
                    if line.get(key) is not None:
                        # Overwrite each value with its integer version.
                        if line[key] is True:
                            line[key] = 1
                        elif line[key] is False:
                            line[key] = 0
                    else:
                        # Key was not set, set key, and assume 0 (False).
                        line[key] = 0
                # Need to populate description field if one doesn't exist.
                # This is a special case for static server definitions which
                # have no description, but populate in this list.
                # It's expected that all other attributes will
                # always be populated.
                if line.get('description') is None:
                    line['description'] = ""

            if mode in ("route", "names"):
                resolvers.update({line: line})
            else:
                resolvers.append(line)

        # take a dump
        if mode in ("route", "names"):
            with open(
                    "/tmp/dnscrypt-proxy_resolvers_"
                    + mode
                    + ".json", "w") as file:
                file.write(json.dumps(resolvers, indent=4))
                file.close()
            print(json.dumps(resolvers, indent=4))

        else:
            print(json.dumps(resolvers, indent=4))


if __name__ == '__main__':
    main()
