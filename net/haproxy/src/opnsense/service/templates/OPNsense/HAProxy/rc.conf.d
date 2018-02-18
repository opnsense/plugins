{% if helpers.exists('OPNsense.HAProxy.general.enabled') and OPNsense.HAProxy.general.enabled|default("0") == "1" %}
haproxy_enable=YES
haproxy_var_script="/usr/local/opnsense/scripts/OPNsense/HAProxy/setup.sh"
haproxy_pidfile="/var/run/haproxy.pid"
haproxy_config="/usr/local/etc/haproxy.conf"
{% if helpers.exists('OPNsense.HAProxy.general.gracefulStop') and OPNsense.HAProxy.general.gracefulStop|default("0") == "1" %}
haproxy_hardstop=NO
{% else %}
haproxy_hardstop=YES
{% endif %}
{% else %}
haproxy_enable=NO
{% endif %}
