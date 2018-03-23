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
    <td>{{ lang._('RFC2328 Conform') }}</td>
    <td><%= checkmark(ospf_overview['rfc2328_conform']) %></td>
  </tr>
  <tr>
    <td>{{ lang._('ASBR') }}</td>
    <td><%= checkmark(ospf_overview['asbr']) %></td>
  </tr>
  <tr>
    <td>{{ lang._('Router ID') }}</td>
    <td><%= ospf_overview['router_id'] %></td>
  </tr>
  <tr>
    <td>{{ lang._('RFC1583 Compatibility') }}</td>
    <td><%= checkmark(ospf_overview['rfc1583_compatibility']) %></td>
  </tr>
  <tr>
    <td>{{ lang._('Opaque Capability') }}</td>
    <td><%= checkmark(ospf_overview['opaque_capability']) %></td>
  </tr>
  <tr>
    <td>{{ lang._('Initial SPF Scheduling Delay') }}</td>
    <td><%= ospf_overview['initial_spf_scheduling_delay'] %></td>
  </tr>
  <tr>
    <td>{{ lang._('Minimum Hold Time') }}</td>
    <td><%= ospf_overview['hold_time']['min'] %> {{ lang._('Milliseconds') }}</td>
  </tr>
  <tr>
    <td>{{ lang._('Maximum Hold Time') }}</td>
    <td><%= ospf_overview['hold_time']['max'] %> {{ lang._('Milliseconds') }}</td>
  </tr>
  <tr>
    <td>{{ lang._('Current Hold Time Multipier') }}</td>
    <td><%= ospf_overview['current_hold_time_multipier'] %></td>
  </tr>
  <tr>
    <td>{{ lang._('SPF Timer') }}</td>
    <td><%= ospf_overview['spf_timer'] %></td>
  </tr>
  <tr>
    <td>{{ lang._('Refresh Timer') }}</td>
    <td><%= ospf_overview['refresh_timer'] %></td>
  </tr>
  <tr>
    <td>{{ lang._('Areas Attached Count') }}</td>
    <td><%= ospf_overview['areas_attached_count'] %></td>
  </tr>
</tbody>
</table>

