#!/usr/local/bin/python3

import argparse
from enum import Enum
import ujson
import re
from typing import List,Dict
from lib import VtySH

class Re(object):
  """ Custom regex helper class

  Use to conveniently build switch-case like constructs using if/elif
  Kudos to https://stackoverflow.com/a/4980181
  """

  def __init__(self):
    self.last_match = None
  def match(self,pattern,text):
    self.last_match = re.match(pattern,text)
    return self.last_match
  def search(self,pattern,text):
    self.last_match = re.search(pattern,text)
    return self.last_match

class FRRTableReader:
  def __init__(self, titles: List[str]=[]):
    self.titles = titles

  def read_header(self, line: str, start_without_title: bool=False, start_without_title_name: str='status'):
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
    
  def read_line(self, line: str) -> List[Dict[str, str]]:
    # parsing is relatively straightforward
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

  def _show(self, suffix: str):
    # execute the command and filter out empty lines so subsequent iterations don't have to  deal with them
    return list(filter(None,vtysh.execute(command='show ' + suffix, translate=bytes.decode).split('\n')))

class OSPF(Daemon):
  RE_ROUTER = r'OSPF Router with ID \(([\.\d]+)\)'
  RE_ROUTERLINKSTATES = r'Router Link States \(Area ([\.\d]+)\)'
  RE_NETLINKSTATES = r'Net Link States \(Area ([\.\d]+)\)'
  RE_SUMMARYLINKSTATES = r'Summary Link States \(Area ([\.\d]+)\)'
  EXTERNALLINKSTATES = 'AS External Link States'

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

        myre = Re()
        # this is going to be dirty
        if myre.search(self.RE_ROUTER, heading):
          router = myre.last_match.group(1)
          if not router in db:
            db[router] = {}
          mode = 'router'
        elif myre.search(self.RE_ROUTERLINKSTATES, heading):
          mode = 'router_link_state_area'
          area = myre.last_match.group(1)
          if not mode in db[router]:
            db[router][mode] = {}
          if not area in db[router][mode]:
            db[router][mode][area] = []
          tr = rltr
        elif myre.search(self.RE_NETLINKSTATES, heading):
          mode = 'net_link_state_area'
          area = myre.last_match.group(1)
          if not mode in db[router]:
            db[router][mode] = {}
          if not area in db[router][mode]:
            db[router][mode][area] = []
          tr = nltr
        elif myre.search(self.RE_SUMMARYLINKSTATES, heading):
          mode = 'summary_link_state_area'
          area = myre.last_match.group(1)
          if not mode in db[router]:
            db[router][mode] = {}
          if not area in db[router][mode]:
            db[router][mode][area] = []
          tr = sltr
        elif heading == self.EXTERNALLINKSTATES:
          mode = 'external_states'
          if not mode in db[router]:
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
            raise DaemonError('attempting to parse a table row but the mode is \'router\'. please debug the FRR output:\n\n\n'+lines)
          if mode == 'external_states':
            db[router][mode].append(tr.read_line(line))
          else:
            db[router][mode][area].append(tr.read_line(line))
    
    return db

class OSPFv3(Daemon):
  def _show(self, suffix: str):
    return super()._show('ipv6 ospf6 ' + suffix)

  def database(self):
    return {}

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

    return interface

  def neighbor(self):
    neighbors = []
    lines = self._show('neighbor')

    tr = FRRTableReader(titles=['Neighbor ID','Pri', 'DeadTime', 'State/IfState', 'Duration I/F[State]'])
    ll = lines.pop(0)
    tr.read_header(ll)

    for line in lines:
      neighbor = tr.read_line(line)
      neighbor['Pri'] = int(neighbor['Pri'])
      neighbors.append(neighbor)

    return neighbors

  def overview(self):
    overview = {}
    lines = map(str.strip, self._show('overview'))

    return overview

# le script
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
  vtysh = VtySH()
  ospf = OSPF(vtysh)
  ospfv3 = OSPFv3(vtysh)

  result = {}
  if args.ospf_database:
    result['ospf_database'] = ospf.database()
  elif args.ospfv3_database:
    result['ospfv3_database'] = ospfv3.database()
  elif args.ospfv3_route:
    result['ospfv3_route'] = ospfv3.route()
  elif args.ospfv3_interface:
    result['ospfv3_interface'] = ospfv3.interface()
  elif args.ospfv3_neighbor:
    result['ospfv3_neighbors'] = ospfv3.neighbor()
  elif args.ospfv3_overview:
    result['ospfv3_overview'] = ospfv3.overview()

  print(ujson.dumps(result, escape_forward_slashes=False))