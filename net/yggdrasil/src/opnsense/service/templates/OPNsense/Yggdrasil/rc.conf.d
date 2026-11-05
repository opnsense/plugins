{% if not helpers.empty('OPNsense.Yggdrasil.general.enabled') %}
yggdrasil_enable="YES"
{% else %}
yggdrasil_enable="NO"
{% endif %}
