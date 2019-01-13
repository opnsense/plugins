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

# global for showing debug output if needed
$USERMAPPING_DEBUG = false

def read_config_xml
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

config = read_config_xml
um_root = config.elements['opnsense/OPNsense/UserMapping']
alias_root = config.elements['opnsense/OPNsense/Firewall/Alias/aliases']
user_mappings = read_config_user_mappings(um_root)
aliases = get_aliases(config)

binding.pry
#&.text&.to_i