# pylint: disable=locally-disabled, too-few-public-methods, no-self-use, invalid-name
"""conn.py - Connection module."""
import re
from socket import socket, AF_INET, AF_UNIX, SOCK_STREAM
from haproxy import const

class HapError(Exception):
    """Generic exception for haproxyctl."""
    pass

class HaPConn(object):
    """HAProxy Socket object.
       This class abstract the socket interface so
       commands can be sent to HAProxy and results received and
       parse by the command objects"""

    def __init__(self, sfile, socket_module=socket):
        """Initializes an HAProxy and opens a connection to it
           (sfile, type) -> Path for the UNIX socket"""

        self.sock = None
        sfile = sfile.strip()
        stype = AF_UNIX
        self.socket_module = socket_module

        mobj = re.match(
            '(?P<proto>unix://|tcp://)(?P<addr>[^:]+):*(?P<port>[0-9]*)$', sfile)

        if mobj:
            proto = mobj.groupdict().get('proto', None)
            addr = mobj.groupdict().get('addr', None)
            port = mobj.groupdict().get('port', '')

            if not addr or not proto:
                raise HapError('Could not determine type of socket.')

            if proto == const.HAP_TCP_PATH:
                if not port:
                    raise HapError('When using a tcp socket, a port is needed.')
                stype = AF_INET
                sfile = (addr, int(port))

            if proto == const.HAP_UNIX_PATH:
                stype = AF_UNIX
                sfile = addr

        # Fallback should be sfile/AF_UNIX by default
        self.sfile = (sfile, stype)
        self.open()

    def open(self):
        """Opens a connection for the socket.
           This function should only be called if
           self.closed() method was called"""

        sfile, stype = self.sfile
        self.sock = self.socket_module(stype, SOCK_STREAM)
        self.sock.connect(sfile)

    def sendCmd(self, cmd, objectify=False):
        """Receives a command obj and sends it to the socket. Receives the output and passes it
           through the command to parse it.
           objectify -> Return an object instead of plain text"""

        res = ""
        try:
            self.sock.send(cmd.getCmd())
        except TypeError:
            self.sock.send(bytearray(cmd.getCmd(), 'ASCII'))
        output = self.sock.recv(const.HAP_BUFSIZE)

        while output:
            res += output.decode('UTF-8')
            output = self.sock.recv(const.HAP_BUFSIZE)

        if objectify:
            return cmd.getResultObj(res)

        return cmd.getResult(res)

    def close(self):
        """Closes the socket"""
        self.sock.close()
