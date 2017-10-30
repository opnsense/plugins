{% if helpers.exists('OPNsense.Rspamd.general.enabled') and OPNsense.Rspamd.general.enabled == '1' and helpers.exists('OPNsense.Rspamd.av') %}
{% for host in OPNsense.Rspamd.av.whitelist.split(',') %}
{{ host }}
{% endfor %}
{% endif %}
