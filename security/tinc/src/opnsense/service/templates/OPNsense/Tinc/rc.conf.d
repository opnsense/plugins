{% set networks = [] %}
{% for network in helpers.toList('OPNsense.Tinc.networks.network') %}
{%   if network.enabled == '1' %}
{%     do networks.append(network) %}
{%   endif %}
{% endfor %}
{% if networks|length > 0 %}
ostincd_enable=YES
{% else %}
ostincd_enable=NO
{% endif %}
