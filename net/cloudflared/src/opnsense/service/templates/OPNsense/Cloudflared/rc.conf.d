{% if helpers.exists('OPNsense.Cloudflared.general.enabled') and OPNsense.Cloudflared.general.enabled == '1' %}
cloudflared_enable="YES"
cloudflared_conf="/usr/local/etc/cloudflared/config.yml"
{% if helpers.exists('OPNsense.Cloudflared.general.token') %}
cloudflared_env="TUNNEL_TOKEN={{ OPNsense.Cloudflared.general.token|trim }}"
{% endif %}
cloudflared_mode_options="run"
{% else %}
cloudflared_enable="NO"
{% endif %}
