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
<table>
<tbody>
  <tr>
    <td>{{ lang._("RFC2328 Conform") }}</td>
    <td><%= checkmark(ospf_overview['rfc2328_conform']) %></td>
  </tr>
  <tr>
    <td>{{ lang._("ASBR") }}</td>
    <td><%= checkmark(ospf_overview['asbr']) %></td>
  </tr>
  <tr>
    <td>{{ lang._("Router ID") }}</td>
    <td><%= ospf_overview['router_id'] %></td>
  </tr>
  <tr>
    <td>{{ lang._("RFC1583 Compatibility") }}</td>
    <td><%= checkmark(ospf_overview['rfc1583_compatibility']) %></td>
  </tr>
  <tr>
    <td>{{ lang._("Opaque Capability") }}</td>
    <td><%= checkmark(ospf_overview['opaque_capability']) %></td>
  </tr>
  <tr>
    <td>{{ lang._("Initial SPF Scheduling Delay") }}</td>
    <td><%= ospf_overview['initial_spf_scheduling_delay'] %></td>
  </tr>
  <tr>
    <td>{{ lang._("Minimum Hold Time") }}</td>
    <td><%= ospf_overview['hold_time']['min'] %> {{ lang._('Milliseconds') }}</td>
  </tr>
  <tr>
    <td>{{ lang._("Maximum Hold Time") }}</td>
    <td><%= ospf_overview['hold_time']['max'] %> {{ lang._('Milliseconds') }}</td>
  </tr>
  <tr>
    <td>{{ lang._("Current Hold Time Multipier") }}</td>
    <td><%= ospf_overview['current_hold_time_multipier'] %></td>
  </tr>
  <tr>
    <td>{{ lang._("SPF Timer") }}</td>
    <td><%= ospf_overview['spf_timer'] %></td>
  </tr>
  <tr>
    <td>{{ lang._("Refresh Timer") }}</td>
    <td><%= ospf_overview['refresh_timer'] %></td>
  </tr>
  <tr>
    <td>{{ lang._("Areas Attached Count") }}</td>
    <td><%= ospf_overview['areas_attached_count'] %></td>
  </tr>
</tbody>
</table>

<h2>{{ lang._('Link State Area') }}</h2>
<table>
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
    <table>
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
          <td>{{ lang._('Fully Adjacent Neighbour Count') }}</td>
          <td><%= area['fully_adjacent_neighbour_count'] %></td>
        </tr>
        <tr>
          <td>{{ lang._('SPF Execution Count') }}</td>
          <td><%= area['spf_exec_count'] %></td>
        </tr>
      </tbody>
    </table>
    <table>
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
    <table>
      <thead>
        <tr>
          <th>{{ lang._('Link ID') }}</th>
          <th>{{ lang._('ADV Router') }}</th>
          <th>{{ lang._('Age') }}</th>
          <th>{{ lang._('Sequence Number') }}</th>
          <th>{{ lang._('Checksum') }}</th>
          <th>{{ lang._('Link Count') }}</th>
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
    <table>
  <% }); %>
  <h2>{{ lang._('Net Link State Area') }}</h2>
  <% _.each(_.keys(ospf_database[router_id]['net_link_state_area']), function(area) { %>
    <h3>Area <%= area %></h3>
    <table>
      <thead>
        <tr>
          <th>{{ lang._('Link ID') }}</th>
          <th>{{ lang._('ADV Router') }}</th>
          <th>{{ lang._('Age') }}</th>
          <th>{{ lang._('Sequence Number') }}</th>
          <th>{{ lang._('Checksum') }}</th>
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
    <table>
  <% }); %>
  <h2>{{ lang._('External States') }}</h2>
  <table>
    <thead>
      <tr>
        <th>{{ lang._('Link ID') }}</th>
        <th>{{ lang._('ADV Router') }}</th>
        <th>{{ lang._('Age') }}</th>
        <th>{{ lang._('Sequence Number') }}</th>
        <th>{{ lang._('Checksum') }}</th>
        <th>{{ lang._('Route') }}</th>
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
        <th>{{ lang._('RXmtL') }}</th>
        <th>{{ lang._('RqstL') }}</th>
        <th>{{ lang._('DBsmL') }}</th>
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

  ajaxCall(url="/api/quagga/diagnostics/ospfoverview", sendData={}, callback=function(data,status) {
    content = _.template($('#overviewtpl').html())(data['response'])
    $('#overview').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfdatabase", sendData={}, callback=function(data,status) {
    content = _.template($('#databasetpl').html())(data['response'])
    $('#database').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfroute", sendData={}, callback=function(data,status) {
    content = _.template($('#routestpl').html())(data['response'])
    $('#routing').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfneighbor", sendData={}, callback=function(data,status) {
    content = _.template($('#neighbortpl').html())(data['response'])
    $('#neighbor').html(content)
  });


});
</script>


<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#overview">{{ lang._('Overview') }}</a></li>
    <li><a data-toggle="tab" href="#routing">{{ lang._('Routing Table') }}</a></li>
    <li><a data-toggle="tab" href="#database">{{ lang._('Database') }}</a></li>
    <li><a data-toggle="tab" href="#neighbor">{{ lang._('Neighbor') }}</a></li>
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
</div>
