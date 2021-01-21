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

        }

        self.Resp = dict([(k, v + "\r\n") for k, v in self.Resp.items()])

    def test_setServerAgent(self):
        """Test 'set server agent' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "up"}
        cmdSetServerAgent = cmds.setServerAgent(**args).getCmd()
        self.assertEqual(cmdSetServerAgent, self.Resp["set-server-agent"])

    def test_setServerHealth(self):
        """Test 'set server health' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "stopping"}
        cmdSetServerHealth = cmds.setServerHealth(**args).getCmd()
        self.assertEqual(cmdSetServerHealth, self.Resp["set-server-health"])

    def test_setServerState(self):
        """Test 'set server state' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "drain"}
        cmdSetServerState = cmds.setServerState(**args).getCmd()
        self.assertEqual(cmdSetServerState, self.Resp["set-server-state"])

    def test_setServerWeight(self):
        """Test 'set server weight' command"""
        args = {"backend": "redis-ro", "server" : "redis-ro0", "value": "10"}
        cmdSetServerState = cmds.setServerWeight(**args).getCmd()
        self.assertEqual(cmdSetServerState, self.Resp["set-server-weight"])

    def test_showFrontends(self):
        """Test 'frontends/backends' commands"""
        args = {}
        cmdFrontends = cmds.showFrontends(**args).getCmd()
        self.assertEqual(cmdFrontends, self.Resp["frontends"])

    def test_showInfo(self):
        """Test 'show info' command"""
        cmdShowInfo = cmds.showInfo().getCmd()
        self.assertEqual(cmdShowInfo, self.Resp["info"])

    def test_showSessions(self):
        """Test 'show info' command"""
        cmdShowInfo = cmds.showSessions().getCmd()
        self.assertEqual(cmdShowInfo, self.Resp["sessions"])

    def test_showServers(self):
        """Test 'show info' command"""
        args = {"backend": "redis-ro"}
        cmdShowInfo = cmds.showServers(**args).getCmd()
        self.assertEqual(cmdShowInfo, self.Resp["servers"])

if __name__ == '__main__':
    unittest.main()
