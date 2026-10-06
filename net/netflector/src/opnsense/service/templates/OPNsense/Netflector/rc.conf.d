{% from 'OPNsense/Macros/interface.macro' import physical_interface %}
{# Armed only with an entry on: the daemon refuses to start with none, as in netflector_enabled(). #}
{% set armed = [] %}
{% if helpers.exists('OPNsense.Netflector.general.enabled') and OPNsense.Netflector.general.enabled == '1' %}
{%   if helpers.exists('OPNsense.Netflector.reflectors.reflector') %}
{%     for entry in helpers.toList('OPNsense.Netflector.reflectors.reflector') %}
{%       if entry.enabled|default('0') == '1' %}
{%         do armed.append(entry.name) %}
{%       endif %}
{%     endfor %}
{%   endif %}
{% endif %}
{% if armed %}
netflector_enable="YES"
{#   The rc script gates on vhid@interface: the same vhid can run on several interfaces. #}
{%   set group = [] %}
{%   if helpers.exists('OPNsense.Netflector.general.carp_depend_on') and OPNsense.Netflector.general.carp_depend_on != '' %}
{%     if helpers.exists('virtualip.vip') %}
{%       for vip in helpers.toList('virtualip.vip') %}
{%         if vip['@uuid'] == OPNsense.Netflector.general.carp_depend_on and vip.mode == 'carp' %}
{%           do group.append(vip) %}
{%           break %}
{%         endif %}
{%       endfor %}
{%     endif %}
{%     if group %}
netflector_carp_vhid="{{ group[0].vhid }}"
netflector_carp_interface="{{ physical_interface(group[0].interface) }}"
{%     else %}
{#     Rendering nothing would leave the gate inert; a vhid no group can have refuses instead. #}
# carp_depend_on names no CARP virtual IP; refusing to start rather than reflecting on both nodes.
netflector_carp_vhid="unknown"
{%     endif %}
{%   endif %}
{% else %}
netflector_enable="NO"
{% endif %}
