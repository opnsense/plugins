{% if helpers.exists('OPNsense.ZabbixAgent.settings.main.enabled') and OPNsense.ZabbixAgent.settings.main.enabled|default("0") == "1" %}
zabbix_agentd_enable=YES
zabbix_agentd_opnsense_bootup_run="/usr/local/opnsense/scripts/OPNsense/ZabbixAgent/setup.sh"
zabbix_agentd_var_script="/usr/local/opnsense/scripts/OPNsense/ZabbixAgent/setup.sh"
{% else %}
zabbix_agentd_enable=NO
{% endif %}
zabbix_agentd_config=/usr/local/etc/zabbix_agentd.conf
