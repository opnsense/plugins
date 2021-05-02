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
import os
import re
import json
import shutil

parsed_list = []
result = {}
args = sys.argv


def error_out(message):
    """ Error handling function.
    Handle erroring out, and returning the error message in json format.
    """
    result['error'] = message
    result['status'] = "failed"
    print(json.dumps(result, indent=4))
    sys.exit()


def pattern_validator(line, lineno):
    """ Validator function for validating patterns.
        This is rudimentary and only checks that valid characters are used.
        It doesn't detect syntax errors, leaving that up to dnscrypt-proxy
        itself.
        Allowed:
          wildcard (asterisk)
          grouping (brackets)
          alphanumberic
          equals
          dash
          period
    """
    if not line.isascii():
        error_out(
            "Non-ASCII characters detected "
            + "on line: {}:".format(lineno)
            + "{}".format(line)
        )

    if bool(re.compile(r'[^a-z0-9.*-=[\]]').search(line)):
        error_out(
            "Non-allowed characters used "
            + "in whitelist rules at line: {}:".format(lineno)
            + "{}".format(line)
        )

    parsed_list.append(line)


# Perform validation on a line using whitelist rules.
def ip_validator(line, lineno):
    """ Validator function for validating patterns.
        This is rudimentary and only checks that valid characters are used.
        It doesn't detect syntax errors,
        leaving that up to dnscrypt-proxy itself.
        Allowed:
          wildcard (asterisk)
          alpha up to f (hex)
          numberic
          period
          colon
    """
    if not line.isascii():
        error_out(
            "Non-ASCII characters detected on line: {}:"
            + format(lineno)
            + "{}".format(line)
        )

    if bool(re.compile(r'[^a-f:0-9.*]').search(line)):
        error_out(
            "Non-allowed characters used "
            + "in whitelist rules at line: {}:".format(lineno)
            + "{}".format(line)
        )

    parsed_list.append(line)


def main():
    """ Main function for outputing the list.
    """
    params_len = len(sys.argv) - 1
    item_list = []

    if params_len == 2:

        mode = args[1]
        file = args[2]

        if not mode:
            error_out("No mode specified in parameters")

        if not file:
            error_out("No file specified in parameters")

        if not os.path.isfile(file):
            error_out("File path {} does not exist".format(file))

        if os.stat(file).st_size > 0:
            if mode == "blocked_names_file_manual":
                validator = \
                    pattern_validator
                dest_dir = \
                    "/usr/local/etc/dnscrypt-proxy"
                dest_file = \
                    "/usr/local/etc/dnscrypt-proxy/blocked-names-manual.txt"
            elif mode == "blocked_ips_file_manual":
                validator = \
                    ip_validator
                dest_dir = \
                    "/usr/local/etc/dnscrypt-proxy"
                dest_file = \
                    "/usr/local/etc/dnscrypt-proxy/blocked-ips-manual.txt"
            elif mode == "allowed_names_file_manual":
                validator = \
                    pattern_validator
                dest_dir = \
                    "/usr/local/etc/dnscrypt-proxy"
                dest_file = \
                    "/usr/local/etc/dnscrypt-proxy/allowed-names-manual.txt"
            elif mode == "allowed_ips_file_manual":
                validator = \
                    ip_validator
                dest_dir = \
                    "/usr/local/etc/dnscrypt-proxy"
                dest_file = \
                    "/usr/local/etc/dnscrypt-proxy/allowed-ips-manual.txt"
            elif mode == "cloaking_file_manual":
                validator = \
                    pattern_validator
                dest_dir = \
                    "/usr/local/etc/dnscrypt-proxy"
                dest_file = \
                    "/usr/local/etc/dnscrypt-proxy/cloaking-manual.txt"
            else:
                error_out("Invalid validator mode specified: {}".format(mode))

            lineno = 0
            for lineraw in open(file, 'r'):
                comment_char = '#'
                schedule_char = '@'
                delimiter_char = ' '
                pattern = lineraw.split(
                    comment_char, 1)[0].split(
                        schedule_char, 1)[0].split(
                            delimiter_char, 1)[0].strip().lower()
                lineno += 1
                # Only validate if the line has something to validate.
                if len(pattern) > 0:
                    validator(pattern, lineno)

                item_list.append(lineraw)

            # If we got this far, there were no errors with validation.

            try:
                os.makedirs(os.path.dirname(dest_dir), exist_ok=True)
                shutil.copyfile(file, dest_file)
                result['action'] = "import"
                result['status'] = "ok"
                print(json.dumps(result, indent=4))
                sys.exit()
            except OSError as err:
                error_out("Error copying file to destination: {}".format(err))
        else:
            error_out("File is empty")
    else:
        error_out("Incorrect number of paramters: {}".format(params_len))


if __name__ == '__main__':
    main()
