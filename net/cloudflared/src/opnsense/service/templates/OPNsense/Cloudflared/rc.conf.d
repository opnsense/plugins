{% if helpers.exists('OPNsense.Cloudflared.general.enabled') and OPNsense.Cloudflared.general.enabled == '1' %}
cloudflared_enable="YES"
cloudflared_conf="/usr/local/etc/cloudflared/config.yml"
{% if helpers.exists('OPNsense.Cloudflared.general.token') %}
{% set ns = namespace(env='TUNNEL_TOKEN=' + OPNsense.Cloudflared.general.token|trim) %}
{% if helpers.exists('OPNsense.Cloudflared.general.edge_ip_version') %}
{% set ns.env = ns.env + ' TUNNEL_EDGE_IP_VERSION=' + OPNsense.Cloudflared.general.edge_ip_version %}
{% endif %}
cloudflared_env="{{ ns.env }}"
{% endif %}
cloudflared_mode_options="run"
{% else %}
cloudflared_enable="NO"
{% endif %}
