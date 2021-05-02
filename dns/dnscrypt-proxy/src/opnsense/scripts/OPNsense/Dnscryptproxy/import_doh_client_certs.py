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

import os
import sys
import json
import configparser
import xml.etree.ElementTree

result = {}

# Parse the dnscrypt-proxy config for some settings.
cnf = configparser.ConfigParser()
cnf.read('/usr/local/opnsense/service/conf/dnscrypt-proxy.conf')

# Create a dictionary for the key:value pairs.
plugin = dict()
for envKey in cnf.items('plugin'):
    plugin[envKey[0]] = envKey[1]


def error_out(message):
    """ Error handling function.
    """
    result['error'] = message
    result['status'] = "failed"
    print(json.dumps(result, indent=4))
    sys.exit()


def main():
    """ Main function for outputing the certificates to files.
    """
    # //$cache_files = array();
    # //$cmd =
    #     '/usr/local/opnsense/scripts/OPNsense/Dnscryptproxy/get-relays.py';

    # $plugin_name = 'dnscrypt-proxy';
    # $plugin_dir = '/usr/local/etc/dnscrypt-proxy';

    client_cert_dir = "doh_client_x509_auth"
    client_cert_suffix = "-client_cert.pem"
    client_cert_key_suffix = "-client_cert_key.pem"
    root_ca_cert_suffix = "-root_ca_cert.pem"

    # // Pre-set this to an error message in the case of unexpected
    # interruption
    # $result = 'Error';
    # // This bit came from the captive portal plugin, adapted for use here.

    # // Get the config.
    # $configObj = Config::getInstance()->object();

    # Parse the config.xml
    tree = xml.etree.ElementTree.parse('/conf/config.xml')
    root_node = tree.getroot()

    # <creds uuid="57cddf7c-f383-4b0d-8d2c-e95e4efee5ea">
    #   <enabled>0</enabled>
    #   <server_name>acsacsar-ams-ipv4</server_name>
    #   <client_cert>-----BEGIN CERTIFICATE-----

    # Set up our list, and walk the tree to our desired node.
    creds = {}
    config_path = "./OPNsense/" \
        + plugin['config_node'] \
        + "/doh_client_x509_auth/creds/[enabled='1']"

    # Iterate through all enabled creds.
    for node in root_node.findall(config_path):
        children = {}
        # Add each sub element as a key:value pair to the children dict.
        for childnode in node:
            children.update({
                childnode.tag:
                    childnode.text})

        # Add our children array with the uuid of the current node as the key.
        creds.update({node.get('uuid'): children})

    if not os.path.isdir(plugin['conf_dir'] + "/" + client_cert_dir):
        os.mkdir(plugin['conf_dir'] + "/" + client_cert_dir, 0o0750)

    for cred in creds:
        # Iterate through each creds entry, and create the files for each
        # cert/key..

        # Open and write out our files if we have something to write.
        if creds[cred]['client_cert']:
            with open(
                plugin['conf_dir'] + '/' + client_cert_dir + '/' + cred
                + client_cert_suffix,
                    'w'
            ) as file:
                file.write(creds[cred]['client_cert'])
                file.close()

        # Open and write out our files if we have something to write.
        if creds[cred]['client_cert_key']:
            with open(
                plugin['conf_dir'] + '/' + client_cert_dir + '/' + cred
                + client_cert_key_suffix,
                'w'
            ) as file:
                file.write(creds[cred]['client_cert_key'])
                file.close()

        # Open and write out our files if we have something to write.
        if creds[cred]['root_ca']:
            with open(
                plugin['conf_dir'] + '/' + client_cert_dir + '/' + cred
                + root_ca_cert_suffix,
                'w'
            ) as file:
                file.write(creds[cred]['root_ca'])
                file.close()

    result['status'] = "OK"
    print(result['status'])


if __name__ == '__main__':
    main()
