#!/usr/local/bin/ruby
=begin
Copyright 2017 Fabian Franz
Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation and/or
   other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
=end

require 'json'
require 'shellwords'
require 'pp'

$QUAGGA_DEBUG = false

class VTYSH
  def initialize(path = '/usr/local/bin/vtysh')
    @path = path
  end

  def execute(param)
    o = `#{@path} -c #{param.shellescape}`
    raise "error" if o.length <= 2
    raise "command error - command: #{param}" if o.include? "% Unknown command"
    o
  end

  #def execute(param)
  #  fn = param.sub("show","sh").gsub(" ","_")
  #  File.read(fn)
  #end
end

class QuaggaTableReader
  attr_accessor :headers
  def initialize(headers = [])
    @headers = headers
  end
  def read_headline(line, start_without_header = false, start_without_header_name = 'status')
    # get begin of header (number of the first char of the string)
    header = line
    header_offset = {}
    header_offset[0] = start_without_header_name if start_without_header
    @headers.map do |x|
      header_offset[header.index(x)] = x.strip
    end


    # make ranges: this will make a range of the first char of the sting until
    # the the char befor the next heading begins
    ranges = []
    0.upto (header_offset.keys.length - 2) do |i|
      ranges << ((header_offset.keys[i])...(header_offset.keys[i + 1]))
    end
    # the last one has no next heading - this will go to the end of the line
    ranges.push ((header_offset.keys.last)..-1) # path
    @header_offset = header_offset
    @ranges = ranges
    nil
  end

  def read_entry(line, expand_fields = {})
    raise "heading missing" unless @ranges
    tmp = {}
    return tmp unless line&.strip.length > 2

    @ranges.each do |r|
      # the string starts here
      b = r.begin
      # get the heading starting where the string starts
      n = @header_offset[b]
      # get the data or return an empty string
      tmp[n] = line[r]&.strip || ""
    end
    # replace characters by the meaning
    expand_fields.keys.each do |key|
      tmp[key] = tmp[key].split("").map {|x| {dn: expand_fields[key][x], abb: x} } if tmp[key]
    end
    tmp
  end
end

class General
  def initialize(vtysh)
    @vtysh = vtysh
  end
  def routes(ipv6 = false)
    lines = @vtysh.execute("show ip#{ipv6 ? 'v6' : ''} route").lines

    # headers
    meanings = {}
    while (line = lines.shift.strip) != ''
      line = line.gsub('Codes: ','')
      line.split(",").each do |meaning|
        short, long = meaning.strip.split(" - ")
        meanings[short] = long
      end
    end

    # you don't have to understand this regex ;)
    entry_regex = /(\S+?)\s+?(\S+?)(?: \[(\d+)\/(\d+)\])? (?:via (\S+?)|is ([^,]+?)), ([^,\n]+)(?:, (\S+))?/
    entries = []
    while (line = lines.shift&.strip)
      if line.length > 10
        code, network, ad, metric, via, direct, interface, time = line.scan(entry_regex).first
        code = code.split('').map {|c| {short: c, long: meanings[c]}}
        entries << {code: code, network: (network || direct), ad: ad, via: via, metric: metric, interface: interface, time: time }
      end
    end
    entries
  end

  def routes6
    routes(true)
  end

  def log
    File.read('/var/log/frr.log').lines.select {|l| l.strip.length > 10}.map do |line|
      date, time, service, message = line.split(' ', 4)
      date = date.split('/').reverse.join(".") # format dd.mm.yyyy
      service = service.split(':').first if service
      {date: date, time:time, service: service, message: message }
    end
  end
end

