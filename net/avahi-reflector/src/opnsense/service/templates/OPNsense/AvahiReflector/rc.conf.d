{% from 'OPNsense/Macros/interface.macro' import physical_interface %}
{# Same rule as avahidaemon.conf: never start without an allow-interfaces list. #}
{% set phys_names = [] %}
{% if OPNsense.AvahiReflector.enabled|default("0") == "1" %}
{%   for intf in (OPNsense.AvahiReflector.interfaces|default("")).split(",") %}
{%     set phys = physical_interface(intf.strip())|trim %}
{%     if phys %}
{%       do phys_names.append(phys) %}
{%     endif %}
{%   endfor %}
{% endif %}
{% if phys_names %}
avahi_daemon_enable="YES"
{% else %}
avahi_daemon_enable="NO"
{% endif %}
