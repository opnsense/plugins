About the OPNsense plugins
==========================

Brace yourselves, plugins are coming to 16.1.

The make targets for the root directory:

list:		print a list of all plugin directories

The make targets for any plugin directory:

manifest:	generate +MANIFEST file
install:	install to target directory
remove:		remove from target directory
collect:	gather updates from target directory
mount:		unionfs mount above target directory
umount:		unionfs umount from target directory
clean:		remove all changes and unknown files
