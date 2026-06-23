#!/usr/bin/env python3
# coding=utf-8
"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
import shutil
import sys
import tempfile
import tarfile
import io
import requests

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('filename', help='output filename')
    cmd_args = parser.parse_args()

    req_opts = {
        'url': 'http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz',
        'timeout': 120,
        'stream': True
    }
    try:
        req = requests.get(**req_opts)
    except Exception as e:
        print("unable to download %s" % req_opts['url'])
        sys.exit(99)

    directory_map = {
        'blacklists/agressif': 'blacklists/aggressive',
        'blacklists/publicite': 'blacklists/advertisements',
        'blacklists/drogue': 'blacklists/drugs',
        'blacklists/tricheur': None,
        'blacklists/arjel': None,
        'blacklists/associations_religieuses': None,
        'blacklists/dialer': None,
        'blacklists/liste_bu': None,
        'blacklists/reaffected': None,
        'blacklists/strict_redirector': None,
        'blacklists/strong_redirector': None,
        'blacklists/sect': None,

    }
    filenames = ['urls', 'domains', 'README', 'global_usage', 'cc-by-sa-4-0.pdf', 'LICENSE.pdf']

    if 200 <= req.status_code <= 299:
        with tempfile.NamedTemporaryFile() as tmp_stream:
            shutil.copyfileobj(req.raw, tmp_stream)
            tmp_stream.seek(0)
            tf = tarfile.open(fileobj=tmp_stream)
            with tarfile.open(cmd_args.filename, "w:gz") as tar_handle:
                for tf_file in tf.getmembers():
                    filename = os.path.basename(tf_file.name)
                    if tf_file.isreg() and filename in filenames:
                        target = tf_file.name
                        dirname = os.path.dirname(tf_file.name)
                        if dirname in directory_map:
                            if directory_map[dirname] is None:
                                continue
                            else:
                                target = "%s/%s" % (directory_map[dirname], filename)
                        fhandle = tf.extractfile(tf_file)
                        info = tarfile.TarInfo(target)
                        fhandle.seek(0, io.SEEK_END)
                        info.size = fhandle.tell()
                        fhandle.seek(0, io.SEEK_SET)
                        tar_handle.addfile(info, fhandle)

                tar_handle.close()
