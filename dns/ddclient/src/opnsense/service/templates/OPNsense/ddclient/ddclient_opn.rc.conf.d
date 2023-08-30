{% if not helpers.empty('OPNsense.DynDNS.general.enabled') and OPNsense.DynDNS.general.backend == 'opnsense' %}
ddclient_opn_enable="YES"
{% else %}
ddclient_opn_enable="NO"
{% endif %}