<h2>{{ lang._('Link State Area') }}</h2>
 <table class="table table-striped">
  <thead>
    <tr>
      <th></th>
      <th>{{ lang._('Count') }}</th>
      <th>{{ lang._('Checksum') }}</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>{{ lang._('External LSA') }}</td>
      <td><%= ospf_overview['external_lsa']['count'] %></td>
      <td><%= ospf_overview['external_lsa']['checksum'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Opaque AS LSA') }}</td>
      <td><%= ospf_overview['opaque_as_lsa']['count'] %></td>
      <td><%= ospf_overview['opaque_as_lsa']['checksum'] %></td>
    </tr>
  </tbody>
</table>

<h2>{{ lang._('Areas') }}</h2>

<% if (ospf_overview['areas']) { %>
  <% areas = ospf_overview['areas'] %>
  <% _.each(_.keys(areas), function(areaname) { %>
    <% area = areas[areaname] %>
    <h3><%= areaname %></h3>
     <table class="table table-striped">
      <tbody>
        <tr>
          <td>{{ lang._('Interfaces: Total') }}</td>
          <td><%= area['interfaces']['total'] %></td>
        </tr>
        <tr>
          <td>{{ lang._('Interfaces: Active') }}</td>
          <td><%= area['interfaces']['active'] %></td>
        </tr>
        <tr>
          <td>{{ lang._('Fully Adjacent Neighbor Count') }}</td>
          <td><%= area['fully_adjacent_neighbor_count'] %></td>
        </tr>
        <tr>
          <td>{{ lang._('SPF Execution Count') }}</td>
          <td><%= area['spf_exec_count'] %></td>
        </tr>
      </tbody>
    </table>
     <table class="table table-striped">
      <thead>
        <tr>
          <th></th>
          <th>{{ lang._('Count') }}</th>
          <th>{{ lang._('Checksum') }}</th>
        </tr>
      </thead>
      <tbody>
        <% _.each(_.keys(area['lsa']), function(lsaname) { %>
        <% lsa = area['lsa'][lsaname] %>
          <tr>
            <td><%= translate(lsaname) %></td>
            <td><%= lsa['count'] %></td>
            <td><%= lsa['checksum'] %></td>
          </tr>
        <% }) %>
      </tbody>
    </table>
  <% }) %>
<% } %>
</script>
<script type="text/x-template" id="databasetpl">
<% _.each(_.keys(ospf_database), function(router_id) { %>
  <h1>{{ lang._('Router ID:')}} <%= router_id %></h1>
  <hr />
  <h2>{{ lang._('Router Link State Area') }}</h2>
  <% _.each(_.keys(ospf_database[router_id]['router_link_state_area']), function(area) { %>
    <h3>Area <%= area %></h3>
     <table class="table table-striped">
      <thead>
        <tr>
          <th data-column-id="linkid" data-type="string">{{ lang._('Link ID') }}</th>
          <th data-column-id="advrouter" data-type="string">{{ lang._('ADV Router') }}</th>
          <th data-column-id="age" data-type="numeric">{{ lang._('Age') }}</th>
          <th data-column-id="seqnr" data-type="string">{{ lang._('Sequence Number') }}</th>
          <th data-column-id="cksum" data-type="string">{{ lang._('Checksum') }}</th>
          <th data-column-id="linkcnt" data-type="numeric">{{ lang._('Link Count') }}</th>
        </tr>
      </thead>
      <tbody>
        <% _.each(ospf_database[router_id]['router_link_state_area'][area], function(entry) { %>
          <tr>
            <td><%= entry["Link ID"] %></td>
            <td><%= entry["ADV Router"] %></td>
            <td><%= entry["Age"] %></td>
            <td><%= entry["Seq#"] %></td>
            <td><%= entry["CkSum"] %></td>
            <td><%= entry["Link count"] %></td>
          </tr>
        <% }); %>
      </tbody>
     </table>
  <% }); %>
  <h2>{{ lang._('Net Link State Area') }}</h2>
  <% _.each(_.keys(ospf_database[router_id]['net_link_state_area']), function(area) { %>
    <h3>{{ lang._('Area:') }} <%= area %></h3>
     <table class="table table-striped">
      <thead>
        <tr>
          <th data-column-id="linkid" data-type="string">{{ lang._('Link ID') }}</th>
          <th data-column-id="advrouter" data-type="string">{{ lang._('ADV Router') }}</th>
          <th data-column-id="age" data-type="numeric">{{ lang._('Age') }}</th>
          <th data-column-id="seqnr" data-type="string">{{ lang._('Sequence Number') }}</th>
          <th data-column-id="cksum" data-type="string">{{ lang._('Checksum') }}</th>
        </tr>
      </thead>
      <tbody>
        <% _.each(ospf_database[router_id]['net_link_state_area'][area], function(entry) { %>
          <tr>
            <td><%= entry["Link ID"] %></td>
            <td><%= entry["ADV Router"] %></td>
            <td><%= entry["Age"] %></td>
            <td><%= entry["Seq#"] %></td>
            <td><%= entry["CkSum"] %></td>
          </tr>
        <% }); %>
      </tbody>
     </table>
  <% }); %>
  <h2>{{ lang._('External States') }}</h2>
   <table class="table table-striped">
    <thead>
      <tr>
        <th data-column-id="linkid" data-type="string">{{ lang._('Link ID') }}</th>
        <th data-column-id="advrouter" data-type="string">{{ lang._('ADV Router') }}</th>
        <th data-column-id="age" data-type="numeric">{{ lang._('Age') }}</th>
        <th data-column-id="seqnr" data-type="string">{{ lang._('Sequence Number') }}</th>
        <th data-column-id="chsum" data-type="string">{{ lang._('Checksum') }}</th>
        <th data-column-id="route" data-type="string">{{ lang._('Route') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.each(ospf_database[router_id]['external_states'], function(entry) { %>
        <tr>
          <td><%= entry["Link ID"] %></td>
          <td><%= entry["ADV Router"] %></td>
          <td><%= entry["Age"] %></td>
          <td><%= entry["Seq#"] %></td>
          <td><%= entry["CkSum"] %></td>
          <td><%= entry["Route"] %></td>
        </tr>
      <% }); %>
    </tbody>
  </table>
<% }); %>
</script>
<script type="text/x-template" id="routestpl">
<h2>{{ lang._('Network Routing Table') }}</h2>
 <table class="table table-striped">
  <thead>
    <tr>
      <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
      <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
      <th data-column-id="cost" data-type="numeric">{{ lang._('Cost') }}</th>
      <th data-column-id="area" data-type="numeric">{{ lang._('Area') }}</th>
      <th data-column-id="via" data-type="string">{{ lang._('Via') }}</th>
      <th data-column-id="viainterface" data-type="string">{{ lang._('Via interface') }}</th>
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
 <table class="table table-striped">
  <thead>
    <tr>
      <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
      <th data-column-id="cost" data-type="numeric">{{ lang._('Cost') }}</th>
      <th data-column-id="area" data-type="string">{{ lang._('Area') }}</th>
      <th data-column-id="asbr" data-type="boolean">{{ lang._('ASBR') }}</th>
      <th data-column-id="via" data-type="string">{{ lang._('Via') }}</th>
      <th data-column-id="viainterface" data-type="string">{{ lang._('Via interface') }}</th>
    </tr>
  </thead>
  <tbody>
    <% _.each(ospf_route['OSPF router routing table'], function(entry) { %>
      <tr>
        <td><%= entry["type"] %></td>
        <td><%= entry["cost"] %></td>
        <td><%= entry["area"] %></td>
        <td><%= entry["asbr"] %></td>
        <td><%= translate(entry["via"]) %></td>
        <td><%= entry["via_interface"] %></td>
      </tr>
    <% }); %>
  </tbody>
</table>
<h2>{{ lang._('External Routing Table') }}</h2>
 <table class="table table-striped">
  <thead>
    <tr>
      <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
      <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
      <th data-column-id="cost" data-type="string">{{ lang._('Cost') }}</th>
      <th data-column-id="tag" data-type="string">{{ lang._('Tag') }}</th>
      <th data-column-id="via" data-type="string">{{ lang._('Via') }}</th>
      <th data-column-id="viainterface" data-type="string">{{ lang._('Via interface') }}</th>
    </tr>
  </thead>
  <tbody>
    <% _.each(ospf_route['OSPF external routing table'], function(entry) { %>
      <tr>
        <td><%= entry["type"] %></td>
        <td><%= entry["network"] %></td>
        <td><%= entry["cost"] %></td>
        <td><%= entry["tag"] %></td>
        <td><%= translate(entry["via"]) %></td>
        <td><%= entry["via_interface"] %></td>
      </tr>
    <% }); %>
  </tbody>
</table>
</script>
<script type="text/x-template" id="neighbortpl">
   <table class="table table-striped">
    <thead>
      <tr>
        <th data-column-id="neighborid" data-type="string">{{ lang._('Neighbor ID') }}</th>
        <th data-column-id="priority" data-type="numeric">{{ lang._('Priority') }}</th>
        <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
        <th data-column-id="deadtime" data-type="string">{{ lang._('Dead Time') }}</th>
        <th data-column-id="address" data-type="string">{{ lang._('Address') }}</th>
        <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
        <th data-column-id="rxmtl" data-type="numeric">RXmtL</th>
        <th data-column-id="rqstl" data-type="numeric">RqstL</th>
        <th data-column-id="dbsml" data-type="numeric">DBsmL</th>
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
   <table class="table table-striped">
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

  ajaxCall(url="/api/quagga/diagnostics/ospfoverview", sendData={}, callback=function(data,status) {
    content = _.template($('#overviewtpl').html())(data['response'])
    $('#overview').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfdatabase", sendData={}, callback=function(data,status) {
    content = _.template($('#databasetpl').html())(data['response'])
    $('#database').html(content)
    $('#database table').bootgrid()
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfroute", sendData={}, callback=function(data,status) {
    content = _.template($('#routestpl').html())(data['response'])
    $('#routing').html(content)
    $('#routing table').bootgrid({converters: dataconverters})
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfneighbor", sendData={}, callback=function(data,status) {
    content = _.template($('#neighbortpl').html())(data['response'])
    $('#neighbor').html(content)
    $('#neighbor table').bootgrid()
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfinterface", sendData={}, callback=function(data,status) {
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
