"""cmds.py - Implementations of the different HAProxy commands"""
import re
import csv
import json
from collections import OrderedDict
from io import StringIO

class Cmd():
    """Cmd - Command base class"""
    req_args = []
    args = {}
    cmdTxt = ""
    helpTxt = ""

    # pylint: disable=unused-argument
    def __init__(self, *args, **kwargs):
        """Argument to the command are given in kwargs only. We ignore *args."""
        self.args = kwargs
        valid_kwargs = [k for (k, v) in kwargs.items() if v is not None]

        if not all([a in valid_kwargs for a in self.req_args]):
            raise Exception(f"Wrong number of arguments. Required arguments are: {self.WhatArgs()}")

    def WhatArgs(self):
        """Returns a formatted string of arguments to this command."""
        return ",".join(self.req_args)

    @classmethod
    def getHelp(cls):
        """Get formatted help string for this command."""
        txtArgs = ",".join(cls.req_args)

        if not txtArgs:
            txtArgs = "None"
        return " ".join((cls.helpTxt, "Arguments: %s" % txtArgs))

    def getCmd(self):
        """Gets the command line for this command.
        The default behavior is to apply the args dict to cmdTxt
        """
        return self.cmdTxt % self.args

    def getBootstrapOutput(self, resObj):
        """ Returns results gathered from HAProxy as jquery bootstrap output """
        args = {
            "rows": resObj,
            "page": int(self.args['page']) if self.args['page'] != None else 1,
            "page_rows": int(self.args['page_rows']) if self.args['page_rows'] != None else len(rows),
            "search": self.args['search'],
            "sort_col": self.args['sort_col'] if self.args['sort_col'] else 'id',
            "sort_dir": self.args['sort_dir'],
        }
        rows = args['rows']
        # search
        if args['search']:
            filtered_rows = []
            for row in rows:
                def inner(row):
                    for k, v in row.items():
                        if args['search'] in v:
                            return row
                    return None

                match = inner(row)
                if match:
                    filtered_rows.append(match)
            rows = filtered_rows

        # sort
        rows.sort(key=lambda k: k[args['sort_col']], reverse=True if args['sort_dir'] == 'desc' else False)

        # pager
        total = len(rows)
        pages = [rows[i:i + args['page_rows']] for i in range(0, total, args['page_rows'])]
        if pages and (args['page'] > len(pages) or args['page'] < 1):
            raise KeyError(f"Current page {args['page']} does not exist. Available pages: {len(pages)}")
        page = pages[args['page'] - 1] if pages else []

        return json.dumps({
            "rows": page,
            "total": total,
            "rowCount": args['page_rows'],
            "current": args['page']
        })

    def getJsonOutput(self, resObj):
        """Returns results gathered from HAProxy as json"""
        return json.dumps(resObj)

    def getResult(self, res):
        """Returns raw results gathered from HAProxy"""
        if res == '\n':
            res = None

        if self.args['output'] == 'json':
            return self.getJsonOutput(self.getResultObj(res))

        if self.args['output'] == 'bootstrap':
            return self.getBootstrapOutput(self.getResultObj(res))

        return res

    def getResultObj(self, res):
        """Returns refined output from HAProxy, packed inside a Python obj i.e. a dict()"""
        return res

class setServerAgent(Cmd):
    cmdTxt = "set server %(backend)s/%(server)s agent %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's agent to a new state."

class setServerHealth(Cmd):
    cmdTxt = "set server %(backend)s/%(server)s health %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's health to a new state."

class setServerState(Cmd):
    cmdTxt = "set server %(backend)s/%(server)s state %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's administrative state to a new state."

class setServerWeight(Cmd):
    cmdTxt = "set server %(backend)s/%(server)s weight %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's weight to a new state."

class showSslCrtLists(Cmd):
    cmdTxt = "show ssl crt-list\r\n"
    helpTxt = "Show the list of crt-lists."

    def getResultObj(self, res):
        result = { "crt_lists": []}
        for line in res.split("\n"):
            if line.startswith('/'):
                result["crt_lists"].append(line)
        return result

class showSslCrtList(Cmd):
    cmdTxt = "show ssl crt-list -n %(crt_list)s\r\n"
    req_args = ['crt_list']
    helpTxt = "Show the the content of a crt-list."

    def getResultObj(self, res):
        result = {}
        list_id = None
        for line in res.split("\n"):
            if line.startswith('# '):
                list_id = line.split("# ")[1]
                result["certs"] = []

            if list_id and line.startswith('/'):
                result["certs"].append(line)

        if result:
           return result

        return {"error": res.strip()}

class showSslCerts(Cmd):
    cmdTxt = "show ssl cert\r\n"
    helpTxt = "Display the SSL certificates used in memory."

    def getResultObj(self, res):
        result = {
            "transaction": [],
            "filename": []
        }
        for line in res.split("\n"):
            if line.startswith('*'):
                result['transaction'].append(line)
            elif line.startswith('/'):
                result['filename'].append(line)
        return result

