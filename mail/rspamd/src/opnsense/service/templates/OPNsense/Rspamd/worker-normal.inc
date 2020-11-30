{% if helpers.exists('OPNsense.Rspamd.general.enabled') and OPNsense.Rspamd.general.enabled == '1' %}
bind_socket = "/var/run/rspamd/normal.sock mode=0666 owner=rspamd";
{% endif %}
