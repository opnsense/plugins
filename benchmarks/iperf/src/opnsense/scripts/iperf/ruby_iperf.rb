#!/usr/local/bin/ruby

=begin
Copyright (C) 2017 Fabian Franz
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
=end

require 'open3'
require 'pp'
require 'json'
require 'logger'
require 'rexml/document'
require 'timeout'
require 'socket'

$instances = {}
SOCKET_FILE = '/var/run/iperf-manager.sock'
ONE_HOUR = 3600
KEY_START_TIME = 'start_time'
KEY_PORT = 'port'
LF = "\n"

def execute_firewall_port(rule)
  Open3.popen3('pfctl -a iperf -f -') do |stdin, stdout, stderr, wait_thr|
    stdin.puts rule
    stdin.close
    stdout.close
    stderr.close
  end
end

def flush_firewall_rules
  `pfctl -a iperf -F rules`
end

def create_open_firewall_port_rule(interface, port, log = 'log')
  "pass in #{log} quick on #{interface} inet proto tcp from {any} to {(self)} port {#{port}} keep state"
end

def gen_firewall_rules
  rules = ''
  $instances.each do |thread, entry|
    pp (["thread alive?", thread.alive?, entry])
    if thread.alive?
      if entry.has_key?('interface') && entry.has_key?('port') && !entry.has_key?('result')
        rules << create_open_firewall_port_rule(entry['interface'], entry['port']) << LF
      end
    end
  end
  execute_firewall_port(rules) if rules.length > 0
end

def open_ports
  `sockstat -l`.lines.map do |x|
    # scan for integers after :
    x.scan(/.*:(\d+).*/)&.first&.first&.to_i
  end.uniq.reject(&:nil?).sort
end

def forwarded_ports
  config = REXML::Document.new(File.new("/conf/config.xml"))
  xml_firewall = config.elements['opnsense/nat'].children.select do |x|
    x.node_type == :element && x.name == 'rule'
  end
  xml_firewall.map do |x|
    x&.elements['local-port']&.text&.to_i
  end.sort.uniq

end

def find_open_ports
  ports = (1024..65000).to_a
  # remove the ports open by the firewall itself
  begin
    ports -= open_ports
  rescue
    print $!
  end
# remove the nat ports
  begin
    ports -= forwarded_ports
  rescue
    print $!
  end
  ports
end

def find_open_port
  find_open_ports.sample
end

def run_iperf3(port)
  output = pid = exit_status = ''
  Open3.popen3(['iperf3', '-J', '-f', 'M', '-V', '-s', '-1',  '-p', port].join ' ') do |stdin, stdout, stderr, wait_thr|
    pid = wait_thr.pid # pid of the started process.
    stdin.close
    exit_status = wait_thr.value
    output = JSON.parse(stdout.read)
    stdout.close
    stderr.close
  end
  output
end

def run_test(interface = 'any', data)
  ret = nil
  data[KEY_PORT] = port = find_open_port
  # regenerate ruleset
  flush_firewall_rules
  gen_firewall_rules
  # do perform test
  begin
    # timeout 10 min
    begin
      Timeout.timeout(600) do
        data['result'] = ret = run_iperf3 port
      end
    rescue Timeout::Error
      data['result'] = ret = [-1,{'error' => 'timeout'}]
    end
  rescue
    puts $!
  end
  # end perform test
  # regenerate ruleset
  flush_firewall_rules
  gen_firewall_rules
  ret
end

def run_test_thread(interface = 'any')
  data = {}
  t = Thread.new do
    data[KEY_START_TIME] = Time.now
    data['interface'] = interface
    run_test(interface, data)
  end
  $instances[t] = data
end

Thread.new do
  loop do
    $instances.each do |key, value|
      current_time = Time.now
      if (current_time - value[KEY_START_TIME]) > ONE_HOUR
        $instances.delete(key)
        key.kill unless key.stop?
      end
    end
    sleep 10
  end
end

# delete stale socket file
File.unlink(SOCKET_FILE) if File.exist? SOCKET_FILE

server = UNIXServer.new(SOCKET_FILE)
begin
  loop do
    Thread.start(server.accept) do |connection|
      until connection.closed?
        begin
          command = connection.gets.strip.split(' ')
          case command.shift
          when 'start'
            interface = 'any'
            if command.length > 0
              intf = command.shift
              # check if a valid interface was given
              interface = intf if intf =~ /^[a-z0-9_-]+$/
            end
            data = run_test_thread interface
            connection.puts '{"status": "queued job"}'
          when 'query'
            connection.puts $instances.values.to_json
          when 'bye'
            connection.puts '{"status": "disconnecting"}'
            connection.close
          else
            connection.puts '{"status": "unknown command"}'
          end
        rescue
        end
      end
    end
  end
rescue
  server.close
end
