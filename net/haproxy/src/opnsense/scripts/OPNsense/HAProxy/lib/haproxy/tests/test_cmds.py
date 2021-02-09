# pylint: disable=star-args, locally-disabled, too-few-public-methods, no-self-use, invalid-name
"""test_cmds.py - Unittests related to command implementations."""
import sys, os, unittest

sys.path.append(os.path.join(os.path.dirname(__file__), '..', '..'))
from haproxy import cmds

class TestCommands(unittest.TestCase):
    """Tests all of the  commands."""
    def setUp(self):

        self.Resp = {"disable" : "disable server redis-ro/redis-ro0",
                     "set-server-agent" : "set server redis-ro/redis-ro0 agent up",
                     "set-server-health" : "set server redis-ro/redis-ro0 health stopping",
                     "set-server-state" : "set server redis-ro/redis-ro0 state drain",
                     "set-server-weight" : "set server redis-ro/redis-ro0 weight 10",
                     "frontends" : "show stat",
                     "info" : "show info",
                     "sessions" : "show sess",
                     "servers" : "show stat",
                     "show-all-ssl-crt-list" : "show ssl crt-list",
                     "show-details-ssl-crt-list" : "show ssl crt-list /tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
                     "show-all-ssl-certs" : "show ssl cert",
                     "show-details-ssl-certs" : "show ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
                     "add-to-crt-list" : "add ssl crt-list /tmp/haproxy/ssl/601a7392cc9984.99301413.certlist /tmp/haproxy/ssl/601a70e4844b0.pem",
                     "del-from-crt-list" : "del ssl crt-list /tmp/haproxy/ssl/601a7392cc9984.99301413.certlist /tmp/haproxy/ssl/601a70e4844b0.pem",
                     "add-ssl-cert" : "new ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
                     "update-ssl-cert" : "set ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem <payload>",
                     "del-ssl-cert" : "del ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
                     "commit-ssl-cert" : "commit ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
                     "abort-ssl-cert" : "abort ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
        }

        self.Resp = dict([(k, v + "\r\n") for k, v in self.Resp.items()])

    def test_setServerAgent(self):
        """Test 'set server agent' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "up"}
        cmdOutput = cmds.setServerAgent(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-agent"])

    def test_setServerHealth(self):
        """Test 'set server health' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "stopping"}
        cmdOutput = cmds.setServerHealth(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-health"])

    def test_setServerState(self):
        """Test 'set server state' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "drain"}
        cmdOutput = cmds.setServerState(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-state"])

    def test_setServerWeight(self):
        """Test 'set server weight' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "10"}
        cmdOutput = cmds.setServerWeight(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-weight"])

    def test_showFrontends(self):
        """Test 'frontends/backends' commands"""
        args = {}
        cmdOutput = cmds.showFrontends(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["frontends"])

    def test_showInfo(self):
        """Test 'show info' command"""
        cmdOutput = cmds.showInfo().getCmd()
        self.assertEqual(cmdOutput, self.Resp["info"])

    def test_showSessions(self):
        """Test 'show sess' command"""
        cmdOutput = cmds.showSessions().getCmd()
        self.assertEqual(cmdOutput, self.Resp["sessions"])

    def test_showServers(self):
        """Test 'show stat' command"""
        args = {"backend": "redis-ro"}
        cmdOutput = cmds.showServers(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["servers"])

    def test_showAllSslCrtList(self):
        """Test 'show ssl crt-list' command"""
        cmdOutput = cmds.showAllSslCrtList().getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-all-ssl-crt-list"])

    def test_showDetailsSslCrtList(self):
        """Test 'show ssl crt-list <filename>' command"""
        args = {
            "filename": "/tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
        }
        cmdOutput = cmds.test_showDetailsSslCrtList(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-details-ssl-crt-list"])

    def test_showAllSslCerts(self):
        """Test 'show ssl cert' command"""
        cmdOutput = cmds.showAllSslCerts().getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-all-ssl-certs"])

    def test_showDetailsSslCerts(self):
        """Test 'show ssl cert <certfile>' command"""
        args = {
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem"
        }
        cmdOutput = cmds.showDetailsSslCerts(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-details-ssl-certs"])

    def test_addToSslCrtList(self):
        """Test 'add ssl crt-list <filename> <certfile>' command"""
        args = {
            "filename": "/tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem"
        }
        cmdOutput = cmds.addToSslCrtList(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["add-to-crt-list"])

    def test_delFromSslCrtList(self):
        """Test 'del ssl crt-list <filename> <certfile>' command"""
        args = {
            "filename": "/tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem"
        }
        cmdOutput = cmds.delFromSslCrtList(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["del-from-crt-list"])

    def test_addSslCrt(self):
        """Test 'new ssl cert <certfile>' command"""
        args = {
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.addSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["add-ssl-cert"])

    def test_updateSslCrt(self):
        """Test 'new ssl cert <certfile>' command"""
        args = {
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem",
            "payload" : "TODO"
        }
        cmdOutput = cmds.updateSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["update-ssl-cert"])

    def test_delSslCrt(self):
        """Test 'del ssl cert <certfile>' command"""
        args = {
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.delSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["del-ssl-cert"])

    def test_commitSslCrt(self):
        """Test 'commit ssl cert <certfile>' command"""
        args = {
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.commitSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["commit-ssl-cert"])

    def test_abortSslCrt(self):
        """Test 'abort ssl cert <certfile>' command"""
        args = {
            "certfile" : "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.abortSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["abort-ssl-cert"])

if __name__ == '__main__':
    unittest.main()