class OSPF
  def initialize(vtysh)
    @vtysh = vtysh
  end
  def neighbors
    qta = QuaggaTableReader.new(["Neighbor ID", "Pri", "State", "Dead Time", "Address", "Interface", "RXmtL", "RqstL", "DBsmL"])
    lines = @vtysh.execute("show ip ospf neighbor").lines
    lines.shift # empty line
    data = []
    qta.read_headline(lines.shift)
    while (line = lines.shift) && (line.length > 2)
      data << qta.read_entry(line)
    end
    data
  end

  def interface
    lines = @vtysh.execute("show ip ospf interface").lines
    interfaces = {}
    current_if = ''
    while line = lines.shift
      next if line.strip.length <= 1
      if line[0] != ' ' # we are in a heading
        current_if = line.split(" ").first
        interfaces[current_if] = {}
        current_if = interfaces[current_if]
        current_if[:enabled] = true
        lines.shift
      else
        line.strip!
        case line
        when 'OSPF not enabled on this interface'
          current_if[:enabled] = false
        when /Internet Address ([^,]+?), Broadcast ([^,]+?), Area (.*)/
          current_if[:address] = $1
          current_if[:broadcast] = $2
          current_if[:area] = $3
        when /MTU mismatch detection:(.*)/
          current_if[:mtu_mismatch_detection] = ($1 == 'enabled')
        when /Router ID ([^,]+?), Network Type ([^,]+?), Cost: (\d+)/
          current_if[:router_id] = $1
          current_if[:network_type] = $2
          current_if[:cost] = $3.to_i
        when /Transmit Delay is (\d+) sec, State ([^,]+?), Priority (\d+)/
          current_if[:transmit_delay] = $1.to_i
          current_if[:state] = $2
          current_if[:priority] = $3.to_i
        when "No designated router on this network"
          current_if[:designated_router] = nil
        when /Designated Router \(ID\) ([^,]+?), Interface Address (.*)/
          current_if[:designated_router] = $1
          current_if[:designated_router_interface_address] = $2
        when "No backup designated router on this network"
          current_if[:backup_designated_router] = nil
        when /Timer intervals configured, Hello (\d+)s, Dead (\d+)s, Wait (\d+)s, Retransmit (\d+)/
          current_if[:intervals] = {hello: $1.to_i, dead: $2.to_i, wait: $3.to_i, retransmit: $4.to_i}
        when /Multicast group memberships: (.*)/
          current_if[:multicast_group_memberships] = $1.split(" ")
        when /Hello due in ([\d\.]+|inactive)s?/
          current_if[:hello_due_in] = $1 == 'inactive' ? $1 : $1.to_f
        when /Neighbor Count is (\d+), Adjacent neighbor count is (\d+)/
          current_if[:neighbor_count] = $1.to_i
          current_if[:adjacent_neighbor_count] = $2.to_i
        else
          # make sure there is an array to write in
          current_if[:unparsed] ||= []
          current_if[:unparsed] << line
          puts line if $QUAGGA_DEBUG
        end
      end
    end
    interfaces
  end

  def database
    lines = @vtysh.execute("show ip ospf database").lines
    db = {}
    heading = ''
    router = ''
    router_link_states_area = ''
    mode = :none
    qta = nil
    while line = lines.shift
      next if line == ''
      if line[0] == ' ' # heading
        heading = line.strip
        case heading
        when /OSPF Router with ID \(([\.\d]+)\)/
          router = $1
          db[router] ||= {}
          mode = :router
        when /Router Link States \(Area ([\.\d]+)\)/
          router_link_states_area = $1
          db[router]['router_link_state_area'] ||= {}
          db[router]['router_link_state_area'][$1] ||= []
          mode = :router_link_state
          qta = nil
        when /Net Link States \(Area ([\.\d]+)\)/
          net_link_states_area = $1
          db[router]['net_link_state_area'] ||= {}
          db[router]['net_link_state_area'][$1] ||= []
          mode = :net_link_state
          qta = nil
        when 'AS External Link States'
          mode = :states
          db[router]['external_states'] ||= []
          qta = nil
        else
          puts "unknown heading" if $QUAGGA_DEBUG
        end
      else
        if qta == nil
          case mode
          when :router_link_state
            qta = QuaggaTableReader.new(["Link ID", "ADV Router", "Age", "Seq#", "CkSum", "Link count"])
          when :net_link_state
            qta = QuaggaTableReader.new(["Link ID", "ADV Router", "Age", "Seq#", "CkSum"])
          when :states
            qta = QuaggaTableReader.new(["Link ID", "ADV Router", "Age", "Seq#", "CkSum", "Route\n"])
          else
            next
          end
          headline = lines.shift
          qta.read_headline(headline,true)
        else
          entry = qta.read_entry(line)
          case mode
          when :router_link_state
            db[router]['router_link_state_area'][router_link_states_area] << entry
          when :net_link_state
            db[router]['net_link_state_area'][net_link_states_area] << entry
          when :states
            db[router]['external_states'] << entry
          end
        end
      end
      # table
    end
    db
  end

  def route
    lines = @vtysh.execute("show ip ospf route").lines
    heading = ''
    route = {}
    last_line = []
    while line = lines.shift
      if line[0] == "=" #heading
        heading = line.scan(/=* ([^=]*) =*/).first.first
        route[heading] = []
      else # data
        case line.strip
        when /N\s+([\d\.\/]+)\s+\[(\d+)\]\s+area:\s(.*)/
          last_line = {network: $1, cost: $2.to_i, area: $3, type: 'N'}
          route[heading] << last_line
        when /N (E(?:\d+) (?:\S+))\s+\[([\d\/]+)\] tag: (\d+)/
          last_line = {network: $1, cost: $2, tag: $3.to_i, type: 'N'}
          route[heading] << last_line
        when /(?:(directly attached) to|via ([^,]+),) (.*)/
          last_line[:via] = $1 || $2
          last_line[:via_interface] = $3
        when /R\s+(\S+)\s+\[(\d+)\] area: ([^,]+)(, ASBR)/
          last_line = {ip: $1, cost: $2.to_i, area: $3, asbr: (", ASBR" == $4), type: 'R'}
          route[heading] << last_line
        else
          puts line if $QUAGGA_DEBUG
        end
      end
    end
    route
  end

  def overview
    lines = @vtysh.execute("show ip ospf").lines
    overview = {rfc2328_conform: false, asbr: false}
    while line = lines.shift&.strip
      case line
      when /OSPF Routing Process, Router ID: ([\d\.]+)/
        overview[:router_id] = $1
      when "This implementation conforms to RFC2328"
        overview[:rfc2328_conform] = true
      when /OpaqueCapability flag is (\S+)/
        overview[:opaque_capability] = ($1 == 'enabled')
      when /Initial SPF scheduling delay (\d+) millisec\(s\)/
        overview[:initial_spf_scheduling_delay] = $1.to_i
      when /(Min|Max)imum hold time between consecutive SPFs (\d+) millisec\(s\)/
        overview[:hold_time] ||= {}
        overview[:hold_time][$1.downcase] = $2.to_i
      when "This router is an ASBR (injecting external routing information)"
        overview[:asbr] = true
      when /Number of external LSA (\d+). Checksum Sum ([x\d]+)/
        overview[:external_lsa] = {count: $1.to_i, checksum: $2}
      when /Number of opaque AS LSA (\d+). Checksum Sum ([x\d]+)/
        overview[:opaque_as_lsa] = {count: $1.to_i, checksum: $2}
      when /Refresh timer (\d+) secs/
        overview[:refresh_timer] = $1.to_i
      when /Number of areas attached to this router: (\d+)/
        overview[:areas_attached_count] = $1.to_i
      when /Hold time multiplier is currently (\d+)/
        overview[:current_hold_time_multipier] = $1.to_i
      when /RFC1583Compatibility flag is (\S+)/
        overview[:rfc1583_compatibility] = ($1 == 'enabled')
      when /SPF timer is (.*)/
        overview[:spf_timer] = $1
      when ""
        break
      else
        puts line if $QUAGGA_DEBUG
      end
    end
    # general overview has ended - now the area overviews come
    overview[:areas] = {}
    current_area = {}
    while line = lines.shift&.strip
      case line
      when /Area ID: (.*)/
        current_area = {}
        overview[:areas][$1] = current_area
      when /Number of interfaces in this area: Total: (\d+), Active: (\d+)/
        current_area[:interfaces] = {total: $1.to_i,active:  $2.to_i}
      when /Number of (router|network|summary) LSA (\d+). Checksum Sum ([\da-fx]+)/
        current_area[:lsa] ||= {}
        current_area[:lsa][$1] = {count: $2.to_i, checksum: $3}
      when /Number of LSA (\d+)/
        current_area[:lsa] ||= {}
        current_area[:lsa][:count] = $1.to_i
      when /Number of (opaque (?:area|link)|NSSA|ASBR summary) LSA (\d+). Checksum Sum ([\da-fx]+)/
        current_area[:lsa] ||= {}
        current_area[:lsa][$1] = {count: $2.to_i, checksum: $3}
      when /Number of fully adjacent neighbors in this area: (\d+)/
        current_area[:fully_adjacent_neighbor_count] = $1.to_i
      when /SPF algorithm executed (\d) times/
        current_area[:spf_exec_count] = $1.to_i
      when "Area has no authentication"
        current_area[:auth] = "none"
      else
        puts line if $QUAGGA_DEBUG
      end
    end
    overview
  end
