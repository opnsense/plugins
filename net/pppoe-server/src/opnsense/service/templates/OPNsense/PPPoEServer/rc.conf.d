{% if helpers.exists('OPNsense.pppoeserver.general.enabled') and OPNsense.pppoeserver.general.enabled == '1' %}
pppoe_server_enable="YES"
{% else %}
pppoe_server_enable="NO"
{% endif %}
