{% if helpers.exists('OPNsense.HAProxy.general.enabled') and OPNsense.HAProxy.general.enabled|default("0") == "1" %}
haproxy_enable=YES
haproxy_opnsense_bootup_run="/usr/local/opnsense/scripts/OPNsense/HAProxy/setup.sh"
haproxy_pidfile="/var/run/haproxy.pid"
haproxy_config="/usr/local/etc/haproxy.conf"
# haproxy_flags=""
{% else %}
haproxy_enable=NO
{% endif %}
