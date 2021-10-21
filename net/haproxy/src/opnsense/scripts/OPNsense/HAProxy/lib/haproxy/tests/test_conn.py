# pylint: disable=locally-disabled, too-few-public-methods, no-self-use, invalid-name, broad-except
"""test_conn.py - Unittests related to connections to HAProxy."""
import sys, os
sys.path.append(os.path.join(os.path.dirname(__file__), '..', '..'))
from haproxy import conn
import unittest
from socket import AF_INET, AF_UNIX

class SimpleConnMock(object):
    """Simple socket mock."""
    def __init__(self, stype, stream):
        self.stype = stype
        self.stream = stream

    def connect(self, addr):
        """Mocked socket.connect method."""
        pass

class TestConnection(unittest.TestCase):
    """Tests different aspects of haproxyctl's connections to HAProxy."""

    def testConnSimple(self):
        """Tests that connection to non-protocol path works and fallsback to UNIX socket."""
        sfile = "/some/path/to/socket.sock"
        c = conn.HaPConn(sfile, socket_module=SimpleConnMock)
        addr, stype = c.sfile
        self.assertEqual(sfile, addr)
        self.assertEqual(stype, AF_UNIX)

    def testConnUnixString(self):
        """Tests that unix:// protocol works and connects to a socket."""
        sfile = "unix:///some/path/to/socket.socket"
        c = conn.HaPConn(sfile, socket_module=SimpleConnMock)
        addr, stype = c.sfile
        self.assertEqual("/some/path/to/socket.socket", addr)
        self.assertEqual(stype, AF_UNIX)

    def testConnTCPString(self):
        """Tests that tcp:// protocol works and connects to an IP."""
        sfile = "tcp://1.2.3.4:8080"
        c = conn.HaPConn(sfile, socket_module=SimpleConnMock)
        addr, stype = c.sfile
        ip, port = addr
        self.assertEqual("1.2.3.4", ip)
        self.assertEqual(8080, port)
        self.assertEqual(stype, AF_INET)

    def testConnTCPStringNoPort(self):
        """Tests that passing a tcp:// address with no port, raises an Exception."""
        sfile = "tcp://1.2.3.4"
        # Not using assertRaises because we still support 2.6
        try:
            conn.HaPConn(sfile, socket_module=SimpleConnMock)
            raise Exception('Connection should have thrown an exception')
        except conn.HapError:
            pass

if __name__ == '__main__':
    unittest.main()