class showSslCert(Cmd):
    cmdTxt = "show ssl cert %(certfile)s\r\n"
    req_args = ['certfile']
    helpTxt = "Display the details of a SSL certificate used in memory."

    def getResultObj(self, res):
        result = {}
        cert_id = None
        for line in res.split("\n"):
            if line:
                key = line.split(":")[0]
                val = line.split(":")[1].strip()

                if key == 'Filename':
                    cert_id = val

                if cert_id:
                    result[key] = val

        if result:
            return result

        return {"error": res.strip()}

class addToSslCrtList(Cmd):
    cmdTxt = "add ssl crt-list %(crt_list)s %(certfile)s\r\n"
    req_args = ['crt_list', 'certfile']
    helpTxt = "Add a ssl cert to a crt-list."

class delFromSslCrtList(Cmd):
    cmdTxt = "del ssl crt-list %(crt_list)s %(certfile)s\r\n"
    req_args = ['crt_list', 'certfile']
    helpTxt = "Delete a ssl cert from a crt-list."

class newSslCrt(Cmd):
    """" Create an empty slot for the certificate in HAProxy’s memory """
    cmdTxt = "new ssl cert %(certfile)s\r\n"
    req_args = ['certfile']
    helpTxt = "Create a new certificate file to be used in a crt-list or a directory."

class updateSslCrt(Cmd):
    """" Begin a transaction to upload the certificate into a slot in HAProxy’s memory """
    cmdTxt = "set ssl cert %(certfile)s <<\n%(payload)s\r\n"
    req_args = ['certfile', 'payload']
    helpTxt = "Replace a certificate file."

class delSslCrt(Cmd):
    """" Begin a transaction to remove the certificate from a slot in HAProxy’s memory """
    cmdTxt = "del ssl cert %(certfile)s\r\n"
    req_args = ['certfile']
    helpTxt = "Delete delete an unused certificate file."

class commitSslCrt(Cmd):
    """ Commit the transaction so HAProxy detects the change. """
    cmdTxt = "commit ssl cert %(certfile)s\r\n"
    req_args = ['certfile']
    helpTxt = "Commit a certificate file."

class abortSslCrt(Cmd):
    cmdTxt = "abort ssl cert %(certfile)s\r\n"
    req_args = ['certfile']
    helpTxt = "Abort a transaction for a certificate file."

class showFBEnds(Cmd):
    """Base class for getting a listing Frontends and Backends"""
    switch = ""
    cmdTxt = "show stat\r\n"

    def getResult(self, res):
        return "\n".join(self._getResult(res))

    def getResultObj(self, res):
        return self._getResult(res)

    def _getResult(self, res):
        """Show Frontend/Backends. To do this, we extract info from
           the stat command and filter out by a specific
           switch (FRONTEND/BACKEND)"""

        if not self.switch:
            raise Exception("No action specified")

        result = []
        lines = res.split('\n')
        cl = re.compile("^[^,].+," + self.switch.upper() + ",.*$")

        for e in lines:
            me = re.match(cl, e)
            if me:
                print(e)
                result.append(e.split(",")[0])
        return result

class showFrontends(showFBEnds):
    """Show frontends command."""
    switch = "frontend"
    helpTxt = "List all Frontends."

class showBackends(showFBEnds):
    """Show backends command."""
    switch = "backend"
    helpTxt = "List all Backends."

class showInfo(Cmd):
    """Show info HAProxy command"""
    cmdTxt = "show info\r\n"
    helpTxt = "Show info on HAProxy instance."

    def getResultObj(self, res):
        resDict = {}
        for line in res.split('\n'):
            k, v = line.split(':')
            resDict[k] = v

        return resDict

class showSessions(Cmd):
    """Show sess HAProxy command"""
    cmdTxt = "show sess\r\n"
    helpTxt = "Show HAProxy sessions."

    def getResultObj(self, res):
        return res.split('\n')

class baseStat(Cmd):
    """Base class for stats commands."""

    def getDict(self, res):
        # clean response
        res = re.sub(r'^# ', '', res, re.MULTILINE)
        res = re.sub(r',\n', '\n', res, re.MULTILINE)
        res = re.sub(r',\n\n', '\n', res, re.MULTILINE)

        csv_string = StringIO(res)
        return csv.DictReader(csv_string, delimiter=',')

class showServers(baseStat):
    """Show all servers. If backend is given, show only servers for this backend. """
    cmdTxt = "show stat\r\n"
    helpTxt = "Lists all servers. Filter for servers in backend, if set."

    def getResultObj(self, res):
        servers = []

        reader = self.getDict(res)
        for row in reader:
            row = OrderedDict(row)
            # show only server
            if row['svname'] in ['BACKEND', 'FRONTEND']:
                continue

            # filter server for given backend
            if self.args['backend'] and row['pxname'] != self.args['backend']:
                continue

            # add id
            row['id'] = f"{row['pxname']}/{row['svname']}"
            row.move_to_end('id', last=False)
            servers.append(dict(row))

        return servers
