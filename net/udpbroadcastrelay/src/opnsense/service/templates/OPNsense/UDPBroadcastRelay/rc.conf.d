{% if helpers.exists('OPNsense.udpbroadcastrelays.udpbroadcastrelay') %}
{% from 'OPNsense/Macros/interface.macro' import physical_interface %}
osudpbroadcastrelay_enable="YES"
{% set Instances=[] %}
{%  for osudpbroadcastrelay in helpers.toList('OPNsense.udpbroadcastrelays.udpbroadcastrelay') %}
{%   if osudpbroadcastrelay.enabled|default('0') == '1' %}
{%    set Parameters=[] %}
{%    if osudpbroadcastrelay.InstanceID %}
{%     do Parameters.append("--id " ~ osudpbroadcastrelay.InstanceID) %}
{%    endif %}
{%    set osifnames = osudpbroadcastrelay.interfaces.split(',') %}
{%    set interface_list=[] %}
{%    for i in osifnames %}
{%    do interface_list.append(physical_interface(i)) %}
{%    do Parameters.append("--dev " ~ physical_interface(i)) %}
{%    endfor %}
{%    do Parameters.append("--port " ~ osudpbroadcastrelay.listenport) %}
{%    if osudpbroadcastrelay.multicastaddress %}
{%    set osmcastaddresses = osudpbroadcastrelay.multicastaddress.split(',') %}
{%    for mcastaddress in osmcastaddresses %}
{%     do Parameters.append("--multicast " ~ mcastaddress) %}
{%    endfor %}
{%    endif %}
{%    if osudpbroadcastrelay.sourceaddress %}
{%     do Parameters.append("-s " ~ osudpbroadcastrelay.sourceaddress) %}
{%    endif %}
{%    if osudpbroadcastrelay.msearch_dial|default('0') == '1' %}
{%     do Parameters.append("--msearch dial ") %}
{%    endif %}
{%    if osudpbroadcastrelay.msearch_proxy|default('0') == '1' %}
{%     do Parameters.append("--msearch proxy ") %}
{%    endif %}
{%    if osudpbroadcastrelay.RevertTTL|default('0') == '1' %}
{%     do Parameters.append("-t ") %}
{%    endif %}
{%     do Parameters.append("-f") %}
{%    set Instance=osudpbroadcastrelay.InstanceID %}
osudpbroadcastrelay_{{Instance}}="{% for Parameter in Parameters %} {{Parameter}}{% endfor %}"
{%     do Instances.append(Instance) %}
{%   endif %}
{%  endfor %}
osudpbroadcastrelay_instances="{% for Instance in Instances %} {{Instance}}{% endfor %}"
{% endif %}
