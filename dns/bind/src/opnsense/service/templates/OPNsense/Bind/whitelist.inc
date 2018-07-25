{% if helpers.exists('OPNsense.bind.general.enabled') and OPNsense.bind.general.enabled == '1' %}
{%   if helpers.exists('OPNsense.bind.dnsbl.enabled') and OPNsense.bind.dnsbl.enabled == '1' %}
{%     if helpers.exists('OPNsense.bind.dnsbl.type') and OPNsense.bind.dnsbl.type != '' %}
{%       for whitelist in helpers.toList('OPNsense.bind.dnsbl.whitelists.split(',')') %}
{{ whitelist }} 	IN	CNAME	rpz-passthru.
*.{{ whitelist }} 	IN	CNAME	rpz-passthru.
{%       endfor %}
{%     endif %}
{%   endif %}
{% endif %}
