#!/usr/local/bin/python3

"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import sys
import argparse
import syslog
import socketserver
import glob
import time
import traceback
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize

class StunnelLog:
    # ident log file location
    base_log_path = "/var/run/stunnel/logs"
    # maximum session length (after detect) in seconds
    session_max_ttl = 600
    # number of seconds after receiving "Connection closed" to remove session from cache
    session_grace_period = 60
    # amount of time in ms to wait before concluding a user is not found.
    # generally intened to prevent syslog latency leading to false access denied statements
    log_flush_grace_period_ms = 250
    # time in ms to wait between polls (when initial fetch didn't result in an authenticated session)
    log_flush_poll_interval_ms = 0.5

    def __init__(self):
        self._filename = None
        self._fhandle = None
        self._last_pos = None
        self._local_cache = dict()
        self._open()

    def _open(self):
        """ open last log file, also responsible for log rotate
        """
        filenames = sorted(glob.glob("%s/stunnel_ident_*.log" % self.base_log_path), reverse=True)
        if len(filenames) > 0 and self._filename != filenames[0]:
            self._filename = filenames[0]
            self._last_pos = None
            try:
                self._fhandle = open(self._filename, 'r')
            except IOError:
                self._fhandle = None

            # cleanup after rotate
            if len(filenames) > 1:
                for filename in filenames[1:]:
                    os.remove(filename)

    def parse(self, search_key):
        """ parse log file and detect new connected clients and the ones leaving (connection closed).
            Accounts connections in self._local_cache (in address:source_port format)
            :param search_key: when search_key isn't found, execute another pass to detect log-rotates
        """
        # we might need another pass
        for i in range(2):
            current_timestamp = time.time()
            if self._fhandle is not None:
                if self._last_pos is not None:
                    self._fhandle.seek(self._last_pos)

                while True:
                    line = self._fhandle.readline()
                    if line:
                        # track session id's, which ease debugging (see logId setting in stunnel)
                        session_id = None
                        if line.find('[') > -1:
                            session_id = line.split('[')[1].split(']')[0]
                        if line.find('IDENT Service') > -1:
                            # Ident log line, username (CN=) is currently returned when an ident call is made
                            cert_subject = line.split('-->')[1].strip()
                            username = cert_subject[cert_subject.find('CN=')+3:].strip()
                            src = line.split(' from ')[1].split()[0]
                            self._local_cache[src] = {
                                'username': username,
                                'cn': cert_subject,
                                'session_id': session_id,
                                'expire': current_timestamp + self.session_max_ttl
                            }
                        elif line.find('Connection closed') > -1 and line.find('[') > -1:
                            # Connection closed lines are used to trigger cleanups in two stages
                            # 1. push expire to now() + session_grace_period
                            # 2. when expired, delete from cache
                            for src in list(self._local_cache):
                                is_expired = current_timestamp > self._local_cache[src]['expire']
                                if session_id == self._local_cache[src]['session_id']:
                                    self._local_cache[src]['expire'] = current_timestamp + self.session_grace_period
                                elif is_expired:
                                    del self._local_cache[src]
                    else:
                        break
                self._last_pos = self._fhandle.tell()
            if search_key in self._local_cache:
                break
            else:
                # possible log rotate (new file)
                self._open()


    def whois(self, src_port, dst_port, address):
        """ try to resolve user at src_port:address:dst_port for max log_flush_grace_period_ms time
            :param src_port: source port
            :param dst_port: destination port
            :param address: address, usually the address stunnel connected to (target hostname)
            :return: username or False if not found
        """
        search_key = "%s:%s" % (address, src_port)
        start_time = time.time()
        while True:
            self.parse(search_key)
            if search_key in self._local_cache:
                return self._local_cache[search_key]['username']
            elif (time.time() - start_time) * 1000.0 > self.log_flush_grace_period_ms:
                break
            else:
                time.sleep(self.log_flush_poll_interval_ms/1000.0)

        return False


class RequestHandler(socketserver.StreamRequestHandler):
    _stunnel_log = None

    @staticmethod
    def stunnel_ident(src_port, dst_port, address):
        if RequestHandler._stunnel_log is None:
            RequestHandler._stunnel_log = StunnelLog()

        return RequestHandler._stunnel_log.whois(src_port, dst_port, address)

    def handle(self):
        """ connection handler, strip src/dst port pairs, resolve and return
        """
        start_time = time.time()
        src_port, dst_port = [0, 0]
        try:
            req_data = self.rfile.readline().decode().strip()
            src_port, dst_port = [ int(x.strip()) for x in req_data.split(',') ]
            if  src_port < 1 or  src_port > 65535 or dst_port < 1 or  dst_port > 65535:
                syslog.syslog(syslog.LOG_WARNING, 'INVALID-PORT %d,%s,%d.' % (
                    src_port, self.client_address[0], dst_port
                ))
                self.wfile.write('{}, {} : ERROR : INVALID-PORT\r\n'.format(src_port, dst_port).encode())
            else:
                username = self.stunnel_ident(src_port, dst_port, self.client_address[0])
                req_latency_ms = (time.time() - start_time) * 1000.0
                if not username:
                    syslog.syslog(syslog.LOG_WARNING, 'NO-USER %d,%s,%d (%0.05f ms).' % (
                        src_port, self.client_address[0], dst_port, req_latency_ms
                    ))
                    self.wfile.write('{}, {} : ERROR : NO-USER\r\n'.format(src_port, dst_port).encode())
                else:
                    syslog.syslog(syslog.LOG_NOTICE, 'USERID %d,%s,%d = %s (%0.05f ms).' % (
                        src_port, self.client_address[0], dst_port, username, req_latency_ms
                    ))
                    self.wfile.write('{}, {} : USERID : OTHER : {}\r\n'.format(src_port, dst_port, username).encode())
        except:
            self.wfile.write('{}, {} : ERROR : UNKNOWN-ERROR\r\n'.format(src_port, dst_port).encode())
            syslog.syslog(syslog.LOG_ERR, traceback.format_exc().replace('\n', ' '))


def run_listener():
    server = socketserver.ThreadingTCPServer(('0.0.0.0', 113), RequestHandler, bind_and_activate=False)
    server.allow_reuse_address = True
    server.request_queue_size = 128
    server.server_bind()
    server.server_activate()
    server.serve_forever()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--foreground', help='run in forground', default=False, action='store_true')
    inputargs = parser.parse_args()

    syslog.openlog('identd_stunnel', facility=syslog.LOG_LOCAL4)

    if inputargs.foreground:
        run_listener()
    else:
        syslog.syslog(syslog.LOG_NOTICE, 'daemonize stunnel_identd.')
        daemon = Daemonize(app="identd_stunnel", pid='/var/run/identd_stunnel.pid', action=run_listener)
        daemon.start()
