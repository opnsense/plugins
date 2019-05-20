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
import fcntl
import datetime


class Telemetry(object):
    def __init__(self, filename, init_last_days=2):
        self._filename = filename
        self._init_last_days = init_last_days
        try:
            self._file_handle = open(self._filename, 'a+')
            fcntl.flock(self._file_handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            # already running
            self._file_handle = None

    def is_running(self):
        return self._file_handle is None

    def get_last_update(self):
        """ return last used timestamp
        :return: datetime
        """
        self._file_handle.seek(0)
        try:
            result = datetime.datetime.fromtimestamp(float(self._file_handle.readline()))
        except ValueError:
            result = datetime.datetime.now() - datetime.timedelta(days=self._init_last_days)

        return result

    def set_last_update(self, stamp):
        """ set last timestamp
        :param stamp: datetime object
        :return:
        """
        self._file_handle.seek(0)
        self._file_handle.truncate()
        self._file_handle.write("%s\n" % stamp.strftime('%s.%f'))

    def __del__(self):
        """ close file handle on destruct
        :return:
        """
        if self._file_handle:
            self._file_handle.close()
