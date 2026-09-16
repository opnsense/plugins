{% if OPNsense.AvahiReflector.enabled|default("0") == "1" %}
avahi_daemon_enable="YES"
{% else %}
avahi_daemon_enable="NO"
{% endif %}
