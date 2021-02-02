#!/usr/bin/env python3
import os
import sys
import argparse
import traceback
import time

sys.path.append(os.path.join(os.path.dirname(__file__), 'lib'))
from haproxy.conn import HaPConn
from haproxy import cmds

SOCKET = '/var/run/haproxy.socket'
VALID_COMMANDS = {
    "set-server-agent": cmds.setServerAgent,
    "set-server-health": cmds.setServerHealth,
    "set-server-state": cmds.setServerState,
    "set-server-weight": cmds.setServerWeight,
    "show-frontends": cmds.showFrontends,
    "show-backends": cmds.showBackends,
    "show-info": cmds.showInfo,
    "show-sessions": cmds.showSessions,
    "show-servers": cmds.showServers,
}

def get_args():
    parser = argparse.ArgumentParser(description='Send haproxy commands via socket.')
    parser.add_argument(
        'command',
        choices=list(VALID_COMMANDS),
        help='The command to execute via haproxy socket'
    )
    parser.add_argument(
        '--backend',
        help='Attempt action on given backend.',
        default=None
    )
    parser.add_argument(
        '--server',
        help='Attempt action on given server.',
        default=None
    )
    parser.add_argument(
        '--server-ids',
        help='Attempt action on a list of server, specified as a comma seperated list e.g. back1/server1,back2/server3',
        default=None
    )
    parser.add_argument(
        '--value',
        help='Specify value for a set command.',
        default=None
    )
    parser.add_argument(
        '--output',
        help='Specify output format.',
        choices=['json', 'bootstrap'],
        default=None
    )
    parser.add_argument(
        '--page-rows',
        help='Limit output to the specified numbers of rows per page.',
        default=None
    )
    parser.add_argument(
        '--page',
        help='Output page number.',
        default=None
    )
    parser.add_argument(
        '--search',
        help='Search for string.',
        default=None
    )
    parser.add_argument(
        '--sort-col',
        help='Sort output on this column.',
        default=None
    )
    parser.add_argument(
        '--sort-dir',
        help='Sort output in this direction.',
        default=None
    )
    parser.add_argument(
        '--debug',
        type=bool,
        help='Show debug output.',
        default=False
    )

    return parser.parse_args()

args = get_args()
command_class = VALID_COMMANDS.get(args.command, None)
command_args = {key: val for key, val in vars(args).items() if key != "command"}

try:
    if args.server_ids:
        # bulk
        command_bulk_args = command_args
        command_bulk_args.pop('server_ids', None)
        for server_id in args.server_ids.split(","):
            command_bulk_args.update({
                'backend': server_id.split("/")[0],
                'server': server_id.split("/")[1]
            })
            con = HaPConn(SOCKET)
            if con:
                result = con.sendCmd(command_class(**command_bulk_args), objectify=False)
                if result:
                    print(f"{server_id}: {result.strip()}")
                con.close()

    else:
        # single
        con = HaPConn(SOCKET)
        if con:
            result = con.sendCmd(command_class(**command_args), objectify=False)
            if result:
                print(result.strip())
        else:
            print(f"Could not open socket {SOCKET}")

except Exception as exc:
    print(f"While talking to {SOCKET}: {exc}")
    if args['debug']:
        tb = traceback.format_exc()
        print(tb)
