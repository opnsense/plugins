{% set networks = [] %}
{% for network in helpers.toList('OPNsense.Tinc.networks.network') %}
{%   if network.enabled == '1' %}
{%     do networks.append(network) %}
{%   endif %}
{% endfor %}
{% if networks|length > 0 %}
OPNtincd_enable=YES
{% else %}
OPNtincd_enable=NO
{% endif %}
