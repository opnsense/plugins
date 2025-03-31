{% if helpers.exists('OPNsense.turnserver.settings.Enabled') and OPNsense.turnserver.settings.Enabled|default("0") == "1" %}
turnserver_enable=YES
{% else %}
turnserver_enable=NO
{% endif %}
