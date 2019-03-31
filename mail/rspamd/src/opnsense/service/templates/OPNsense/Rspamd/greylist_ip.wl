{% if helpers.exists('OPNsense.Rspamd.general.enabled') and OPNsense.Rspamd.general.enabled == '1' and helpers.exists('OPNsense.Rspamd.graylist.whitelist_ip') and OPNsense.Rspamd.graylist.whitelist_ip != '' %}
{%   for host in OPNsense.Rspamd.graylist.whitelist_ip.split(',') %}
{{ host }}
{%   endfor %}
{% endif %}
