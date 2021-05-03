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
CLIENT_CERT_DIR = "doh_client_x509_auth"
CLIENT_CERT_SUFFIX = "-client_cert.pem"
CLIENT_CERT_KEY_SUFFIX = "-client_cert_key.pem"
ROOT_CA_CERT_SUFFIX = "-root_ca_cert.pem"

# Parse the dnscrypt-proxy config for some settings.
cnf = configparser.ConfigParser()
cnf.read(
    os.path.dirname(os.path.realpath(__file__))
    + '/../../../service/conf/dnscrypt-proxy.conf'
)
# Create a dictionary for the key:value pairs.
plugin = dict()
for envKey in cnf.items('plugin'):
    plugin[envKey[0]] = envKey[1]

DEFAULT_CONFIG = '/conf/config.xml'
DEFAULT_CONFIG_PATH = \
    "./OPNsense/" \
    + plugin['config_node'] \
    + "/doh_client_x509_auth/creds/[enabled='1']"
DEFAULT_OUTPUT_DIR = \
    plugin['conf_dir'] \
    + "/" \
    + CLIENT_CERT_DIR


def error_out(message):
    """ Error handling function.
    """
    result['error'] = message
    result['status'] = "failed"
    print(json.dumps(result, indent=4))
    sys.exit()


def write_cred(cred_content, cred_suffix, cred, output_dir=DEFAULT_OUTPUT_DIR):
    """ Write the cred out to a file.
    """
    if cred_content:
        with open(
            output_dir
            + '/'
            + cred
            + cred_suffix,
            'w'
        ) as file:
            file.write(cred_content + "\n")


def get_config_nodes(
        config=DEFAULT_CONFIG,
        xml_path=DEFAULT_CONFIG_PATH
        ):
    """ Get the nodes from the config
    """
    # Parse the config.xml
    tree = xml.etree.ElementTree.parse(config)
    root_node = tree.getroot()

    # Set up our list, and walk the tree to our desired node.
    nodes = {}

    # Iterate through all enabled creds.
    for node in root_node.findall(xml_path):
        children = {}
        # Add each sub element as a key:value pair to the children dict.
        for childnode in node:
            children.update({childnode.tag: childnode.text})

        # Add our children array with the uuid of the current node as the key.
        nodes.update({node.get('uuid'): children})

    return nodes


def main(
        config=DEFAULT_CONFIG,
        output_dir=DEFAULT_OUTPUT_DIR,
        xml_path=DEFAULT_CONFIG_PATH,
        ):
    """ Main function for outputing the certificates to files.
    """
    # Set the desired config path, and get the nodes from that path.
    creds = get_config_nodes(config, xml_path)

    # Make the directory if we have creds
    # and the directory if it doesn't exist.
    if creds and not os.path.isdir(output_dir):
        os.makedirs(output_dir, 0o0750)

    for cred in creds:
        # Write the cred out if applicable.
        write_cred(
            creds[cred]['client_cert'],
            CLIENT_CERT_SUFFIX,
            cred,
            output_dir
            )
        write_cred(
            creds[cred]['client_cert_key'],
            CLIENT_CERT_KEY_SUFFIX,
            cred,
            output_dir
            )
        write_cred(
            creds[cred]['root_ca'],
            ROOT_CA_CERT_SUFFIX,
            cred,
            output_dir
            )

    result['status'] = "OK"
    print(result['status'])


if __name__ == '__main__':
    main(
        DEFAULT_CONFIG,
        DEFAULT_OUTPUT_DIR,
        DEFAULT_CONFIG_PATH
    )
