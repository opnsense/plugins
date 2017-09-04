=begin
    Copyright 2017 Fabian Franz
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

CATEGORIES = %w{devel dns net net-mgmt sysutils security www}
SRCDIR = 'src'
PHP_COMMENT_TAGS = "/*\n", "\n*/\n"
VOLT_COMMENT_TAGS = "{#\n", "\n#}\n"

BSD_LICENSE = <<LICENSE
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
LICENSE

def create_license_header(tags, copyright_holder, year=Time.now.year.to_s)
  [
    tags[0],
    "    Copyright (C) " + year +" " + copyright_holder,
    BSD_LICENSE,
    tags[1]
  ].join("\n")
end

require 'fileutils'

def question(q, match = nil)
  loop do
    puts q
    data = STDIN.gets.strip
    return data if (!match) || (data =~ match)
  end
end






# tasks


namespace :plugin do
  desc 'create a new plugin'
  task :new do
    plugin_name = question("Enter the name of the new plugin:", /^[a-z]+?[a-z0-9_-]*$/)
    namespace_name = question("Enter the name of the namespace of the new plugin (name in the URL etc. - Must start with an uppercase character):", /^[A-Z]+?[a-z]*$/)
    category = question("Type a category. Valid categories are:\n#{CATEGORIES.join(', ')}",
                        /^#{ CATEGORIES.map {|name| Regexp.escape name }.join('|')}$/
                       )
    author = question("Enter your Name:", /.+/)
    email = question("Please type your contact email:", /[a-z0-9.-_]+@[a-z0-9.-_]+.[a-z]{2,}/)
    decriptive_name = question("Please enter a short description (single line which will be shown in the GUI):",/.+/)
    dependencies = question("Enter package dependencies:")
    
    plugin_base = File.join(category,plugin_name)
    source_dir = File.join(plugin_base,SRCDIR)
    app_dir = File.join(source_dir, 'opnsense', 'mvc', 'app')
    controller_dir = File.join(app_dir, 'controllers','OPNsense', namespace_name)
    model_dir = File.join(app_dir, 'models','OPNsense', namespace_name)
    view_dir = File.join(app_dir, 'views','OPNsense', namespace_name)
    controller_api_dir = File.join(controller_dir, 'Api')
    controller_form_dir = File.join(controller_dir, 'forms')
    acl_dir = File.join(model_dir, 'ACL')
    menu_dir = File.join(model_dir, 'Menu')
    FileUtils.mkdir_p(plugin_base)
    FileUtils.mkdir_p(source_dir)
    FileUtils.mkdir_p(controller_dir)
    FileUtils.mkdir_p(controller_form_dir)
    FileUtils.mkdir_p(controller_api_dir)
    FileUtils.mkdir_p(model_dir)
    FileUtils.mkdir_p(view_dir)
    FileUtils.mkdir_p(acl_dir)
    FileUtils.mkdir_p(menu_dir)
    
    # create makefile
    File.open(File.join(plugin_base, 'Makefile'), 'wb') do |f|
      f.puts('PLUGIN_NAME='.ljust(22) + plugin_name)
      f.puts('PLUGIN_VERSION='.ljust(22) + '0.1')
      f.puts('PLUGIN_COMMENT='.ljust(22) + decriptive_name)
      f.puts('PLUGIN_DEPENDS='.ljust(22) + dependencies) if dependencies.length > 0
      f.puts('PLUGIN_MAINTAINER='.ljust(22) + email)
      f.puts('')
      f.puts('.include "../../Mk/plugins.mk"')
    end
    
    # write plugin ACL
    File.open(File.join(acl_dir, 'ACL.xml'), 'wb') do |acl|
      acl.puts <<~ACL
      <acl>
        <page-#{namespace_name}>
          <name>#{plugin_name}</name>
          <patterns>
            <pattern>ui/#{namespace_name.downcase}/*</pattern>
            <pattern>api/#{namespace_name.downcase}/*</pattern>
          </patterns>
        </page-#{namespace_name}>
      </acl>
      ACL
    end # write acl
    
    # write plugin menu
    # note the bath icon is used to make it embarrassing to a user not to change the default icon ;)
    File.open(File.join(menu_dir, 'Menu.xml'), 'wb') do |f|
      f.puts <<~MENU
      <menu>
        <Services>
          <#{plugin_name} VisibleName="#{plugin_name}" cssClass="fa fa-bath fa-fw" url="/ui/#{namespace_name.downcase}/" />
        </Services>
      </menu>
      MENU
    end # end write menu
    
    # write a index controller
    File.open(File.join(controller_dir,'IndexController.php'), 'wb') do |f|
      f.puts <<~DOCINDEXCONTROLLER
      <?php
      #{create_license_header(PHP_COMMENT_TAGS, author)}
      
      namespace OPNsense/#{namespace_name};

      /**
      * Class IndexController
      * @package OPNsense/#{namespace_name}
      */
      class IndexController extends \\OPNsense\\Base\\IndexController
      {
          public function indexAction()
          {
              $this->view->title = gettext("#{plugin_name}");
              $this->view->pick('OPNsense/#{namespace_name}/index');
          }
      }
      DOCINDEXCONTROLLER
    end
    File.open(File.join(view_dir,'index.volt'), 'wb') do |f|
      f.puts <<~INDEXVIEW
      #{create_license_header(VOLT_COMMENT_TAGS, author)}
      
      This plugin has been created but no content exists.
      INDEXVIEW
    end
  end
end
