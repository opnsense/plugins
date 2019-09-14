{% if helpers.exists('OPNsense.unbound.dnsbl.enabled') and OPNsense.unbound.dnsbl.enabled == '1' %}
{%   if helpers.exists('OPNsense.unbound.dnsbl.type') and OPNsense.unbound.dnsbl.type != '' %}
unbound_dnsbl="{{ OPNsense.unbound.dnsbl.type }}"
{%   endif %}
{% endif %}
