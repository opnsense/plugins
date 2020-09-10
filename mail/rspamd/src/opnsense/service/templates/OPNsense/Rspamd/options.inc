# Please don't modify this file as your changes might be overwritten with
# the next update.
#

{% if helpers.exists('OPNsense.Rspamd.general.enabled') and OPNsense.Rspamd.general.enabled == '1' %}

dns {
         nameserver = [{{ """ + ("','".join(OPNsense.Rspamd.general.nameserver.split(','))) + """ }}];
}

{% endif %}
