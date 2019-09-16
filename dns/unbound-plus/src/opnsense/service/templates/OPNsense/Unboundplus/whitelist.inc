{%   if helpers.exists('OPNsense.unboundplus.dnsbl.enabled') and OPNsense.unboundplus.dnsbl.enabled == '1' %}
{%     if helpers.exists('OPNsense.unboundplus.dnsbl.type') and OPNsense.unboundplus.dnsbl.type != '' %}
{%       if helpers.exists('OPNsense.unboundplus.dnsbl.whitelists') and OPNsense.unboundplus.dnsbl.whitelists != '' %}
{{ OPNsense.unboundplus.dnsbl.whitelists|default("") }}
{%       endif %}
{%     endif %}
{%   endif %}
