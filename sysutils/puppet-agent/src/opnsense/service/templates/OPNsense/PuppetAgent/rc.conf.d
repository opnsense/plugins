{% if helpers.exists('OPNsense.puppetagent.general') and OPNsense.puppetagent.general.Enabled|default("0") == "1" %}
puppet_enable="Yes"
{% else %}
puppet_enable="No"
{% endif %}
