{% if  OPNsense.StepCA.CA.Enabled|default("0") != "0" %}
step_ca_enable="YES"
{% else %}
step_ca_enable="NO"
{% endif %}
{# Yubikey requires pcscd enabled. #}
{% set keySourceR = OPNsense.StepCA.Initialize.root.Source|default("trust") %}
{% set keySourceI = OPNsense.StepCA.Initialize.intermediate.Source|default("trust") %}
{% if keySourceR.startswith("yubikey") or keySourceI.startswith("yubikey") %}
pcscd_enable="YES"
{% else %}
pcscd_enable="NO"
{% endif %}