end

class BGP
  def initialize(sh)
    @vtysh = sh
  end

  def overview
    output = @vtysh.execute('show ip bgp')
    return {} if output.include? "No BGP process is configured"
    return {} unless output.include? 'version' # we get an empty output if quagga is not running
    output = output.split("\n")
    bgp = {}

    x,y = output.shift.scan(/.*?version is (\d+).*?ID is ([0-9\.]+).*/).first
    bgp['table_version'] = x
    bgp['local_router_id'] = y

    # find out, what the status abbreviations mean
    status_codes = {}
    line = output.shift
    line.split(":").last.strip.split(",").each do |x|
      k,v = x.strip.split(" ")
      status_codes[k] = v
    end
    while line.end_with? ","
      line = output.shift
      line.strip.split(",").each do |x|
        k,v = x.strip.split(" ")
        status_codes[k] = v
      end
    end

    # same like before but for the origin codes
    origin_codes = {}
    output.shift.split(":").last.strip.split(",").each do |x|
      k,v = x.strip.split(" - ")
      origin_codes[k] = v
    end

    # drop empty line
    output.shift
    # read entries
    bgp['output'] = []
    qta = QuaggaTableReader.new(["Network", "Next Hop", "Metric", "LocPrf", "Weight", "Path"])
    qta.read_headline(output.shift,true)
    while line = output.shift&.strip
      break if line == ''
      data = qta.read_entry(line)
      data['status'] = data['status'].split("").map {|x| {dn: status_codes[x], abb: x} }
      data['Path'] = data['Path'].split("").map {|x| {dn: origin_codes[x], abb: x} }
      bgp['output'] << data
    end
    bgp
  end
