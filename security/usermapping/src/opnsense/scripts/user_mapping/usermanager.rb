#!/usr/local/bin/ruby
=begin
Copyright 2019 Fabian Franz
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


require 'enumerator'
require 'json'
require 'pp'
require 'socket'
require 'rexml/document'
require 'pry'
require 'ostruct'
require 'open3'
require 'shellwords'
require 'logger'

# global for showing debug output if needed
#$USERMAPPING_DEBUG = false

$login_states = {}
$shared_data = OpenStruct.new
module OSConfig
  class << self
    def read_config_xml
      REXML::Document.new(File.new("/conf/config.xml"))
    end

    def read_config_user_mappings(um_root)
      um_nodes = um_root.each_child.select {|child| child.class == REXML::Element && child.name == 'user_mapping'}
      nodes = []
      um_nodes.each do |node|
        nodes << node.children.reject {|x| x.class == REXML::Text}.map {|x| [x.name, x.text]}.to_h
      end
      nodes
    end

    def get_aliases(config)
      aliases = config.elements['opnsense/OPNsense/Firewall/Alias/aliases'].children
      tmp = aliases.select {|s| s.class == REXML::Element}
      tmp.map do |x|
        uuid = x.attributes['uuid']
        data = x.children.reject {|x| x.class == REXML::Text}.map {|x| [x.name, x.text]}.to_h
        [uuid, data]
      end.to_h
    end

    def find_aliases(object_type, object_name)
      objects_found = $shared_data.user_mappings.select do |um|
        um['object_name'] == object_name && um['type'] == object_type
      end
      aliases = []
      objects_found.each do |obj|
        if !(obj['external_alias'].nil?) && (obj['external_alias'] != '')
          tmp = obj['external_alias'] # UUID of the Alias
          aliases << $shared_data.aliases[tmp] if $shared_data.aliases[tmp]
        end
      end
      aliases
    end
  end
end

module Authorization
  class PFCTL
    class << self
      def call_pfctl(command)
        Open3.popen3(command) do |stdin, stdout, stderr, thread|
          stdin.close
          stdout.close
          stderr.close
        end
      end

      def add_ip_to_alias(alias_name, ip)
        call_pfctl "pfctl -t #{alias_name.shellescape} -T add #{ip.shellescape}"
      end

      def del_ip_from_alias(alias_name, ip)
        call_pfctl "pfctl -t #{alias_name.shellescape} -T del #{ip.shellescape}"
      end
    end
  end
  class User
    attr_accessor :username
    attr_accessor :groups
    attr_accessor :valid_until
    attr_accessor :ip_address

    def initialize(data)
      update_data(data)
    end

    def update(data)
      update_data data
    end

    def update_data(data)
      raise "ip missing" if data['ip'].nil?
      if data['groups']&.is_a? Array
        remove_from_pf_groups(data) if @groups && !@groups.empty?
        @groups = data['groups'] # use the provided groups
      else
        # remove unused groups
        @groups = []
      end
      # username may not be set (after first login)
      if data['username']
        if @username != data['username']
          remove_user_from_pf(data['username'])
        end
        @username = data['username']
      end
      raise "username missing" if @username.nil?
      @ip_address = data['ip']
      if data['valid_until']
        @valid_until = data['valid_until']
      else
        @valid_until = Time.now + 60 # default: 60 sec
      end

      # add the new aliases
      add_groups_to_pf
      add_user_to_pf
    end

    def logout(ip = nil)
      $login_states.delete(ip || @ip_address)
      remove_from_pf_groups({'groups' => []})
      remove_user_from_pf(@username)
    end

    def to_json(state = nil)
      {username: @username, groups: groups, valid_until: @valid_until, ip_address: @ip_address}.to_json(state)
    end

    private

    def add_user_to_pf
      OSConfig.find_aliases('User', @username).each do |group_aliase|
        PFCTL.add_ip_to_alias(group_aliase['name'], @ip_address)
      end
    end
    
    def add_groups_to_pf
      @groups.each do |group|
        OSConfig.find_aliases('Group', group).each do |group_aliase|
          PFCTL.add_ip_to_alias(group_aliase['name'], @ip_address)
        end
      end
    end

    def remove_user_from_pf(un)
      OSConfig.find_aliases('User', un).each do |user_aliases|
        PFCTL.del_ip_from_alias(user_aliases['name'], @ip_address)
      end
    end

    def remove_from_pf_groups(data)
      if (@groups.is_a? Array) and !@groups.empty?
        (@groups - data['groups']).each do |group|
          ::OSConfig.find_aliases('Group', group).each do |alias_obj|
            PFCTL::del_ip_from_alias(alias_obj['name'], @ip_address)
          end
        end
      end
    end
  end
  class << self
    def login(data)
      if $login_states[data['ip']]
        user = $login_states[data['ip']]
        user.update(data)
      else
        user = $login_states[data['ip']] = User.new data
      end
      user
    end

    def logout(session)
      $login_states[session['ip']].logout
      {status: 'logged out'}
    end
  end

  def self.whois(data)
    $login_states[data['ip']] || {error: "not found"}
  end
end

Thread.new do
  counter = 0
  loop do
    if counter == 0
      config = OSConfig::read_config_xml
      um_root = config.elements['opnsense/OPNsense/UserMapping']
      $shared_data.user_mappings = OSConfig::read_config_user_mappings(um_root)
      $shared_data.aliases = OSConfig::get_aliases(config)
    end
    now = Time.now
    $login_states.values.each do |session|
      session.logout if now > session.valid_until
    end
    sleep 20.0
  end
end


module Communication
  class << self
    SOCKET = '/var/run/usermapping'

    def run_server
      File.delete SOCKET if File.exist? SOCKET
      server = UNIXServer.new(SOCKET)
      loop do
        socket = server.accept
        Thread.new do
          begin
            handle_connection(socket)
          rescue
            socket.close
            puts $!, $!.backtrace
          end
        end
      end
    end

    def handle_connection(socket)
      data = socket.gets
      begin
        data = JSON.parse data
        response =
            case data['method']
            when 'list'
              $login_states.values.to_json
            when 'login'
              ::Authorization.login(data).to_json
            when 'logout'
              ::Authorization.logout(data)
            when 'whois'
              ::Authorization.whois(data).to_json
            when 'pry'
              binding.pry # start debugger
            when 'exit'
              socket.puts '{"error": "exiting"}'
              socket.close
              File.delete SOCKET if File.exist? SOCKET
              Kernel.exit! 0
            else
              {error: 'unknown command'}
            end
        socket.puts(response)
      rescue JSON::ParserError
        socket.puts '{"error": "invalid JSON data"}'
      end
      socket.close
    end
  end
end

Communication.run_server
