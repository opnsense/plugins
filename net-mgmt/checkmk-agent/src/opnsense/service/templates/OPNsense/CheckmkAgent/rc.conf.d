{% if not helpers.empty('OPNsense.CheckmkAgent.enabled') %}
checkmkagent_enable="YES"
checkmkagent_setup="/usr/local/opnsense/scripts/checkmkagent/setup.sh"
checkmkagent_address="{{ OPNsense.CheckmkAgent.address|default('127.0.0.1') }}"
checkmkagent_port="{{ OPNsense.CheckmkAgent.port|default('6556') }}"
{% if not helpers.empty('OPNsense.CheckmkAgent.encryption') %}
checkmkagent_encryption="YES"
{% endif %}
{% else %}
checkmkagent_enable="NO"
{% endif %}
