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
    function: returns a list or dict of the currently available relays from the enabled sources files.
"""

import os, sys
import json
import base64
import struct
import configparser
import xml.etree.ElementTree

# Parse the dnscrypt-proxy config for some settings.
cnf = configparser.ConfigParser()
cnf.read('/usr/local/opnsense/service/conf/dnscrypt-proxy.conf')

# Create a dictionary for the key:value pairs.
plugin = dict()
for envKey in cnf.items('plugin'):
    plugin[envKey[0]] = envKey[1]

args = sys.argv

def error_out(message):
    result['error'] = message
    result['status'] = "failed"
    print(json.dumps(result, indent=4))
    sys.exit()


def main():
    global result
    result = {}

    # The only way to tell if any given resolver is actually a relay is to
    # analyze the stamp. So we go through all of sources files, and pick out
    # the relays.

    # Needs some error handling, probably should always return a json?

    dnscrypt_proxy_path = plugin['config_dir']

    if len(args) > 0:

        # Parse the config.xml
        tree = xml.etree.ElementTree.parse('/conf/config.xml')
        rootNode = tree.getroot()

        # Set up our list, and walk the tree to our desired node.
        cache_files = []
        config_path = "./OPNsense/dnscrypt-proxy/sources/source"
        for node in rootNode.findall(config_path):
            for childnode in node.iter():
                # For each source, if enabled, get the cache_file of that node, and append to our list.
                if childnode.tag == "enabled":
                    if childnode.text == "1":
                        cache_files.append(node.find("cache_file").text)

        # Iterate through our cache files, and parse for relays.
        for cache_file in cache_files:
            file = dnscrypt_proxy_path + "/" + cache_file

            if not os.path.isfile(file):
                error_out("File path {} does not exist. Exiting...".format(file))

            # Set the type of object we need depending on the type of output we would like.
            # dict is used for dropdowns
            # list is used for bootgrid
            if len(args) == 2:
                if args[1] == "names":
                    relays = {}
                else:
                    error_out("Invalid mode specified: {}".format(args[1]))
            else:
                relays = []

            with open(file) as f:
                desc_bit = False
                description = ""
                for cnt, line in enumerate(f):
                    # dnscrypt-proxy actually uses the string "## " to search for resolvers in the sources files.
                    # You can see this in the dnscrypt-proxy source code here:
                    # https://github.com/DNSCrypt/dnscrypt-proxy/blob/65f42918a1f85652ea4a378e20300d04b15ef2a8/dnscrypt-proxy/sources.go#L243
                    # This syntax is markdown for GitHub, but it's being used here to indicate a resolver stamp.
                    # It's not fool proof but should be generally reliable, and we'll be doing the same thing.
                    if desc_bit == True and line[:3] != "## " and line[:7] != "sdns://":
                        description += line
                    if line[:3] == "## ":
                        resolver_name = line[2:].strip()
                        desc_bit = True
                    if line[:7] == "sdns://":
                        # Here we parse the stamp to see what protocol the resolver is.
                        # These two trys are taken from the dnsstamps project: https://pypi.org/project/dnsstamps/
                        # We only need the protocol though, so the code is adapted for here.
                        try:
                            stamp = base64.urlsafe_b64decode(line.replace('sdns://', '') + '===')
                        except Exception as e:
                            raise Exception('Unable to unpack stamp', e)
                        try:
                            protocol = struct.unpack('<B', stamp[:1])[0]
                        except Exception as e:
                            raise Exception('Unable to consume protocol', e)
                        # The protocol number for relays is 129.
                        if protocol == 129:
                            # If we found a relay add it to the appropriate object.
                            if len(args) == 2:
                                if args[1] == "names":
                                    relays.update({resolver_name:resolver_name})
                            else:
                                relays.append({"name":resolver_name,"description":description.strip()})
                        # Reset these vars since we're done entry.
                        desc_bit = False
                        description = ""
        # Output whatever relays we found to stdout.
        print (json.dumps(relays, indent=4))


if __name__ == '__main__':
    main()
