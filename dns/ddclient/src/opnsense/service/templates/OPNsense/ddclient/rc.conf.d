{% if not helpers.empty('OPNsense.DynDNS.general.enabled') and OPNsense.DynDNS.general.backend == 'ddclient' %}
ddclient_enable="YES"
ddclient_setup="/usr/local/opnsense/scripts/ddclient/setup.sh"
ddclient_flags="-daemon {{OPNsense.DynDNS.general.daemon_delay|default('300')}}"
{% else %}
ddclient_enable="NO"
{% endif %}
