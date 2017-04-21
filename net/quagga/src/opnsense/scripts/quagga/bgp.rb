#!/usr/local/bin/ruby
=begin
Copyright 2017 Fabian Franz
Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
=end
require 'json'

VTYSH = '/usr/local/bin/vtysh'

def show_ip_bgp
  output = `#{VTYSH} -d bgpd -c "show ip bgp"`
  return {} if output.include? "No BGP process is configured"
  output = output.split("\n")
  bgp = {}
  
  # global BGP information (version and ID)
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

  # get begin of header (number of the first char of the string)
  header = output.shift
  header_offset = {}
  header_offset[0] = 'status'
  ["Network", "Next Hop", "Metric", "LocPrf", "Weight", "Path"].map do |x|
    header_offset[header.index(x)] = x
  end


  # make ranges: this will make a range of the first char of the sting until
  # the the char befor the next heading begins
  ranges = []
  0.upto (header_offset.keys.length - 3) do |i|
    ranges << ((header_offset.keys[i])...(header_offset.keys[i + 1]))
  end
  # the last one has no next heading - this will go to the end of the line
  ranges.push ((header_offset.keys.last)..-1) # path


  # collect the real data
  bgp['output'] = []
  while line = output.shift
    tmp = {}
    # kill empty lines
    unless line && (line.include? 'Total' || line.strip.length > 10 || line.include?(' out of'))
      ranges.each do |r|
        # the string starts here
        b = r.begin
        # get the heading starting where the string starts
        n = header_offset[b]
        # get the data or return an empty string
        tmp[n] = line[r]&.strip || ""
      end
      # replace characters by the meaning
      tmp['status'] = tmp['status'].split("").map {|x| {dn: status_codes[x], abb: x} }
      tmp['Path'] = tmp['Path'].split("").map {|x| {dn: origin_codes[x], abb: x} }
      # add the line - except if it was empty
      bgp['output'] << tmp unless tmp['Network'] == ""
    end
  end
  # final return
  bgp
end

puts show_ip_bgp.to_json