end

class OSPFv3
  def initialize(sh)
    @vtysh = sh
  end

  def overview
    lines = @vtysh.execute("show ipv6 ospf6").lines
    overview = {}
    while line = lines.shift&.strip
      case line
      when /OSPFv3 Routing Process \((\d+)\) with Router-ID ([\d\.]+)/
        overview[:router_id] = $2
        overview[:routing_process] = $1.to_i
      when /Initial SPF scheduling delay (\d+) millisec\(s\)/
        overview[:initial_spf_scheduling_delay] = $1.to_i
      # this line contains a typo in the output - I made it to work with and without
      # this typo
      when /(Min|Max)imum hold time between consecutive SPFs (\d+) milli?second\(s\)/
        overview[:hold_time] ||= {}
        overview[:hold_time][$1.downcase] = $2.to_i
      when "This router is an ASBR (injecting external routing information)"
        overview[:asbr] = true
      when /SPF timer is (.*)/
        overview[:spf_timer] = $1
      when /Running (.*)/
        overview[:running_time] = $1
      when /Number of AS scoped LSAs is (\d+)/
        overview[:number_as_scoped] = $1.to_i
      when /Hold time multiplier is currently (\d+)/
        overview[:current_hold_time_multipier] = $1.to_i
      when /Number of areas in this router is (\d+)/
        overview[:number_of_areas] = $1.to_i
      when ""
        break
      else
        # debug
        puts line if $QUAGGA_DEBUG
      end
    end
    # general overview has ended - now the area overviews come
    overview[:areas] = {}
    current_area = {}
    while line = lines.shift&.strip
      case line
      when /^Area ([\d\.]*)/
        current_area = {}
        overview[:areas][$1] = current_area
      when /Interface attached to this area: (.*)/
        current_area[:interfaces] = $1.split(" ")
      when /Number of Area scoped LSAs is (.*)/
        current_area[:number_lsas] = $1.to_i
      else
        puts line if $QUAGGA_DEBUG
      end
    end
    overview
  end

  def linkstate
    lines = @vtysh.execute("show ipv6 ospf6 linkstate").lines
    linkstate = {}

    qta = nil
    current_area = []
    while line = lines.shift&.strip
      case line
      when /SPF Result in Area (.*)/
        linkstate[$1] = current_area = []
        qta = QuaggaTableReader.new(["Type","Router-ID", "Net-ID", "Rtr-Bits", "Options", "Cost"])
        lines.shift
        qta.read_headline(lines.shift)
      else
        if line.length > 10
          current_area << qta.read_entry(line)
        end
      end
    end
    linkstate
  end

  def route
    route = []
    lines = @vtysh.execute("show ipv6 ospf6 route").lines

    lines.each do |line|
      f1, f2, network, gateway, interface, time = line.strip.split(/\s+/)
      route << { f1: f1,
                 f2: f2,
                 network: network,
                 gateway: gateway,
                 interface: interface,
                 time: time }
    end
    route
  end

  def neighbors
    qta = QuaggaTableReader.new(["Neighbor ID","Pri", "DeadTime", "State/IfState", "Duration I/F[State]"])
    neighbor = []
    nb = @vtysh.execute("show ipv6 ospf6 neighbor").lines
    qta.read_headline(nb.shift.strip)
    while line = nb.shift&.strip
      puts line
      if line.length > 10
        tmp = qta.read_entry(line)
        tmp['Pri'] = tmp['Pri'].to_i
        neighbor << tmp
      end
    end
    neighbor
  end
  def database
    lines = @vtysh.execute("show ipv6 ospf6 database").lines
    database = {}
    mode = :none
    area = ''
    qta = :none
    while line = lines.shift&.strip
      case line
      when /Area Scoped Link State Database \(Area (.*)\)/
        mode = :scoped_link_db
        database[:scoped_link_db] ||= {}
        database[:scoped_link_db][$1] = area = []
        qta = database_qta(lines)
      when /I\/F Scoped Link State Database \(I\/F (\S+) in Area (.*)\)/
        mode = :if_scoped_link_state
        database[:if_scoped_link_state] ||= {}
        database[:if_scoped_link_state][$1] ||= {}
        area = database[:if_scoped_link_state][$1][$2] ||= []
        qta= database_qta(lines)
      when "AS Scoped Link State Database"
        mode = :as_scoped
        area = database[:as_scoped] ||= []
        qta=database_qta(lines)
        # note: i have no data for this but i think it looks like the others
      else
        if line.length > 10
          area << qta.read_entry(line)
        end
      end
    end
    database
  end

  def interface
    lines = @vtysh.execute("show ipv6 ospf6 interface").lines
    int = {}
    current_if = {}
    while line = lines.shift
      if line.length > 5
        case line.strip
        when /(\S+) is (down|up), type ([A-Z]+)/
          current_if =  int[$1] = {up: ($2 == "up" ? true : false),
                                   type: $3,
                                   enabled: true}
        when /Interface ID: (\d+)/
          current_if[:id] = $1.to_i
        when /OSPF not enabled on this interface/
          current_if[:enabled] = false
        when /Instance ID (\d+), Interface MTU (\d+) \(autodetect: (\d+)\)/
          current_if[:instance_id] = $1.to_i
          current_if[:interface_mtu] = $2.to_i
          current_if[:interface_mtu_autodetect] = $3.to_i
        when "Internet Address:"
          # ignore
        when /(inet |inet6): (\S+)/
          current_if[:IPv6] ||= []
          current_if[:IPv4] ||= []
          family = $1 == 'inet6' ? :IPv6 : :IPv4
          address = $2
          current_if[family] << address
        when /MTU mismatch detection: (en|dis)abled/
          current_if[:mtu_mismatch_detection] = $1 == 'en'
        when /DR: (\S+) BDR: (\S+)/
          current_if[:designated_router] = $1
          current_if[:backup_designated_router] = $2
        when /State (\S+), Transmit Delay (\d+) sec, Priority (\d+)/
          current_if[:state] = $1
          current_if[:transmit_delay] = $2.to_i
          current_if[:priority] = $3.to_i
        when /Number of I\/F scoped LSAs is (\d+)/
          current_if[:number_if_scoped_lsas] = $1.to_i
        when /(\d+) Pending LSAs for (\S+) in Time ([\d:]+)(?: (.*))/
          current_if[:pending_lsas] ||= {}
          current_if[:pending_lsas][$2] = {time: $3,
                                           count: $1,
                                           flags: $4}
        when "Timer intervals configured:"
          # ignore
        when /Hello (\d+), Dead (\d+), Retransmit (\d+)/
          current_if[:timers] = {hello: $1.to_i,
                                 dead: $2.to_i,
                                 retransmit: $3.to_i }
        when /Area ID (\S+), Cost (\d+)/
          current_if[:area_cost] ||= []
          current_if[:area_cost] << {area: $1, cost: $2.to_i }
        else
          puts line if $QUAGGA_DEBUG
        end
      end
    end
    int
  end

  private
  def database_qta(lines)
    # DON'T REMOVE THE SPACES!!!
    # For some reasons the fields are right aligned with the fields which makes it hard
    # to parse. I don't know a better way to get the correct offset except automatically.
    # (Detection of semantic of the fields)
    qta = QuaggaTableReader.new(["Type", "LSId", "AdvRouter", "     Age", "  SeqNum","                       Payload"])
    lines.shift
    qta.read_headline(lines.shift)
    qta
  end
