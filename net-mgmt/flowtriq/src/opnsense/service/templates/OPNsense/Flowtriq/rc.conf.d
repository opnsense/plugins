{% if helpers.exists('OPNsense.Flowtriq.settings.enabled') and OPNsense.Flowtriq.settings.enabled|default("0") == "1" %}
{% from 'OPNsense/Macros/interface.macro' import physical_interface %}
{% set agent_host = OPNsense.Flowtriq.settings.agentHost|default("") %}
{% set agent_port = OPNsense.Flowtriq.settings.agentPort|default("2055") %}
{% set netflow_version = OPNsense.Flowtriq.settings.netflowVersion|default("9") %}
{% set tracking_level = OPNsense.Flowtriq.settings.trackingLevel|default("full") %}
{% set max_flows = OPNsense.Flowtriq.settings.maxFlows|default("8192") %}
{% if agent_host != "" %}
{%   if helpers.exists('OPNsense.Flowtriq.settings.interface') and OPNsense.Flowtriq.settings.interface != '' %}
{%     set iface = physical_interface(OPNsense.Flowtriq.settings.interface.split(',')[0]) %}
{%   else %}
{%     set iface = "" %}
{%   endif %}
{%   if iface != "" %}
softflowd_enable="YES"
softflowd_flags="-i {{ iface }} -n {{ agent_host }}:{{ agent_port }} -v {{ netflow_version }} -T {{ tracking_level }} -N {{ max_flows }}"
{%   endif %}
{% endif %}
{% else %}
softflowd_enable="NO"
{% endif %}
