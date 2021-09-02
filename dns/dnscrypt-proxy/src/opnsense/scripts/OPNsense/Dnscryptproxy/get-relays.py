#!/usr/local/bin/python3

import os, sys
import json
import base64
import struct

args = sys.argv

def main():

    # The only way to tell if any given resolver is actually a relay is to
    # analyze the stamp. So we go through all of sources files, and pick out
    # the relays.

    # Needs some error handling, probably should always return a json?

    dnscrypt_proxy_path = "/usr/local/etc/dnscrypt-proxy"

    if len(args) > 1:
        for cache_file in args[1].split(","):
            file = dnscrypt_proxy_path + "/" + cache_file

            if not os.path.isfile(file):
                print("File path {} does not exist. Exiting...".format(file))
                sys.exit()

            if len(args) == 3:
                if args[2] == "names":
                    relays = {}
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
                        if protocol == 129: # This is the protocol number for relays.
                            if len(args) == 3:
                                if args[2] == "names":
                                    relays.update({resolver_name:resolver_name})
                            else:
                                relays.append({"name":resolver_name,"description":description.strip()})
                        desc_bit = False
                        description = ""
        # Output whatever relays we found to stdout.
        print (json.dumps(relays, indent=4))

if __name__ == '__main__':
    main()
