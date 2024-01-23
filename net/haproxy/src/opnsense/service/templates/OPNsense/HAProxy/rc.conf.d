{% if helpers.exists('OPNsense.HAProxy.general.enabled') and OPNsense.HAProxy.general.enabled|default("0") == "1" %}
haproxy_enable=YES
haproxy_setup="/usr/local/opnsense/scripts/OPNsense/HAProxy/setup.sh"
haproxy_pidfile="/var/run/haproxy.pid"
haproxy_config="/usr/local/etc/haproxy.conf"
{% if helpers.exists('OPNsense.HAProxy.general.gracefulStop') and OPNsense.HAProxy.general.gracefulStop|default("0") == "1" %}
haproxy_hardstop=NO
{% else %}
haproxy_hardstop=YES
{% endif %}
{% if helpers.exists('OPNsense.HAProxy.general.seamlessReload') and OPNsense.HAProxy.general.seamlessReload|default("0") == "1" %}
haproxy_socket="/var/run/haproxy.socket"
haproxy_softreload=YES
{% else %}
haproxy_softreload=NO
{% endif %}
{% else %}
haproxy_enable=NO
{% endif %}
