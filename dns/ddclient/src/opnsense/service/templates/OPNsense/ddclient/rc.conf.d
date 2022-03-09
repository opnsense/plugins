{% if not helpers.empty('OPNsense.DynDNS.general.enabled') %}
ddclient_enable="YES"
{% else %}
ddclient_enable="NO"
{% endif %}
