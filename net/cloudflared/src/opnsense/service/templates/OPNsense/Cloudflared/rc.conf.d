{% if helpers.exists('OPNsense.Cloudflared.general.enabled') and OPNsense.Cloudflared.general.enabled == '1' %}
cloudflared_enable="YES"
cloudflared_conf="/usr/local/etc/cloudflared/config.yml"
{% if helpers.exists('OPNsense.Cloudflared.general.token') %}
cloudflared_env="TUNNEL_TOKEN={{ OPNsense.Cloudflared.general.token|trim }}{% if helpers.exists('OPNsense.Cloudflared.general.edge_ip_version') and OPNsense.Cloudflared.general.edge_ip_version != 'auto' %} TUNNEL_EDGE_IP_VERSION={{ OPNsense.Cloudflared.general.edge_ip_version | replace('ipv', '') }}{% endif %}"
{% endif %}
cloudflared_mode_options="run"
{% else %}
cloudflared_enable="NO"
{% endif %}
