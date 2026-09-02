#!/usr/local/bin/python3

#
# Copyright (C) 2026 Sergii Bogomolov
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.
#
"""Validate the configuration the plugin would generate, without touching the one on disk.
Render into a throwaway root, validate that, and leave the live file alone. This is the same call
configd's own `template reload` makes; only the target root differs.
"""

import json
import os
import subprocess
import sys
import tempfile

# The service directory, not modules/: `modules` is a package and template.py imports from it
# relatively (`from .addons import template_helpers`), so importing template.py as a top-level module
# fails. configd itself reaches it as `from .. import template`.
sys.path.insert(0, '/usr/local/opnsense/service')

from modules import config  # noqa: E402
from modules import template  # noqa: E402

CONFIG_XML = '/conf/config.xml'
MODULE = 'OPNsense/Netflector'
GENERATED = 'usr/local/etc/netflector.toml'
DAEMON = '/usr/local/bin/netflector'


def report(status, message):
    """Answer as JSON, so the GUI does not have to guess the verdict from the wording."""
    print(json.dumps({'status': status, 'message': message}))


def main():
    with tempfile.TemporaryDirectory() as root:
        conf = config.Config(CONFIG_XML)
        cnf = conf.get()

        tmpl = template.Template(root)
        tmpl.set_config(cnf)
        tmpl.generate(MODULE)

        candidate = os.path.join(root, GENERATED)
        if not os.path.exists(candidate):
            report('failed', 'The plugin generated no configuration at all.')
            return

        with open(candidate) as handle:
            generated = handle.read()

        # Nothing enabled is an off configuration, not a broken one, whichever way the service switch
        # points: the template arms the daemon only when both are on, so this state stops it rather
        # than starting it. Handing the file to the daemon anyway would answer with its "must define
        # at least one reflector", which reads as a fault when nothing is wrong.
        if '[reflectors.' not in generated:
            report('idle', 'Nothing to validate (no enabled reflectors, the service will not run)')
            return

        proc = subprocess.run(
            [DAEMON, '--check-config', candidate],
            capture_output=True,
            text=True,
            check=False,
        )
        # The daemon reports a rejected configuration on stderr and exits non-zero. Report whatever it
        # said and exit 0 regardless: configd discards the output of a failing action, and that output
        # is the entire point here.
        message = (proc.stdout + proc.stderr).strip()
        # The temporary path is an implementation detail; the user configured a firewall, not /tmp/xyz.
        message = message.replace(candidate, 'the generated configuration')
        if not message:
            report('failed', 'The validation returned nothing.')
        elif proc.returncode == 0:
            report('ok', message)
        else:
            report('failed', message)


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        # configd discards a failing action's output, so an escaping exception reaches the GUI as a
        # generic error. Catch broadly and answer on stdout with exit 0, like a rejected config.
        report('failed', f'The validation could not run: {type(exc).__name__}: {exc}')
