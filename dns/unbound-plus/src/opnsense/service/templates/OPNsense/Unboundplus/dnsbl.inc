{% if helpers.exists('OPNsense.unboundplus.general.enabled') and OPNsense.unboundplus.general.enabled == '1' %}
{%   if helpers.exists('OPNsense.unboundplus.general.type') and OPNsense.unboundplus.general.type != '' %}
unbound_dnsbl="{{ OPNsense.unboundplus.general.type }}"
{%   endif %}
{% endif %}