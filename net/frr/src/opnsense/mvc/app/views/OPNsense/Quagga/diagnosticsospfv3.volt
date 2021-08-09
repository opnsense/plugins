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
<h2>{{ lang._('General') }}</h2>
<table class="table table-striped">
  <tbody>
    <tr>
      <td>{{ lang._('Router ID') }}</td>
      <td><%= ospfv3_overview['router_id'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Routing Process') }}</td>
      <td><%= ospfv3_overview['routing_process'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Running Time') }}</td>
      <td><%= ospfv3_overview['running_time'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Initial SPF scheduling delay') }}</td>
      <td><%= ospfv3_overview['intital_spf_scheduling_delay'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Hold Time') }}</td>
      <td>
        {{ lang._('Minimum Hold Time') }} <%= ospfv3_overview['hold_time']['min'] %><br/>
        {{ lang._('Maximum Hold Time:') }} <%= ospfv3_overview['hold_time']['max'] %>
      </td>
    </tr>
    <tr>
      <td>{{ lang._('SPF timer') }}</td>
      <td><%= ospfv3_overview['spf_timer'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Number Of Scoped AS') }}</td>
      <td><%= ospfv3_overview['number_as_scoped'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Number Of Areas') }}</td>
      <td><%= ospfv3_overview['number_of_areas'] %></td>
    </tr>
  </tbody>
</table>
<h2>{{ lang._('Areas') }}</h2>
<% if (ospfv3_overview['areas']) { %>
  <% areas = ospfv3_overview['areas'] %>
  <% _.each(_.keys(areas), function(areaname) { %>
    <% area = areas[areaname] %>
    <h3><%= areaname %></h3>
    <table class="table table-striped">
      <tbody>
        <tr>
          <td>{{ lang._('Number Of LSAs') }}</td>
          <td><%= area['number_lsas'] %></td>
        </tr>
        <tr>
          <td>{{ lang._('Interfaces') }}</td>
          <td><%= _.join(area['interfaces'], ", ") %></td>
        </tr>
      </tbody>
    </table>
  <% }) %>
<% } %>
</script>

<script type="text/x-template" id="routestpl">
  <table>
    <thead>
      <tr>
        <th data-column-id="flags1" data-type="string">{{ lang._('Flags 1') }}</th>
        <th data-column-id="flags2" data-type="string">{{ lang._('Flags 2') }}</th>
        <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
        <th data-column-id="gateway" data-type="string">{{ lang._('Gateway') }}</th>
        <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
        <th data-column-id="time" data-type="string">{{ lang._('Time') }}</th>
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
  <% if(ospfv3_database['scoped_link_db']) { %>
    <h2>{{ lang._('Scoped Link Database') }}</h2>
    <% _.each(_.keys(ospfv3_database['scoped_link_db']), function(areaname) { %>
      <% area = ospfv3_database['scoped_link_db'][areaname] %>
      <h3><% areaname %></h3>
      <table>
        <thead>
          <tr>
            <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
            <th data-column-id="lsid" data-type="string">{{ lang._('LS ID') }}</th>
            <th data-column-id="advrouter" data-type="string">{{ lang._('Advertising Router') }}</th>
            <th data-column-id="age" data-type="numeric">{{ lang._('Age') }}</th>
            <th data-column-id="seqnr" data-type="string">{{ lang._('Sequence Number') }}</th>
            <th data-column-id="payload" data-type="string">{{ lang._('Payload') }}</th>
          </tr>
        </thead>
        <tbody>
          <% _.each(area, function(entry) { %>
            <tr>
              <td><%= entry['Type'] %></td>
              <td><%= entry['LSId'] %></td>
              <td><%= entry['AdvRouter'] %></td>
              <td><%= entry['Age'] %></td>
              <td><%= entry['SeqNum'] %></td>
              <td><%= entry['Payload'] %></td>
            </tr>
          <% }) %>
        </tbody>
      </table>
    <% }) %>
  <% } %>
  <% if(ospfv3_database['if_scoped_link_state']) { %>
    <h2>{{ lang._('Interface Scoped Link Database') }}</h2>
    <% _.each(_.keys(ospfv3_database['if_scoped_link_state']), function(intfname) { %>
      <% intf = ospfv3_database['if_scoped_link_state'][intfname] %>
      <% _.each(_.keys(intf), function(areaname) { %>
        <% area = intf[areaname] %>
        <h3><%= intfname %> / <%= areaname %></h3>
        <table>
          <thead>
            <tr>
              <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
              <th data-column-id="lsid" data-type="string">{{ lang._('LS ID') }}</th>
              <th data-column-id="advrouter" data-type="string">{{ lang._('Advertising Router') }}</th>
              <th data-column-id="age" data-type="numeric">{{ lang._('Age') }}</th>
              <th data-column-id="seqnr" data-type="string">{{ lang._('Sequence Number') }}</th>
              <th data-column-id="payload" data-type="string">{{ lang._('Payload') }}</th>
            </tr>
          </thead>
          <tbody>
            <% _.each(area, function(entry) { %>
              <tr>
                <td><%= entry['Type'] %></td>
                <td><%= entry['LSId'] %></td>
                <td><%= entry['AdvRouter'] %></td>
                <td><%= entry['Age'] %></td>
                <td><%= entry['SeqNum'] %></td>
                <td><%= entry['Payload'] %></td>
              </tr>
            <% }) %>
          </tbody>
        </table>
      <% }) %>
    <% }) %>
  <% } %>
  <% if (ospfv3_database['as_scoped']) { %>
    <h2>{{ lang._('AS Scoped') }}</h2>
    <table>
      <thead>
        <tr>
          <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
          <th data-column-id="lsid" data-type="string">{{ lang._('LS ID') }}</th>
          <th data-column-id="advrouter" data-type="string">{{ lang._('Advertising Router') }}</th>
          <th data-column-id="age" data-type="numeric">{{ lang._('Age') }}</th>
          <th data-column-id="seqnr" data-type="string">{{ lang._('Sequence Number') }}</th>
          <th data-column-id="payload" data-type="string">{{ lang._('Payload') }}</th>
        </tr>
      </thead>
      <tbody>
        <% _.each(ospfv3_database['as_scoped'], function(entry) { %>
          <tr>
            <td><%= entry['Type'] %></td>
            <td><%= entry['LSId'] %></td>
            <td><%= entry['AdvRouter'] %></td>
            <td><%= entry['Age'] %></td>
            <td><%= entry['SeqNum'] %></td>
            <td><%= entry['Payload'] %></td>
          </tr>
        <% }) %>
      </tbody>
    </table>
  <% } %>
</script>

<script type="text/x-template" id="interfacetpl">
<% _.each(_.keys(ospfv3_interface), function(interfacename) { %>
  <% int = ospfv3_interface[interfacename] %>
  <h2><%= interfacename %></h2>
  <table class="table table-striped">
    <tbody>
      <% _.each(_.keys(int), function(propertyname) { %>
        <% if (propertyname != 'pending_lsas' ) { %>
          <tr>
            <td><%= translate(propertyname) %></td>
            <td>
              <% if (int[propertyname] === false || int[propertyname] === true)  { %>
                <%= checkmark(int[propertyname]) %>
              <% } else if (propertyname == 'timers')  { %>
                {{ lang._('Hello Timer:') }} <%= int[propertyname]['hello'] %><br />
                {{ lang._('Dead Timer:') }} <%= int[propertyname]['dead'] %><br />
                {{ lang._('Wait Timer:') }} <%= int[propertyname]['wait'] %><br />
                {{ lang._('Retransmit Timer:') }} <%= int[propertyname]['retransmit'] %>
              <% } else if (propertyname == 'IPv6' || propertyname == 'IPv4')  { %>
                <%= _.join(int[propertyname],'<br />') %><br />
              <% } else if (propertyname == 'area_cost')  { %>
                <% _.each(int[propertyname], function (ac) { %>
                  <%= ac['area'] %>: <%= ac['cost'] %><br />
                <% }) %>
              <% } else { %>
                <%= translate(int[propertyname]) %>
              <% } %>
            </td>
          </tr>
        <% } else { %>
          <% _.each(_.keys(int[propertyname]), function(lsa) { %>
          <% mylsa = int[propertyname][lsa] %>
            <tr>
              <td><%= lsa %> {{ lang._('Time') }}</td>
              <td><%= mylsa['time'] %> </td>
            </tr>
            <tr>
              <td><%= lsa %> {{ lang._('Count') }}</td>
              <td><%= mylsa['Count'] %> </td>
            </tr>
            <tr>
              <td><%= lsa %> {{ lang._('Flags') }}</td>
              <td><%= mylsa['flags'] %> </td>
            </tr>
          <% }) %>
        <% } %>
      <% }); %>
    </tbody>
  </table>
<% }) %>
</script>
<script src="/ui/js/quagga/lodash.js"></script>
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
  tr['timers'] = '{{ lang._('Timers') }}'
  tr['number_if_scoped_lsas'] = '{{ lang._('Number Of Interface Scoped LSAs') }}'
  tr['pending_lsas'] = '{{ lang._('Pending LSAs') }}'
  tr['area_cost'] = '{{ lang._('Area Cost') }}'
  tr['instance_id'] = '{{ lang._('Instance ID') }}'
  tr['interface_mtu'] = '{{ lang._('Interface MTU') }}'
  tr['type'] = '{{ lang._('Type') }}'
  tr['id'] = '{{ lang._('ID') }}'
  tr['up'] = '{{ lang._('Up') }}'
  tr['DR'] = '{{ lang._('Designated Router') }}'
  tr['BDR'] = '{{ lang._('Backup Designated Router') }}'
  tr['BROADCAST'] = '{{ lang._('Broadcast') }}'
  tr['UNKNOWN'] = '{{ lang._('Unknown') }}'
  tr['POINTOPOINT'] = '{{ lang._('Point to Point') }}'
  tr['LOOPBACK'] = '{{ lang._('Loopback') }}'
  return _.has(tr,data) ? tr[data] : data
}

function checkmark(bin)
{
  return "<i class=\"fa " + (bin ? "fa-check-square" : "fa-square") + " text-muted\"></i>";
}

dataconverters = {
    boolean: {
        from: function (value) { return (value == 'true') || (value == true); },
        to: function (value) { return checkmark(value) }
    },
    raw: {
        from: function (value) { return value },
        to: function (value) { return value }
    }
}

$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/ospfv3overview", sendData={}, callback=function(data,status) {
    content = _.template($('#overviewtpl').html())(data['response'])
    $('#overview').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfv3database", sendData={}, callback=function(data,status) {
    content = _.template($('#databasetpl').html())(data['response'])
    $('#database').html(content)
    $('#database table').bootgrid()
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfv3route", sendData={}, callback=function(data,status) {
    content = _.template($('#routestpl').html())(data['response'])
    $('#routing').html(content)
    $('#routing table').bootgrid()
  });
  /*ajaxCall(url="/api/quagga/diagnostics/ospfv3neighbor", sendData={}, callback=function(data,status) {
    content = _.template($('#neighbortpl').html())(data['response'])
    $('#neighbor').html(content)
  });*/
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
    <!--<li><a data-toggle="tab" href="#neighbor">{{ lang._('Neighbor') }}</a></li>-->
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
