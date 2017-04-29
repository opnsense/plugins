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

<script type="text/x-template" id="databasetpl">
<% _.each(_.keys(ospf_database), function(router_id) { %>
  <h1>{{ lang._('Router ID:')}} <%= router_id %></h1>
  <hr />
  <h2>{{ lang._('Link State Area') }}</h2>
  <% _.each(_.keys(ospf_database[router_id]['link_state_area']), function(area) { %>
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
        <% _.each(ospf_database[router_id]['link_state_area'][area], function(entry) { %>
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
        <td><%= entry["via"] %></td>
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
        <td><%= entry["via"] %></td>
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
        <td><%= entry["via"] %></td>
        <td><%= entry["via_interface"] %></td>
      </tr>
    <% }); %>
  </tbody>
</table>
</script>
<script type="text/javascript" src="/ui/js/lodash.js"></script>
<script>

function translate(string)
{
  return string;
}

function checkmark(bin)
{
  return "<i class=\"fa " + (bin ? "fa-check-square" : "fa-square") + " text-muted\"></i>";
}

$(document).ready(function() {
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status'])
  });

  //ajaxCall(url="/api/quagga/diagnostics/ospfdatabase", sendData={}, callback=function(data,status) {
    //
  //});
  ajaxCall(url="/api/quagga/diagnostics/ospfdatabase", sendData={}, callback=function(data,status) {
    content = _.template($('#databasetpl').html())(data['response'])
    $('#database').html(content)
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfroute", sendData={}, callback=function(data,status) {
  content = _.template($('#routestpl').html())(data['response'])
  $('#routing').html(content)
});
    
    
});
</script>


<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#overview">{{ lang._('Overview') }}</a></li>
    <li><a data-toggle="tab" href="#routing">{{ lang._('Routing Table') }}</a></li>
    <li><a data-toggle="tab" href="#database">{{ lang._('OSPF Database') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="overview" class="tab-pane fade in">
    </div>
    <div id="routing" class="tab-pane fade in">
    </div>
    <div id="database" class="tab-pane fade in">
    </div>
</div>

