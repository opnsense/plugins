# pylint: disable=star-args, locally-disabled, too-few-public-methods, no-self-use, invalid-name
"""test_cmds.py - Unittests related to command implementations."""
import sys, os, unittest

sys.path.append(os.path.join(os.path.dirname(__file__), '..', '..'))
from haproxy import cmds


class TestCommands(unittest.TestCase):
    """Tests all of the  commands."""

    def setUp(self):
        self.maxDiff = None
        self.pem_cert_content = """
        -----BEGIN CERTIFICATE-----
        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
        +VCAIWMkC36Dvg==
        -----END CERTIFICATE-----

        -----BEGIN RSA PRIVATE KEY-----
        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
        -----END RSA PRIVATE KEY-----

        -----BEGIN CERTIFICATE-----
        MIIEqzCCApOgAwIBAgIRAIvhKg5ZRO08VGQx8JdhT+UwDQYJKoZIhvcNAQELBQAw
        GjEYMBYGA1UEAwwPRmFrZSBMRSBSb290IFgxMB4XDTE2MDUyMzIyMDc1OVoXDTM2
        MDUyMzIyMDc1OVowIjEgMB4GA1UEAwwXRmFrZSBMRSBJbnRlcm1lZGlhdGUgWDEw
        ggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDtWKySDn7rWZc5ggjz3ZB0
        8jO4xti3uzINfD5sQ7Lj7hzetUT+wQob+iXSZkhnvx+IvdbXF5/yt8aWPpUKnPym
        oLxsYiI5gQBLxNDzIec0OIaflWqAr29m7J8+NNtApEN8nZFnf3bhehZW7AxmS1m0
        ZnSsdHw0Fw+bgixPg2MQ9k9oefFeqa+7Kqdlz5bbrUYV2volxhDFtnI4Mh8BiWCN
        xDH1Hizq+GKCcHsinDZWurCqder/afJBnQs+SBSL6MVApHt+d35zjBD92fO2Je56
        dhMfzCgOKXeJ340WhW3TjD1zqLZXeaCyUNRnfOmWZV8nEhtHOFbUCU7r/KkjMZO9
        AgMBAAGjgeMwgeAwDgYDVR0PAQH/BAQDAgGGMBIGA1UdEwEB/wQIMAYBAf8CAQAw
        HQYDVR0OBBYEFMDMA0a5WCDMXHJw8+EuyyCm9Wg6MHoGCCsGAQUFBwEBBG4wbDA0
        BggrBgEFBQcwAYYoaHR0cDovL29jc3Auc3RnLXJvb3QteDEubGV0c2VuY3J5cHQu
        b3JnLzA0BggrBgEFBQcwAoYoaHR0cDovL2NlcnQuc3RnLXJvb3QteDEubGV0c2Vu
        Y3J5cHQub3JnLzAfBgNVHSMEGDAWgBTBJnSkikSg5vogKNhcI5pFiBh54DANBgkq
        hkiG9w0BAQsFAAOCAgEABYSu4Il+fI0MYU42OTmEj+1HqQ5DvyAeyCA6sGuZdwjF
        UGeVOv3NnLyfofuUOjEbY5irFCDtnv+0ckukUZN9lz4Q2YjWGUpW4TTu3ieTsaC9
        AFvCSgNHJyWSVtWvB5XDxsqawl1KzHzzwr132bF2rtGtazSqVqK9E07sGHMCf+zp
        DQVDVVGtqZPHwX3KqUtefE621b8RI6VCl4oD30Olf8pjuzG4JKBFRFclzLRjo/h7
        IkkfjZ8wDa7faOjVXx6n+eUQ29cIMCzr8/rNWHS9pYGGQKJiY2xmVC9h12H99Xyf
        zWE9vb5zKP3MVG6neX1hSdo7PEAb9fqRhHkqVsqUvJlIRmvXvVKTwNCP3eCjRCCI
        PTAvjV+4ni786iXwwFYNz8l3PmPLCyQXWGohnJ8iBm+5nk7O2ynaPVW0U2W+pt2w
        SVuvdDM5zGv2f9ltNWUiYZHJ1mmO97jSY/6YfdOUH66iRtQtDkHBRdkNBsMbD+Em
        2TgBldtHNSJBfB3pm9FblgOcJ0FSWcUDWJ7vO0+NTXlgrRofRT6pVywzxVo6dND0
        WzYlTWeUVsO40xJqhgUQRER9YLOLxJ0O6C8i0xFxAMKOtSdodMB3RIwt7RFQ0uyt
        n5Z5MqkYhlMI3J1tPRTp1nEt9fyGspBOO05gi148Qasp+3N+svqKomoQglNoAxU=
        -----END CERTIFICATE-----
        """

        self.Resp = {
            "disable": "disable server redis-ro/redis-ro0",
            "set-server-agent": "set server redis-ro/redis-ro0 agent up",
            "set-server-health": "set server redis-ro/redis-ro0 health stopping",
            "set-server-state": "set server redis-ro/redis-ro0 state drain",
            "set-server-weight": "set server redis-ro/redis-ro0 weight 10",
            "frontends": "show stat",
            "info": "show info",
            "sessions": "show sess",
            "servers": "show stat",
            "show-ssl-crt-lists": "show ssl crt-list",
            "show-ssl-crt-list": "show ssl crt-list -n /tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
            "show-ssl-certs": "show ssl cert",
            "show-ssl-cert": "show ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
            "add-to-crt-list": "add ssl crt-list /tmp/haproxy/ssl/601a7392cc9984.99301413.certlist /tmp/haproxy/ssl/601a70e4844b0.pem",
            "del-from-crt-list": "del ssl crt-list /tmp/haproxy/ssl/601a7392cc9984.99301413.certlist /tmp/haproxy/ssl/601a70e4844b0.pem",
            "new-ssl-cert": "new ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
            "update-ssl-cert": "set ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem <<\n%s" % self.pem_cert_content,
            "del-ssl-cert": "del ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
            "commit-ssl-cert": "commit ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
            "abort-ssl-cert": "abort ssl cert /tmp/haproxy/ssl/601a70e4844b0.pem",
        }

        self.Resp = dict([(k, v + "\r\n") for k, v in self.Resp.items()])

    def test_setServerAgent(self):
        """Test 'set server agent' command"""
        args = {"backend": "redis-ro", "server": "redis-ro0", "value": "up"}
        cmdOutput = cmds.setServerAgent(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-agent"])

    def test_setServerHealth(self):
        """Test 'set server health' command"""
        args = {"backend": "redis-ro", "server": "redis-ro0", "value": "stopping"}
        cmdOutput = cmds.setServerHealth(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-health"])

    def test_setServerState(self):
        """Test 'set server state' command"""
        args = {"backend": "redis-ro", "server": "redis-ro0", "value": "drain"}
        cmdOutput = cmds.setServerState(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["set-server-state"])

    def test_setServerWeight(self):
        """Test 'set server weight' command"""
        args = {"backend": "redis-ro", "server": "redis-ro0", "value": "10"}
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

    def test_showSslCrtLists(self):
        """Test 'show ssl crt-list' command"""
        cmdOutput = cmds.showSslCrtLists().getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-ssl-crt-lists"])

    def test_showSslCrtList(self):
        """Test 'show ssl crt-list <crt-list>' command"""
        args = {
            "crt_list": "/tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
        }
        cmdOutput = cmds.showSslCrtList(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-ssl-crt-list"])

    def test_showSslCerts(self):
        """Test 'show ssl cert' command"""
        cmdOutput = cmds.showSslCerts().getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-ssl-certs"])

    def test_showSslCert(self):
        """Test 'show ssl cert <certfile>' command"""
        args = {
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem"
        }
        cmdOutput = cmds.showSslCert(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["show-ssl-cert"])

    def test_addToSslCrtList(self):
        """Test 'add ssl crt-list <crt-list> <certfile>' command"""
        args = {
            "crt_list": "/tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem"
        }
        cmdOutput = cmds.addToSslCrtList(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["add-to-crt-list"])

    def test_delFromSslCrtList(self):
        """Test 'del ssl crt-list <crt-list> <certfile>' command"""
        args = {
            "crt_list": "/tmp/haproxy/ssl/601a7392cc9984.99301413.certlist",
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem"
        }
        cmdOutput = cmds.delFromSslCrtList(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["del-from-crt-list"])

    def test_newSslCrt(self):
        """Test 'new ssl cert <certfile>' command"""
        args = {
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.newSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["new-ssl-cert"])

    def test_updateSslCrt(self):
        """Test 'set ssl cert <certfile> <payload>' command"""
        args = {
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem",
            "payload": "%s" % self.pem_cert_content
        }
        cmdOutput = cmds.updateSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["update-ssl-cert"])

    def test_delSslCrt(self):
        """Test 'del ssl cert <certfile>' command"""
        args = {
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.delSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["del-ssl-cert"])

    def test_commitSslCrt(self):
        """Test 'commit ssl cert <certfile>' command"""
        args = {
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.commitSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["commit-ssl-cert"])

    def test_abortSslCrt(self):
        """Test 'abort ssl cert <certfile>' command"""
        args = {
            "certfile": "/tmp/haproxy/ssl/601a70e4844b0.pem",
        }
        cmdOutput = cmds.abortSslCrt(**args).getCmd()
        self.assertEqual(cmdOutput, self.Resp["abort-ssl-cert"])


if __name__ == '__main__':
    unittest.main()
