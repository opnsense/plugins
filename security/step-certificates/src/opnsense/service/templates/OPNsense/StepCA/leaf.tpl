{%- for prov in helpers.toList('OPNsense.StepCA.CA.provisioners.provisioner') %}
{%-     if prov.Enabled == "1" %}
{%-         if prov.Provisioner == "acme" %}
{%              if TARGET_FILTERS['OPNsense.StepCA.CA.provisioners.provisioner.' ~ loop.index0] or TARGET_FILTERS['OPNsense.StepCA.CA.provisioners.provisioner'] %}
{
    {{prov.CreateTemplate}}
}
{%-             endif -%}
{%-         endif -%}
{%-     endif -%}
{%- endfor %}