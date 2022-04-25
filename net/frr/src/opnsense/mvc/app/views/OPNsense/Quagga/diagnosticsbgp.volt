{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

{#
{{ partial("layout_partials/base_form",['fields':diagnosticsForm,'id':'frm_diagnostics_settings'])}}
#}

<script type="text/x-template" id="routestpl">
  <h2>{{ lang._('Table Version') }}: <%= tableVersion %></h2>
  <table class="table table-striped">
    <thead>
      <tr>
        <th data-column-id="valid" data-type="boolean" data-formatter="boolean" data-width="5%">{{ lang._('Valid') }}</th>
        <th data-column-id="best" data-type="boolean" data-formatter="boolean" data-width="5%">{{ lang._('Best') }}</th>
        <th data-column-id="internal" data-type="boolean" data-formatter="boolean" data-width="6%">{{ lang._('Internal') }}</th>
        <th data-column-id="network" data-type="string" data-width="21%">{{ lang._('Network') }}</th>
        <th data-column-id="nexthop" data-type="string" data-width="21%">{{ lang._('Next Hop') }}</th>
        <th data-column-id="metric" data-type="numeric" data-width="5%">{{ lang._('Metric') }}</th>
        <th data-column-id="locprf" data-type="numeric" data-width="5%">{{ lang._('LocPrf') }}</th>
        <th data-column-id="weight" data-type="numeric" data-width="6%">{{ lang._('Weight') }}</th>
        <th data-column-id="path" data-type="string" data-width="16%">{{ lang._('Path') }}</th>
        <th data-column-id="origin" data-type="string" data-formatter="origin" data-width="10%">{{ lang._('Origin') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.forEach(routes, function(route_array, network) { %>
        <% _.forEach(route_array, function(route) { %>
          <% _.forEach(route['nexthops'], function(nexthop) { %>
            <tr>
              <td><%= (typeof(route['valid']) != "undefined" && route['valid']) %></td>
              <td><%= (typeof(route['bestpath']) != "undefined" && route['bestpath']) %></td>
              <td><%= (typeof(route['pathFrom']) != "undefined" && route['pathFrom'] == 'internal') %></td>
              <td><%= network %></td>
              <td><%= nexthop['ip'] %></td>
              <td><%= route['metric'] %></td>
              <td><%= route['locPrf'] %></td>
              <td><%= route['weight'] %></td>
              <td><%= (route['path'] == "" ? "{{ lang._('Internal') }}" : route['path']) %></td>
              <td><%= route['origin'] %></td>
            </tr>
          <% }); %>
        <% }); %>
      <% }); %>
    </tbody>
  </table>
</script>

<script type="text/javascript" src="/ui/js/quagga/lodash.js"></script>
<script type="text/javascript">
let dataconverters = {
  boolean: {
    from: function (value) { return (value == 'true') || (value == true); },
    to: function (value) { return value; }
  }
}

let dataformatters = {
  boolean: function (column, row) {
    if (row[column.id]) {
        return "<span class=\"fa fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
    } else {
        return "<span class=\"fa fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
    }
  },
  origin: function(column, row) {
    return (row[column.id] === 'incomplete' ? '<abbr title="{{ lang._('Incomplete') }}">&quest;</abbr>' : row[column.id]);
  }
}

$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/bgproute4", sendData={}, callback=function(data, status) {
    content = _.template($('#routestpl').html())(data['response']);
    $('#routing').html(content);
    $('#routing table').bootgrid({
      formatters: dataformatters
    });
  });

  ajaxCall(url="/api/quagga/diagnostics/bgproute6", sendData={}, callback=function(data, status) {
    content = _.template($('#routestpl').html())(data['response']);
    $('#routing6').html(content);
    $('#routing6 table').bootgrid({
      formatters: dataformatters
    });
  });

  ajaxCall(url="/api/quagga/diagnostics/bgpneighbors/plain", sendData={}, callback=function(data, status) {
    $('#neighborscontent').text(data['response']);
  });

  ajaxCall(url="/api/quagga/diagnostics/bgpsummary/plain", sendData={}, callback=function(data, status) {
    $("#summarycontent").text(data['response']);
  });
});
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
  <li class="active"><a data-toggle="tab" href="#routing">IPv4 {{ lang._('Routing Table') }}</a></li>
  <li><a data-toggle="tab" href="#routing6">IPv6 {{ lang._('Routing Table') }}</a></li>
  <li><a data-toggle="tab" href="#neighbors">{{ lang._('Neighbors') }}</a></li>
  <li><a data-toggle="tab" href="#summary">{{ lang._('Summary') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
  <div id="routing" class="tab-pane fade in active"></div>
  <div id="routing6" class="tab-pane fade in"></div>
  <div id="neighbors" class="tab-pane fade in">
    <pre id="neighborscontent"></pre>
  </div>
  <div id="summary" class="tab-pane fade in">
    <pre id="summarycontent"></pre>
  </div>
</div>
