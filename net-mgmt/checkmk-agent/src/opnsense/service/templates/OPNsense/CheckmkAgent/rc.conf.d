{% if not helpers.empty('OPNsense.CheckmkAgent.enabled') %}
checkmkagent_enable="YES"
{% else %}
checkmkagent_enable="NO"
{% endif %}
