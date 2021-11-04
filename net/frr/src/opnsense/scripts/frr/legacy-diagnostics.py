#!/usr/local/bin/python3
"""
    Copyright (c) 2020 Marc Leuser
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

import argparse
import ujson
import re
from typing import List, Dict
from lib import VtySH


class Re:
    """ Custom regex helper class (source https://stackoverflow.com/a/4980181)
        Use to conveniently build switch-case like constructs using if/elif
    """

    def __init__(self):
        self.last_match = None

    def match(self, pattern, text):
        self.last_match = re.match(pattern, text)
        return self.last_match

    def search(self, pattern, text):
        self.last_match = re.search(pattern, text)
        return self.last_match


class FRRTableReader:
    def __init__(self, titles: List[str] = None):
        self.titles = titles if titles is not None else list()
        self.columns = list()

    def read_header(self, line: str, start_without_title: bool = False, start_without_title_name: str = 'status'):
        # we're going to create a list of columns. each entry contains title, start index and end index for easy parsing
        self.columns = []

        # the first column's title may sometimes be empty in FRR's output. we'll have to give it a name though
        if start_without_title:
            self.columns.append({
                'title': start_without_title_name,
                'start_index': 0,
                'end_index': None
            })

        # fill the list with subsequent columns
        for title in self.titles:
            try:
                # find the start index of the current title in the header
                start_index = line.index(title)
            except ValueError:
                # just skip the column if it can't be found
                continue

            # last column's end index is this column's start index
            if self.columns:
                self.columns[-1]['end_index'] = start_index

            # add the current column to the list
            self.columns.append({
                'title': title,
                'start_index': start_index,
                'end_index': None
            })

    def read_line(self, line: str) -> Dict[str, str]:
        result = {}

        # sanity check: the line has to be long enough to contain all columns,
        # so its length must be greater than the last column's start index
        if len(line) > self.columns[-1]['start_index']:
            for column in self.columns:
                # use the column's name as dict key and just extract the data from start to end index
                result[column['title'].strip()] = line[column['start_index']:column['end_index']].strip()

        return result


class DaemonError(Exception):
    pass


class Daemon:
    def __init__(self, vtysh: VtySH):
        self.vtysh = vtysh
        self.myre = Re()

    def _show(self, suffix: str):
        # execute the command and filter out empty lines so subsequent iterations don't have to  deal with them
        return list(filter(None, self.vtysh.execute(command='show ' + suffix, translate=bytes.decode).split('\n')))


class OSPF(Daemon):
    def _show(self, suffix: str):
        return super()._show('ip ospf ' + suffix)

    def database(self):
        db = {}
        # table reader for Route Link States
        rltr = FRRTableReader(titles=['Link ID', 'ADV Router', 'Age', 'Seq#', 'CkSum', 'Link count'])
        # table reader for Net Link States
        nltr = FRRTableReader(titles=['Link ID', 'ADV Router', 'Age', 'Seq#', 'CkSum'])
        # table reader for Summary Link States
        sltr = FRRTableReader(titles=['Link ID', 'ADV Router', 'Age', 'Seq#', 'CkSum', 'Route\n'])
        # table reader for AS External Link States (columns are identical to Summary Link States)
        eltr = sltr

        # get the FRR output
        lines = self._show('database')

        # placeholder for the active table reader for the coming line
        tr = None
        # whether or not the header for the current section has already been parsed
        header_parsed = False
        # the current router
        router = None
        # the current area
        area = None
        # the current mode
        mode = None

        for line in lines:
            if line.startswith(' '):
                # this is a heading
                heading = line.strip()
                header_parsed = False

                # this is going to be dirty
                if self.myre.search(r'OSPF Router with ID \(([\.\d]+)\)', heading):
                    router = self.myre.last_match.group(1)
                    if router not in db:
                        db[router] = {}
                    mode = 'router'
                elif self.myre.search(r'Router Link States \(Area ([\.\d]+)\)', heading):
                    mode = 'router_link_state_area'
                    area = self.myre.last_match.group(1)
                    if mode not in db[router]:
                        db[router][mode] = {}
                    if area not in db[router][mode]:
                        db[router][mode][area] = []
                    tr = rltr
                elif self.myre.search(r'Net Link States \(Area ([\.\d]+)\)', heading):
                    mode = 'net_link_state_area'
                    area = self.myre.last_match.group(1)
                    if mode not in db[router]:
                        db[router][mode] = {}
                    if area not in db[router][mode]:
                        db[router][mode][area] = []
                    tr = nltr
                elif self.myre.search(r'Summary Link States \(Area ([\.\d]+)\)', heading):
                    mode = 'summary_link_state_area'
                    area = self.myre.last_match.group(1)
                    if mode not in db[router]:
                        db[router][mode] = {}
                    if area not in db[router][mode]:
                        db[router][mode][area] = []
                    tr = sltr
                elif heading == 'AS External Link States':
                    mode = 'external_states'
                    if mode not in db[router]:
                        db[router][mode] = []
                    tr = eltr
                else:
                    raise DaemonError('failed to parse heading: ' + heading)
                # told you.
            else:
                if not header_parsed:
                    if mode in ['summary_link_state_area', 'external_states']:
                        # workaround because "Route" matches on "Router" and breaks the offset parsing logic
                        # stay consistent with the previous script, add a trailing newline
                        line += '\n'
                    tr.read_header(line)
                    header_parsed = True
                else:
                    if mode == 'router':
                        raise DaemonError(
                            'attempting to parse a table row but the mode is \'router\'. '
                            'please debug the FRR output:\n\n\n' + line
                        )
                    elif mode == 'external_states':
                        db[router][mode].append(tr.read_line(line))
                    else:
                        db[router][mode][area].append(tr.read_line(line))

        return db


class OSPFv3(Daemon):
    def _show(self, suffix: str):
        return super()._show('ipv6 ospf6 ' + suffix)

    def database(self):
        database = {}
        lines = map(str.strip, self._show('database'))

        # table reader
        tr = FRRTableReader(titles=["Type", "LSId", "AdvRouter", " Age", "  SeqNum", "                       Payload"])
        header_parsed = False
        interface = None
        area = None
        mode = None

        for line in lines:
            if self.myre.search(r'Area Scoped Link State Database \(Area (.*)\)', line):
                header_parsed = False
                mode = 'scoped_link_db'
                area = self.myre.last_match.group(1)
                if mode not in database:
                    database[mode] = {}
                if area not in database[mode]:
                    database[mode][area] = []
            elif self.myre.search(r'I\/F Scoped Link State Database \(I\/F (\S+) in Area (.*)\)', line):
                header_parsed = False
                mode = 'if_scoped_link_state'
                interface = self.myre.last_match.group(1)
                area = self.myre.last_match.group(2)
                if mode not in database:
                    database[mode] = {}
                if interface not in database[mode]:
                    database[mode][interface] = {}
                if area not in database[mode][interface]:
                    database[mode][interface][area] = []
            elif line == 'AS Scoped Link State Database':
                header_parsed = False
                mode = 'as_scoped'
                if mode not in database:
                    database[mode] = []
            else:
                if not header_parsed:
                    tr.read_header(line)
                    header_parsed = True
                else:
                    if mode == 'scoped_link_db':
                        database[mode][area].append(tr.read_line(line))
                    elif mode == 'if_scoped_link_state':
                        database[mode][interface][area].append(tr.read_line(line))
                    elif mode == 'as_scoped':
                        database[mode].append(tr.read_line(line))
                    else:
                        raise DaemonError('invalid mode, failed to parse line: ' + line)

        return database

    def route(self):
        route = []
        lines = map(str.strip, self._show('route'))

        for line in lines:
            columns = re.split(r'\s+', line)
            route.append({
                'f1': columns[0],
                'f2': columns[1],
                'network': columns[2],
                'gateway': columns[3],
                'interface': columns[4],
                'time': columns[5],
            })

        return route

    def interface(self):
        interface = {}
        lines = map(str.strip, self._show('interface'))

        current_if = None
        for line in lines:
            if self.myre.search(r'(\S+) is (down|up), type ([A-Z]+)', line):
                current_if = self.myre.last_match.group(1)
                interface[current_if] = {
                    'up': True if self.myre.last_match.group(2) == 'up' else False,
                    'type': self.myre.last_match.group(3),
                    'enabled': True
                }
            elif self.myre.search(r'Interface ID: (\d+)', line):
                interface[current_if]['id'] = self.myre.last_match.group(1)
            elif self.myre.search(r'OSPF not enabled on this interface', line):
                interface[current_if]['enabled'] = False
            elif self.myre.search(r'Instance ID (\d+), Interface MTU (\d+) \(autodetect: (\d+)\)', line):
                interface[current_if]['instance_id'] = int(self.myre.last_match.group(1))
                interface[current_if]['interface_mtu'] = int(self.myre.last_match.group(2))
                interface[current_if]['interface_mtu_autodetect'] = int(self.myre.last_match.group(3))
            elif self.myre.search(r'(inet |inet6): (\S+)', line):
                family = 'IPv6' if self.myre.last_match.group(1) == 'inet6' else 'IPv4'
                if family not in interface[current_if]:
                    interface[current_if][family] = []
                interface[current_if][family].append(self.myre.last_match.group(2))
            elif self.myre.search(r'MTU mismatch detection: (en|dis)abled', line):
                interface[current_if]['mtu_mismatch_detection'] = True if self.myre.last_match.group(
                    1) == 'en' else False
            elif self.myre.search(r'DR: (\S+) BDR: (\S+)', line):
                interface[current_if]['designated_router'] = self.myre.last_match.group(1)
                interface[current_if]['backup_designated_router'] = self.myre.last_match.group(2)
            elif self.myre.search(r'State (\S+), Transmit Delay (\d+) sec, Priority (\d+)', line):
                interface[current_if]['state'] = self.myre.last_match.group(1)
                interface[current_if]['transmit_delay'] = int(self.myre.last_match.group(2))
                interface[current_if]['priority'] = int(self.myre.last_match.group(3))
            elif self.myre.search(r'Number of I\/F scoped LSAs is (\d+)', line):
                interface[current_if]['number_if_scoped_lsas'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'(\d+) Pending LSAs for (\S+) in Time ([\d:]+)(?: (.*))', line):
                if 'pending_lsas' not in interface[current_if]:
                    interface[current_if]['pending_lsas'] = {}
                interface[current_if]['pending_lsas'][self.myre.last_match.group(2)] = {
                    'time': self.myre.last_match.group(3),
                    'count': self.myre.last_match.group(1),
                    'flags': self.myre.last_match.group(4)
                }
            elif self.myre.search(r'Hello (\d+), Dead (\d+), Retransmit (\d+)', line):
                interface[current_if]['timers'] = {
                    'hello': int(self.myre.last_match.group(1)),
                    'dead': int(self.myre.last_match.group(2)),
                    'retransmit': int(self.myre.last_match.group(3))
                }
            elif self.myre.search(r'Area ID (\S+), Cost (\d+)', line):
                if 'area_cost' not in interface[current_if]:
                    interface[current_if]['area_cost'] = []
                interface[current_if]['area_cost'].append({
                    'area': self.myre.last_match.group(1),
                    'cost': int(self.myre.last_match.group(2))
                })
            elif line in ['Internet Address:', 'Timer intervals configured:']:
                # ignore these strings
                pass
            else:
                raise DaemonError('failed to parse line: ' + line)

        return interface

    def neighbor(self):
        neighbors = []
        lines = self._show('neighbor')

        tr = FRRTableReader(titles=['Neighbor ID', 'Pri', 'DeadTime', 'State/IfState', 'Duration I/F[State]'])
        ll = lines.pop(0)
        tr.read_header(ll)

        for line in lines:
            neighbor = tr.read_line(line)
            neighbor['Pri'] = int(neighbor['Pri'])
            neighbors.append(neighbor)

        return neighbors

    def overview(self):
        overview = {'areas': {}}
        lines = map(str.strip, self._show(''))

        current_area = None

        for line in lines:
            if self.myre.search(r'OSPFv3 Routing Process \((\d+)\) with Router-ID ([\d\.]+)', line):
                overview['router_id'] = self.myre.last_match.group(2)
                overview['routing_process'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'Initial SPF scheduling delay (\d+) millisec\(s\)', line):
                overview['initial_spf_scheduling_delay'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'(Min|Max)imum hold time between consecutive SPFs (\d+) milli?second\(s\)', line):
                if 'hold_time' not in overview:
                    overview['hold_time'] = {}
                overview['hold_time'][self.myre.last_match.group(1).lower()] = int(self.myre.last_match.group(2))
            elif line == 'This router is an ASBR (injecting external routing information)':
                overview['asbr'] = True
            elif self.myre.search(r'SPF timer is (.*)', line):
                overview['spf_timer'] = self.myre.last_match.group(1)
            elif self.myre.search(r'Running (.*)', line):
                overview['running_time'] = self.myre.last_match.group(1)
            elif self.myre.search(r'Number of AS scoped LSAs is (\d+)', line):
                overview['number_as_scoped'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'Hold time multiplier is currently (\d+)', line):
                overview['current_hold_time_multipier'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'Number of areas in this router is (\d+)', line):
                overview['number_of_areas'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'^Area ([\d\.]*)', line):
                current_area = self.myre.last_match.group(1)
                overview['areas'][current_area] = {}
            elif self.myre.search(r'Interface attached to this area: (.*)', line):
                overview['areas'][current_area]['interfaces'] = self.myre.last_match.group(1).split(' ')
            elif self.myre.search(r'Number of Area scoped LSAs is (.*)', line):
                overview['areas'][current_area]['number_lsas'] = int(self.myre.last_match.group(1))
            elif self.myre.search(r'LSA minimum arrival (.*)', line) or \
                    self.myre.search(r'SPF algorithm last executed (.*)', line) or \
                    self.myre.search(r'Last SPF duration (.*)', line) or \
                    self.myre.search(r'SPF last executed (.*)', line) or \
                    self.myre.search(r'Number of Area scoped LSAs is (.*)', line):
                # skip these lines
                pass
            else:
                raise DaemonError('failed to parse line: ' + line)

        return overview


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Python port of the OPNsense FRR output parser.')
    parser.add_argument('-d', '--ospf-database', help='Prints the OSPF Database', action='store_true')
    parser.add_argument('-D', '--ospfv3-database', help='Prints the OSPFv3 Database', action='store_true')
    parser.add_argument('-t', '--ospfv3-route', help='Prints the OSPFv3 routing table', action='store_true')
    parser.add_argument('-I', '--ospfv3-interface', help='Prints OSPFv3 interface information', action='store_true')
    parser.add_argument('-N', '--ospfv3-neighbor', help='Prints OSPFv3 neighbor information', action='store_true')
    parser.add_argument('-O', '--ospfv3-overview', help='Prints an OSPFv3 Summary', action='store_true')
    args = parser.parse_args()

    # initialize VtySH and parser objects
    main_vtysh = VtySH()
    ospf = OSPF(main_vtysh)
    ospfv3 = OSPFv3(main_vtysh)

    main_result = {}
    if args.ospf_database:
        main_result['ospf_database'] = ospf.database()
    elif args.ospfv3_database:
        main_result['ospfv3_database'] = ospfv3.database()
    elif args.ospfv3_route:
        main_result['ospfv3_route'] = ospfv3.route()
    elif args.ospfv3_interface:
        main_result['ospfv3_interface'] = ospfv3.interface()
    elif args.ospfv3_neighbor:
        main_result['ospfv3_neighbors'] = ospfv3.neighbor()
    elif args.ospfv3_overview:
        main_result['ospfv3_overview'] = ospfv3.overview()

    print(ujson.dumps(main_result, escape_forward_slashes=False))
