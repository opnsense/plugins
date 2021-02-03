# pylint: disable=locally-disabled, too-few-public-methods, no-self-use, invalid-name
"""cmds.py - Implementations of the different HAProxy commands"""

import re
import csv
import json
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

    def getResult(self, res):
        """Returns raw results gathered from HAProxy"""
        if res == '\n':
            res = None
        return res

    def getResultObj(self, res):
        """Returns refined output from HAProxy, packed inside a Python obj i.e. a dict()"""
        return res


class setServerAgent(Cmd):
    """Set server agent command."""
    cmdTxt = "set server %(backend)s/%(server)s agent %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's agent to a new state."


class setServerHealth(Cmd):
    """Set server health command."""
    cmdTxt = "set server %(backend)s/%(server)s health %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's health to a new state."


class setServerState(Cmd):
    """Set server state command."""
    cmdTxt = "set server %(backend)s/%(server)s state %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's administrative state to a new state."


class setServerWeight(Cmd):
    """Set server weight command."""
    cmdTxt = "set server %(backend)s/%(server)s weight %(value)s\r\n"
    req_args = ['backend', 'server', 'value']
    helpTxt = "Force a server's weight to a new state."


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

    def getBootstrapOutput(self, **kwargs):
        rows = kwargs['rows']
        # search
        if kwargs['search']:
            filtered_rows = []
            for row in rows:
                def inner(row):
                    for k, v in row.items():
                        if kwargs['search'] in v:
                            return row
                    return None

                match = inner(row)
                if match:
                    filtered_rows.append(match)
            rows = filtered_rows

        # sort
        rows.sort(key=lambda k: k[kwargs['sort_col']], reverse=True if kwargs['sort_dir'] == 'desc' else False)

        # pager
        total = len(rows)
        pages = [rows[i:i + kwargs['page_rows']] for i in range(0, total, kwargs['page_rows'])]
        if pages and (kwargs['page'] > len(pages) or kwargs['page'] < 1):
            raise KeyError(f"Current page {kwargs['page']} does not exist. Available pages: {len(pages)}")
        page = pages[kwargs['page'] - 1] if pages else []

        return json.dumps({
            "rows": page,
            "total": total,
            "rowCount": kwargs['page_rows'],
            "current": kwargs['page']
        })


class showServers(baseStat):
    """Show all servers. If backend is given, show only servers for this backend. """
    cmdTxt = "show stat\r\n"
    helpTxt = "Lists all servers. Filter for servers in backend, if set."

    def getResult(self, res):
        if self.args['output'] == 'json':
            return json.dumps(self.getResultObj(res))

        if self.args['output'] == 'bootstrap':
            rows = self.getResultObj(res)
            args = {
                "rows": rows,
                "page": int(self.args['page']) if self.args['page'] != None else 1,
                "page_rows": int(self.args['page_rows']) if self.args['page_rows'] != None else len(rows),
                "search": self.args['search'],
                "sort_col": self.args['sort_col'] if self.args['sort_col'] else 'id',
                "sort_dir": self.args['sort_dir'],
            }
            return self.getBootstrapOutput(**args)

        return self.getResultObj(res)

    def getResultObj(self, res):
        servers = []

        reader = self.getDict(res)
        for row in reader:
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
