"""
    Copyright (c) 2018-2019 Ad Schellevis <ad@opnsense.org>
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
import os
import re
import glob
import datetime
import ujson


def reverse_log_reader(filename):
    """ read log file in reverse order
    :param filename: filename
    :return: generator
    """
    block_size = 81920
    input_stream = open(filename, 'r')
    input_stream.seek(0, os.SEEK_END)
    file_byte_start = input_stream.tell()

    data = ''
    while file_byte_start > 0:
        if file_byte_start - block_size < 0:
            block_size = file_byte_start
            file_byte_start = 0
        else:
            file_byte_start -= block_size

        input_stream.seek(file_byte_start)

        data = input_stream.read(block_size) + data
        bol = data.rfind('\n')
        eol = len(data)

        while bol > -1:
            yield data[bol:eol]
            eol = bol
            bol = data.rfind('\n', 0, eol)

        data = data[:eol] if bol == -1 else ''

        if file_byte_start == 0 and bol == -1:
            yield data


def parse_log_line(line):
    """ parse eve log records, add __timestamp__ containing a timezoned datetime object
    :param line: raw json string
    :return: dict or None if unable to parse
    """
    try:
        record = ujson.loads(line.strip())
    except ValueError:
        record = dict()
    if 'timestamp' in record:
        # convert timestamp
        try:
            tmp = [int(x) for x in re.split('T|-|\\:|\\.|\\+', record['timestamp'])]
            tz = record['timestamp'][-5:]
            ts = datetime.datetime(*tmp[:7])
            ts -= datetime.timedelta(hours=int(tz[2:3]), minutes=int(tz[-2:])) * int(tz[0:1] + '1')
            record['__timestamp__'] = ts
            return record
        except ValueError:
            pass

    return None


def reader(log_directory, last_update):
    """ read and parse eve logs until timestamp is reached
    :param log_directory: directory to search for eve.json*
    :param last_update: datetime of last send event
    :return: iterator
    """
    for filename in sorted(glob.glob("%s/eve.json*" % log_directory)):
        for line in reverse_log_reader(filename=filename):
            record = parse_log_line(line)
            if record:
                if record['__timestamp__'] > last_update:
                    yield record
                else:
                    return
