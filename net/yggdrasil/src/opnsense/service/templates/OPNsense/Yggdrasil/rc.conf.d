{% if not helpers.empty('OPNsense.Yggdrasil.general.enabled') %}
yggdrasil_enable="YES"
start_precmd="/usr/local/opnsense/scripts/OPNsense/Yggdrasil/install-config.sh"
{% else %}
yggdrasil_enable="NO"
{% endif %}
