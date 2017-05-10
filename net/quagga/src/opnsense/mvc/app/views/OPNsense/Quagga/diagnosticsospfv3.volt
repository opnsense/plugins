{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script type="text/x-template" id="overviewtpl">
</script>

<script type="text/x-template" id="routestpl">
  <table>
    <thead>
      <tr>
        <td>{{ lang._('Flags 1') }}</td>
        <td>{{ lang._('Flags 2') }}</td>
        <td>{{ lang._('Network') }}</td>
        <td>{{ lang._('Gateway') }}</td>
        <td>{{ lang._('Interface') }}</td>
        <td>{{ lang._('Time') }}</td>
      </tr>
    </thead>
    <tbody>
      <% _.each(ospfv3_route, function (route) { %>
        <tr>
          <td><%= route['f1'] %></td>
          <td><%= route['f2'] %></td>
          <td><%= route['network'] %></td>
          <td><%= route['gateway'] %></td>
          <td><%= route['interface'] %></td>
          <td><%= route['time'] %></td>
        </tr>
      <% }) %>
    </tbody>
  </table>
</script>

<script type="text/x-template" id="databasetpl">
</script>

<script type="text/x-template" id="interfacetpl">
<% _.each(_.keys(ospf_interface), function(interfacename) { %>
  <% int = ospf_interface[interfacename] %>
  <h2><%= interfacename %></h2>
  <table>
    <tbody>
      <% _.each(_.keys(int), function(propertyname) { %>
        <tr>
          <td><%= translate(propertyname) %></td>
          <td>
            <% if (int[propertyname] === false || int[propertyname] === true)  { %>
              <%= checkmark(int[propertyname]) %>
            <% } else if (propertyname == 'intervals')  { %>
              {{ lang._('Hello Interval:') }} <%= int[propertyname]['hello'] %><br />
              {{ lang._('Dead Interval:') }} <%= int[propertyname]['dead'] %><br />
              {{ lang._('Wait Interval:') }} <%= int[propertyname]['wait'] %><br />
              {{ lang._('Retransmit Interval:') }} <%= int[propertyname]['retransmit'] %>
            <% } else { %>
              <%= int[propertyname] %>
            <% } %>
          </td>
        </tr>
      <% }); %>
    </tbody>
  </table>
<% }) %>
</script>
<script type="text/javascript" src="/ui/js/quagga/lodash.js"></script>
<script>

function translate(data)
{
  tr = []
  tr['count'] = '{{ lang._('Count') }}'
  tr['router'] = '{{ lang._('Router') }}'
  tr['network'] = '{{ lang._('Network') }}'
  tr['summary'] = '{{ lang._('Summary') }}'
  tr['ASBR summary'] = '{{ lang._('ASBR summary') }}'
  tr['NSSA'] = '{{ lang._('NSSA') }}'
  tr['directly attached'] = '{{ lang._('Directly Attached') }}'
  tr['Full/DR'] = '{{ lang._('Full (Designated Router)') }}'
  tr['enabled'] = '{{ lang._('Enabled') }}'
  tr['cost'] = '{{ lang._('Cost') }}'
  tr['priority'] = '{{ lang._('Priority') }}'
  tr['router_id'] = '{{ lang._('Router ID') }}'
  tr['network_type'] = '{{ lang._('Network Type') }}'
  tr['area'] = '{{ lang._('Area') }}'
  tr['transmit_delay'] = '{{ lang._('Transmit Delay') }}'
  tr['state'] = '{{ lang._('State') }}'
  tr['broadcast'] = '{{ lang._('Broadcast') }}'
  tr['mtu_mismatch_detection'] = '{{ lang._('MTU Mismatch Detection') }}'
  tr['address'] = '{{ lang._('Address') }}'
  tr['designated_router'] = '{{ lang._('Designated Router') }}'
  tr['designated_router_interface_address'] = '{{ lang._('Designated Router Interface Address') }}'
  tr['backup_designated_router'] = '{{ lang._('Backup Designated Router') }}'
  tr['multicast_group_memberships'] = '{{ lang._('Multicast Group Memberships') }}'
  tr['intervals'] = '{{ lang._('Intervals') }}'
  tr['hello_due_in'] = '{{ lang._('Hello Due In') }}'
  tr['neighbor_count'] = '{{ lang._('Neighbor Count') }}'
  tr['adjacent_neighbor_count'] = '{{ lang._('Adjacent Neighbor Count') }}'
  return _.has(tr,data) ? tr[data] : data
}

function checkmark(bin)
{
  return "<i class=\"fa " + (bin ? "fa-check-square" : "fa-square") + " text-muted\"></i>";
}

$(document).ready(function() {
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status'])
  });

  ajaxCall(url="/api/quagga/diagnostics/ospfv3overview", sendData={}, callback=function(data,status) {
    content = _.template($('#overviewtpl').html())(data['response'])
    $('#overview').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfv3database", sendData={}, callback=function(data,status) {
    content = _.template($('#databasetpl').html())(data['response'])
    $('#database').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfv3route", sendData={}, callback=function(data,status) {
    content = _.template($('#routestpl').html())(data['response'])
    $('#routing').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfv3neighbor", sendData={}, callback=function(data,status) {
    content = _.template($('#neighbortpl').html())(data['response'])
    $('#neighbor').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfv3interface", sendData={}, callback=function(data,status) {
    //content = _.template($('#interfacetpl').html())(data['response'])
    //$('#interface').html(content)
  });


});
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#overview">{{ lang._('Overview') }}</a></li>
    <li><a data-toggle="tab" href="#routing">{{ lang._('Routing Table') }}</a></li>
    <li><a data-toggle="tab" href="#database">{{ lang._('Database') }}</a></li>
    <li><a data-toggle="tab" href="#neighbor">{{ lang._('Neighbor') }}</a></li>
    <li><a data-toggle="tab" href="#interface">{{ lang._('Interface') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="overview" class="tab-pane fade in active">
    </div>
    <div id="routing" class="tab-pane fade in">
    </div>
    <div id="database" class="tab-pane fade in">
    </div>
    <div id="neighbor" class="tab-pane fade in">
    </div>
    <div id="interface" class="tab-pane fade in">
    </div>
</div>
