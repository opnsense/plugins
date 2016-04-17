haproxy_enable="{% if helpers.exists('OPNsense.HAProxy.general.enabled') and OPNsense.HAProxy.general.enabled|default("0") == "1" %}YES{% else %}NO{% endif %}"
haproxy_pidfile="/var/run/haproxy.pid"
haproxy_config="/usr/local/etc/haproxy.conf"
# haproxy_flags=""
