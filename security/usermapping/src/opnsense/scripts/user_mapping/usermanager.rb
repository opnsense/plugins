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

# global for showing debug output if needed
$USERMAPPING_DEBUG = false

$LOGIN_STATES = {}
$SHARED_DATA = OpenStruct.new
module OSConfig
    class << self
        def read_config_xml
            #
            REXML::Document.new(File.new("/conf/config.xml"))
        end

        def read_config_user_mappings(um_root)
            um_nodes = um_root.each_child.select { |child| child.class == REXML::Element && child.name == 'user_mapping'}
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
    end
end

module Authorization
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
            if data['groups']&.is_a? Array
                @groups = data['groups'] # use the provided groups
            else
                @groups = []
            end
            @ip_address = data['ip']
            @username = data['username'] if data['username']
            if data['valid_until']
                @valid_until = data['valid_until']
            else
                @valid_until = Time.now + 60 # default: 60 sec
            end
        end

        def logout(ip)
          $LOGIN_STATES.delete ip
          # remove also from tables
        end
        def to_json
          {username: @username, groups: groups, valid_until: @valid_until, ip_address: @ip_address}.to_json
        end
    end
    class << self
        def login(data)
            if $LOGIN_STATES[data['ip']]
                user = $LOGIN_STATES[data['ip']]
                user.update(data)
            else
                user = $LOGIN_STATES[data['ip']] = User.new data
            end
          user
        end
        def logout(session)
            $LOGIN_STATES[session['ip']].logout
            {status: 'logged out'}
        end
    end

    def self.whois(data)
      $LOGIN_STATES[data['ip']]
    end
end

Thread.new do
  loop do
      config = OSConfig::read_config_xml
      um_root = config.elements['opnsense/OPNsense/UserMapping']
      alias_root = config.elements['opnsense/OPNsense/Firewall/Alias/aliases']
      $SHARED_DATA.user_mappings = OSConfig::read_config_user_mappings(um_root)
      $SHARED_DATA.aliases = OSConfig::get_aliases(config)
      sleep 120
  end
end


module Communication
    class << self
        SOCKET = 'sock'
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
                      puts $!
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
                    $LOGIN_STATES
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
            #binding.pry
            socket.close
        end
    end
end

Communication.run_server

#&.text&.to_i