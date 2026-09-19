#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Claudio Guareschi
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
import os
import re
import tempfile
import uuid
import xml.etree.ElementTree as ET
from pathlib import Path


CONFIG_FILENAME = '/conf/config.xml'
RUNTIME_DIRECTORY = '/var/run/ddclient'
CHECKIP_COMMAND = '/usr/local/opnsense/scripts/ddclient/checkip'
UUID_PATTERN = r'[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'
SOURCE_PATTERN = re.compile(rf'vip:([46]):({UUID_PATTERN})')
WRAPPER_PATTERN = re.compile(rf'{UUID_PATTERN}-v[46]')


def validate_uuid(value):
    if not value or re.fullmatch(UUID_PATTERN, value) is None:
        raise ValueError(f'invalid UUID: {value!r}')
    uuid.UUID(value)
    return value


def desired_wrappers(config_filename=CONFIG_FILENAME, checkip_command=CHECKIP_COMMAND):
    config = ET.parse(config_filename).getroot()
    general = config.find('./OPNsense/DynDNS/general')
    if general is None or general.findtext('enabled') != '1' or general.findtext('backend') != 'ddclient':
        return {}

    wrappers = {}
    for account in config.findall('./OPNsense/DynDNS/accounts/account'):
        if account.findtext('enabled', '0') != '1':
            continue
        source = account.findtext('interface', '')
        if not source.startswith('vip:'):
            continue

        match = SOURCE_PATTERN.fullmatch(source)
        if match is None:
            raise ValueError(f'invalid Virtual IP source: {source!r}')
        family, virtual_ip_uuid = match.groups()
        validate_uuid(virtual_ip_uuid)
        account_uuid = validate_uuid(account.get('uuid'))

        checkip = account.findtext('checkip', '')
        if (family == '4' and checkip != 'if') or (family == '6' and checkip not in ['if', 'if6']):
            raise ValueError(f'invalid check IP method for {source!r}: {checkip!r}')

        service = 'if6' if family == '6' else 'if'
        filename = f'{account_uuid}-v{family}'
        wrappers[filename] = (
            '#!/bin/sh\n'
            f"exec {checkip_command} -s {service} -i '{source}'\n"
        )
    return wrappers


def reconcile_wrappers(wrappers, runtime_directory=RUNTIME_DIRECTORY):
    runtime_path = Path(runtime_directory)
    runtime_path.mkdir(mode=0o700, parents=True, exist_ok=True)
    runtime_path.chmod(0o700)

    for filename, content in wrappers.items():
        descriptor, temporary_filename = tempfile.mkstemp(prefix='.wrapper-', dir=runtime_path)
        try:
            os.fchmod(descriptor, 0o700)
            with os.fdopen(descriptor, 'w') as wrapper:
                descriptor = None
                wrapper.write(content)
                wrapper.flush()
                os.fsync(wrapper.fileno())
            os.replace(temporary_filename, runtime_path / filename)
        finally:
            if descriptor is not None:
                os.close(descriptor)
            Path(temporary_filename).unlink(missing_ok=True)

    expected = set(wrappers)
    for entry in runtime_path.iterdir():
        if entry.name not in expected and (
            WRAPPER_PATTERN.fullmatch(entry.name) is not None or entry.name.startswith('.wrapper-')
        ):
            entry.unlink()


def main():
    parser = argparse.ArgumentParser(description='Generate ddclient CARP Virtual IP wrappers')
    parser.add_argument('-c', '--config', default=CONFIG_FILENAME)
    parser.add_argument('-d', '--directory', default=RUNTIME_DIRECTORY)
    args = parser.parse_args()
    try:
        wrappers = desired_wrappers(args.config)
    except (OSError, ET.ParseError, ValueError):
        # Never leave a previously valid wrapper usable after invalid input.
        reconcile_wrappers({}, args.directory)
        raise
    reconcile_wrappers(wrappers, args.directory)


if __name__ == '__main__':
    main()
