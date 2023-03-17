{% if helpers.exists('OPNsense.saltminion.general') and OPNsense.saltminion.general.Enabled|default("0") == "1" %}
salt_minion_enable="YES"
{% else %}
salt_minion_enable="NO"
{% endif %}
