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

<script type="text/x-template" id="databasetpl">
</script>

<script type="text/x-template" id="routestpl">
  <h2>{{ lang._('Network Routing Table') }}</h2>
  <table>
    <thead>
      <tr>
        <th>{{ lang._('Type') }}</th>
        <th>{{ lang._('Network') }}</th>
        <th>{{ lang._('Cost') }}</th>
        <th>{{ lang._('Area') }}</th>
        <th>{{ lang._('Via') }}</th>
        <th>{{ lang._('Via interface') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.each(ospf_route['OSPF network routing table'], function(entry) { %>
        <tr>
          <td><%= entry["type"] %></td>
          <td><%= entry["network"] %></td>
          <td><%= entry["cost"] %></td>
          <td><%= entry["area"] %></td>
          <td><%= translate(entry["via"]) %></td>
          <td><%= entry["via_interface"] %></td>
        </tr>
      <% }); %>
    </tbody>
  </table>
  <h2>{{ lang._('Router Routing Table') }}</h2>
  <table>
    <thead>
      <tr>
        <th>{{ lang._('Type') }}</th>
        <th>{{ lang._('Network') }}</th>
        <th>{{ lang._('Cost') }}</th>
        <th>{{ lang._('Area') }}</th>
        <th>{{ lang._('ASBR') }}</th>
        <th>{{ lang._('Via') }}</th>
        <th>{{ lang._('Via interface') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.each(ospf_route['OSPF router routing table'], function(entry) { %>
        <tr>
          <td><%= entry["type"] %></td>
          <td><%= entry["network"] %></td>
          <td><%= entry["cost"] %></td>
          <td><%= entry["area"] %></td>
          <td><%= checkmark(entry["asbr"]) %></td>
          <td><%= translate(entry["via"]) %></td>
          <td><%= entry["via_interface"] %></td>
        </tr>
      <% }); %>
    </tbody>
  </table>
  <h2>{{ lang._('External Routing Table') }}</h2>
  <table>
    <thead>
      <tr>
        <th>{{ lang._('Type') }}</th>
        <th>{{ lang._('Network') }}</th>
        <th>{{ lang._('Cost') }}</th>
        <th>{{ lang._('Area') }}</th>
        <th>{{ lang._('Tag') }}</th>
        <th>{{ lang._('Via') }}</th>
        <th>{{ lang._('Via interface') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.each(ospf_route['OSPF external routing table'], function(entry) { %>
        <tr>
          <td><%= entry["type"] %></td>
          <td><%= entry["network"] %></td>
          <td><%= entry["cost"] %></td>
          <td><%= entry["area"] %></td>
          <td><%= entry["tag"] %></td>
          <td><%= translate(entry["via"]) %></td>
          <td><%= entry["via_interface"] %></td>
        </tr>
      <% }); %>
    </tbody>
  </table>
</script>

<script type="text/x-template" id="neighbortpl">
  <table>
    <thead>
      <tr>
        <th>{{ lang._('Neighbor ID') }}</th>
        <th>{{ lang._('Priority') }}</th>
        <th>{{ lang._('State') }}</th>
        <th>{{ lang._('Dead Time') }}</th>
        <th>{{ lang._('Address') }}</th>
        <th>{{ lang._('Interface') }}</th>
        <th>RXmtL</th>
        <th>RqstL</th>
        <th>DBsmL</th>
      </tr>
    </thead>
    <tbody>
      <% _.each(ospf_neighbors, function(entry) { %>
        <tr>
          <td><%= entry["Neighbor ID"] %></td>
          <td><%= entry["Pri"] %></td>
          <td><%= translate(entry["State"]) %></td>
          <td><%= entry["Dead Time"] %></td>
          <td><%= entry["Address"] %></td>
          <td><%= entry["Interface"] %></td>
          <td><%= entry["RXmtL"] %></td>
          <td><%= entry["RqstL"] %></td>
          <td><%= entry["DBsmL"] %></td>
        </tr>
      <% }); %>
    </tbody>
  </table>
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
    content = _.template($('#interfacetpl').html())(data['response'])
    $('#interface').html(content)
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

