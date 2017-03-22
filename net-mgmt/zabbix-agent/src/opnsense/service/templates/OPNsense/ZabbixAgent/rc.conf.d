{% if helpers.exists('OPNsense.ZabbixAgent.settings.main.enabled') and OPNsense.ZabbixAgent.settings.main.enabled|default("0") == "1" %}
zabbix_agentd_enable=YES
{% else %}
zabbix_agentd_enable=NO
{% endif %}
zabbix_agentd_config=/usr/local/etc/zabbix_agentd.conf
