{% if helpers.exists('OPNsense.Rspamd.general.enabled') and OPNsense.Rspamd.general.enabled == '1' and helpers.exists('OPNsense.Rspamd.phishing.exclusion') and OPNsense.Rspamd.phishing.exclusion != '' %}
{%   for sender in OPNsense.Rspamd.phishing.exclusion.split(',') %}
{{ sender }}
{%   endfor %}
{% endif %}
