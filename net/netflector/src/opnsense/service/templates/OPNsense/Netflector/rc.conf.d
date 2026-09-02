{% from 'OPNsense/Macros/interface.macro' import physical_interface %}
{# The daemon refuses to start with no reflector to run, so "enabled" must mean the same thing here as
   in netflector_enabled(): the service is on AND at least one entry is on. Keying only on the global
   switch would arm a service whose generated configuration has no [reflectors.*] table at all. #}
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
{% else %}
netflector_enable="NO"
{% endif %}
{# The rc script gates on a vhid, not on the model's VIP uuid. The interface goes with it because the
   same vhid can run on several interfaces as unrelated groups. #}
{% set group = [] %}
{% if helpers.exists('OPNsense.Netflector.general.carp_depend_on') and OPNsense.Netflector.general.carp_depend_on != '' %}
{%   if helpers.exists('virtualip.vip') %}
{%     for vip in helpers.toList('virtualip.vip') %}
{%       if vip['@uuid'] == OPNsense.Netflector.general.carp_depend_on and vip.mode == 'carp' %}
{%         do group.append(vip) %}
{%         break %}
{%       endif %}
{%     endfor %}
{%   endif %}
{%   if group %}
netflector_carp_vhid="{{ group[0].vhid }}"
netflector_carp_interface="{{ physical_interface(group[0].interface) }}"
{%   else %}
{#   Rendering nothing would leave the gate inert and reflect anyway. A vhid no group can ever be
     named refuses instead, and reads as the sentinel it is in the rc script's warning. #}
# carp_depend_on names no CARP virtual IP; refusing to start rather than reflecting on both nodes.
netflector_carp_vhid="unknown"
{%   endif %}
{% endif %}
