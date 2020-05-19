#!/usr/local/bin/python3

import os
import sys
import argparse
import syslog
import socketserver
import glob
import time
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize

base_log_path = "/var/run/stunnel/logs"
max_ttl = 300
cleanup_grace_period = 60
log_flush_grace_period_ms = 250

class StunnelLog:
    def __init__(self):
        self._filename = None
        self._fhandle = None
        self._last_pos = None
        self._last_timestamp = None
        self._local_cache = dict()

    def _open(self):
        filenames = sorted(glob.glob("%s/stunnel_ident_*.log" % base_log_path), reverse=True)
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
        # we might need another pass
        for i in range(2):
            current_timestamp = time.time()
            self._open()
            if self._fhandle is not None:
                if self._last_pos is not None:
                    self._fhandle.seek(self._last_pos)

                while True:
                    line = self._fhandle.readline()
                    if line:
                        session_id = None
                        if line.find('[') > -1:
                            session_id = line.split('[')[1].split(']')[0]
                        if line.find('IDENT Service') > -1:
                            cert_subject = line.split('-->')[1].strip()
                            username = cert_subject[cert_subject.find('CN=')+3:].strip()
                            src = line.split(' from ')[1].split()[0]
                            self._local_cache[src] = {
                                'username': username,
                                'cn': cert_subject,
                                'session_id': session_id,
                                'expire': current_timestamp + max_ttl
                            }
                        elif line.find('Connection closed') > -1 and line.find('[') > -1:
                            for src in list(self._local_cache):
                                is_expired = current_timestamp > self._local_cache[src]['expire']
                                if session_id == self._local_cache[src]['session_id']:
                                    self._local_cache[src]['expire'] = current_timestamp + cleanup_grace_period
                                elif is_expired:
                                    del self._local_cache[src]
                    else:
                        break
                self._last_pos = self._fhandle.tell()
            # found, no need to search for log rotate
            if search_key in self._local_cache:
                break


    def whois(self, src_port, dst_port, address):
        search_key = "%s:%s" % (address, src_port)
        for i in range(log_flush_grace_period_ms):
            self.parse(search_key)
            if search_key in self._local_cache:
                return self._local_cache[search_key]['username']
            else:
                time.sleep(1/1000.0)

        return False

class RequestHandler(socketserver.StreamRequestHandler):
    _stunnel_log = None

    @staticmethod
    def stunnel_ident(src_port, dst_port, address):
        if RequestHandler._stunnel_log is None:
            RequestHandler._stunnel_log = StunnelLog()

        return RequestHandler._stunnel_log.whois(src_port, dst_port, address)

    def handle(self):
        start_time = time.time()
        src_port, dst_port = [0, 0]
        try:
            self.data = self.rfile.readline().decode().strip()
            src_port, dst_port = [ int(x.strip()) for x in self.data.split(',') ]
            username = self.stunnel_ident(src_port, dst_port, self.client_address[0])
            req_latency_ms = (time.time() - start_time) / 1000.0
            if not username:
                syslog.syslog(syslog.LOG_NOTICE, 'NO-USER %d,%d (%0.5f ms).' % (src_port, dst_port, req_latency_ms))
                self.wfile.write('{}, {} : ERROR : NO-USER\r\n'.format(src_port, dst_port).encode())
            else:
                syslog.syslog(syslog.LOG_NOTICE, 'USERID %d,%d = %s.' % (src_port, dst_port, username))
                self.wfile.write('{}, {} : USERID : OTHER : {}\r\n'.format(src_port, dst_port, username).encode())
        except:
            self.wfile.write('{}, {} : ERROR : UNKNOWN-ERROR\r\n'.format(src_port, dst_port).encode())
            raise

def run_listener():
    server = socketserver.TCPServer(('0.0.0.0', 113), RequestHandler, bind_and_activate=False)
    server.allow_reuse_address = True
    server.server_bind()
    server.server_activate()
    server.serve_forever()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--foreground', help='run in forground', default=False, action='store_true')
    inputargs = parser.parse_args()

    syslog.openlog('stunnel_identd', logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)

    if inputargs.foreground:
        run_listener()
    else:
        syslog.syslog(syslog.LOG_NOTICE, 'daemonize stunnel_identd.')
        daemon = Daemonize(app="stunnel_identd", pid='/var/run/stunnel_identd.pid', action=run_listener)
        daemon.start()