end

require 'optparse'
options = {}

OptionParser.new do |opts|
  opts.banner = "Usage: #{__FILE__} -s section [section specific params]"
  #### OSPFv2
  opts.on("-d", "--ospf-database", "Prints the OSPF Database") do |od|
    options[:ospf_database] = od
  end
  opts.on("-r", "--ospf-route", 'print OSPF routing table') do |od|
    options[:ospf_route] = od
  end
  opts.on("-i", "--ospf-interface", 'print OSPF interface information') do |od|
    options[:ospf_interface] = od
  end
  opts.on("-n", "--ospf-neighbor", 'Print OSPF neighbors') do |od|
    options[:ospf_neighbors] = od
  end
  opts.on("-o", "--ospf-overview", "Print OSPF summary") do |od|
    options[:ospf_overview] = od
  end
  #### OSPFv3
    opts.on("-D", "--ospfv3-database", "Prints the OSPFv3 Database") do |od|
    options[:ospfv3_database] = od
  end
  opts.on("-t", "--ospfv3-route", 'print OSPFv3 routing table') do |od|
    options[:ospfv3_route] = od
  end
  opts.on("-I", "--ospfv3-interface", 'print OSPFv3 interface information') do |od|
    options[:ospfv3_interface] = od
  end
  opts.on("-N", "--ospfv3-neighbor", 'Print OSPFv3 neighbors') do |od|
    options[:ospfv3_neighbors] = od
  end
  opts.on("-O", "--ospfv3-overview", "Print OSPFv3 summary") do |od|
    options[:ospfv3_overview] = od
  end
  #### general things about routing
  opts.on("-R", "--general-routes", "Print Routing Table (IPv4)") do |od|
    options[:general_routes] = od
  end
  opts.on("-6", "--general-routes6", "Print Routing Table (IPv6)") do |od|
    options[:general_routes6] = od
  end
  opts.on("-l", "--general-log", "Print Logs") do |od|
    options[:general_log] = od
  end
  ### BGP
  opts.on("-B", "--bgp-overview", "Print an overview of BGP") do |od|
    options[:bgp_overview] = od
  end
  ### program opts
  opts.on("-H", "--human-readable", "Print the output human readable (not json)") do |od|
    options[:human_readable] = od
  end
  opts.on("-X", "--debug", "Prints debug output") do |od|
    $QUAGGA_DEBUG = true
  end
  opts.on("-h", "--help", "Prints this help") do
    puts opts
    exit
  end
end.parse!
# use the lib
sh = VTYSH.new
ospf = OSPF.new sh
ospfv3 = OSPFv3.new sh
bgp = BGP.new sh
general = General.new sh

result = {}
options.keys.each do |k|
  # if it is true
  if options[k]
    begin
      if k.to_s.include? 'ospf_'
        cmd = k.to_s.split('_').last
        result[k] = ospf.send(cmd)
      elsif k.to_s.include? 'ospfv3'
        cmd = k.to_s.split('_').last
        result[k] = ospfv3.send(cmd)
      elsif k.to_s.include? 'general'
        cmd = k.to_s.split('_').last
        result[k] = general.send(cmd)
      elsif k.to_s.include? 'bgp'
        cmd = k.to_s.split('_').last
        result[k] = bgp.send(cmd)
      end
    rescue # do nothing on an error
      result[k] = "error"
      puts $! if $QUAGGA_DEBUG
    end
  end
end

# ospf.database, general.routes, ospf.interface, ospf.neighbors, ospf.route, ospf.overview
if options[:human_readable]
  pp result
else
  print result.to_json
end
