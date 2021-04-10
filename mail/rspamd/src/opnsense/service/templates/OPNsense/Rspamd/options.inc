# Please don't modify this file as your changes might be overwritten with
# the next update.
#

dns {
         nameserver = [{{ "'" + ("','".join(OPNsense.Rspamd.general.nameserver.split(','))) + "'" }}];
}
