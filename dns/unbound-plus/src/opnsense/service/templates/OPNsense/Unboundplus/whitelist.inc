{% if helpers.exists('OPNsense.unboundplus.general.enabled') and OPNsense.unboundplus.general.enabled == '1' %}
{%   if helpers.exists('OPNsense.unboundplus.dnsbl.enabled') and OPNsense.unboundplus.dnsbl.enabled == '1' %}
{%     if helpers.exists('OPNsense.unboundplus.dnsbl.type') and OPNsense.unboundplus.dnsbl.type != '' %}
{%       if helpers.exists('OPNsense.unboundplus.dnsbl.whitelists') and OPNsense.unboundplus.dnsbl.whitelists != '' %}
{%         for whitelist in OPNsense.unboundplus.dnsbl.whitelists.split(',') %}
{{ whitelist }}
{%         endfor %}
{%       endif %}
{%     endif %}
{%   endif %}
{% endif %}
