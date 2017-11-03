{% if helpers.exists('OPNsense.ProxySSO.EnableSSO') and OPNsense.ProxySSO.EnableSSO|default("0") == "1" %}
squid_krb5_ktname="/usr/local/etc/squid/squid.keytab"
{% endif %}
