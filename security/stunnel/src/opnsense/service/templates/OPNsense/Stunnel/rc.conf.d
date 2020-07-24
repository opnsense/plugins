{% if not helpers.empty('OPNsense.Stunnel.general.enabled') %}
stunnel_enable="YES"
stunnel_pidfile="/var/run/stunnel/stunnel.pid"

mkdir -p /var/run/stunnel/certs
mkdir -p /var/run/stunnel/logs
chown -R stunnel:stunnel /var/run/stunnel
chmod -R 700 /var/run/stunnel

/usr/local/opnsense/scripts/stunnel/generate_certs.php > /dev/null 2>&1

{% else %}
stunnel_enable="NO"
{% endif %}
