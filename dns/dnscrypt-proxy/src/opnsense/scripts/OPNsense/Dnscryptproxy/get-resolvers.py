#!/usr/local/bin/python3

import os, sys
import json
import subprocess

args = sys.argv

def main():

    if len(args) == 2:
        if args[1] == "route":
            mode = "route"
        elif args[1] == "names":
            mode = "names"
    else:
        mode = ""

    if mode == "names" or mode == "route":  # Use a different data structure depending on expected output.
        resolvers = {}
    else:
        resolvers = []

    if mode == "route" or mode == "names":
            cmd = "/usr/local/sbin/dnscrypt-proxy -child -list-all -config /usr/local/etc/dnscrypt-proxy/dnscrypt-proxy.toml"
    else:
            cmd = "/usr/local/sbin/dnscrypt-proxy -child -list-all -json -config /usr/local/etc/dnscrypt-proxy/dnscrypt-proxy.toml"
    # Only do the rest if we have something to do.
    if cmd is not None:
        sp = subprocess.Popen(cmd,shell=True,stdout=subprocess.PIPE)
        rc=sp.wait
        out,err=sp.communicate()

        if mode == "route" or mode == "names":
            resolvers_out = out.decode('utf-8').splitlines()
        else:
            resolvers_out = json.loads(out.decode('utf-8'))

        if mode == "route":
            resolvers.update({"*":"*"})

        for idx, each in enumerate(resolvers_out):
            # for the dict method
            #resolvers.update({each:None})
            if len(args) == 1:
                # Set these bits integer values to work with the bootgrid boolean formatter.
                for key in ['ipv6', 'dnssec','nolog','nofilter']:
                    if each.get(key) != None:
                        if each[key] == True:  # Overwrite each value with its integer version.
                            each[key] = 1
                        elif each[key] == False:
                            each[key] = 0
                    else:
                        each[key] = 0  # Key was not set, set key, and assume 0 (False).
                # Need to populate description field if one doesn't exist.
                # This is a special case for static server definitions which
                # have no description, but populate in this list.
                # It's expected that all other attributes will always be populated.
                if each.get('description') == None:
                    each['description'] = ""

            if mode == "route" or mode == "names":
                resolvers.update({each:each})
            else:
                resolvers.append(each)

        # take a dump
        if mode == "route" or mode == "names":
            tmp_file = open("/tmp/dnscrypt-proxy_resolvers_" + mode + ".json", "w")
            tmp_file.write(json.dumps(resolvers,indent=4))
            tmp_file.close()
            print(json.dumps(resolvers,indent=4))

        else:
            print(json.dumps(resolvers,indent=4))

if __name__ == '__main__':
    main()
