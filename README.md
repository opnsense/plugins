About the OPNsense plugins
==========================

The plugins collection offers users and developers a way to quickly
build additions for OPNsense that can be optionally installed.  As
soon as they are upstreamed they will become available to everyone
through the firmware GUI pages.

Plugins can do the following:

* Modify the menu, access control lists and look and feel (themes)
* Add additional server software and their respective GUI pages
* Create new authentication methods to be used within other subsystems
* Provide other types of devices and interfaces to the firewall
* Enhance the backend services with additional work tasks

Now we need your help to enrich the plugins.  Feel free to contact us
at project AT opnsense DOT org or open GitHub issue to get in touch.


Stay safe,
Your OPNsense team

A list of currently available plugins
=====================================

devel/helloworld -- a sample plugin to show how development works
net/haproxy -- flexible HAProxy plugin
net/l2tp -- L2TP server support
net/pppoe -- PPPoE servers support
net/pptp -- PPTP server support
sysutils/smart -- S.M.A.R.T. disk utilies
sysutils/vmware -- Guest additions for VMware
sysutils/xen -- Guest additions for Xen

A brief description of how to use the plugins repository
========================================================

The workflow of the plugins repository is quite similar to the
core repository, although the plugins have one source directory
per plugin, while the core can be thought of a lone plugin.

Commits for individual plugins should therefore be split into
individual chunks for each src/ directory so that they can be
reviewed separately and also be applied remotely.

When an OPNsense release is built, the plugins are automatically
added to the final package repository.

The most useful Makefile targets and their purpose is described
below.

The make targets for the root directory:

* lint:		run syntax checks on all available plugins
* list:		print a list of all plugin directories

The make targets for any plugin directory:

* package:	creates a package from directory
* install:	install to target directory
* remove:	remove from target directory
* collect:	gather updates from target directory
* clean:	remove all changes and unknown files
