{% if helpers.exists('OPNsense.openconnect.general.vpncscript') and OPNsense.openconnect.general.vpncscript|default("") != "" %}
{{ OPNsense.openconnect.general.vpncscript }}
{% else %}
{}
{% endif %}
