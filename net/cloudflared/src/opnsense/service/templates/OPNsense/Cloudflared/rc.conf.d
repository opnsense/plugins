{% if not helpers.empty('OPNsense.Cloudflared.general.enabled') %}
cloudflared_enable="YES"
cloudflared_conf="/usr/local/etc/cloudflared/config.yml"
{% if not helpers.empty('OPNsense.Cloudflared.general.token') %}
cloudflared_env="TUNNEL_TOKEN={{ OPNsense.Cloudflared.general.token|trim }}"
{% endif %}
cloudflared_mode_options="run"
# log to syslog instead of the unrotated /var/log/cloudflared.log
cloudflared_flags="-S -T cloudflared"
logfile="/dev/null"
{% else %}
cloudflared_enable="NO"
{% endif %}
