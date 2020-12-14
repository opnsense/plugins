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

<script type="text/x-template" id="overviewtpl">
  <h2>{{ lang._('General') }}</h2>
  <table class="table table-striped">
    <tbody>
      <tr>
        <td>{{ lang._('Table Version') }}</td>
        <td><%= tableVersion %></td>
      </tr>
      <tr>
        <td>{{ lang._('Local Router ID') }}</td>
        <td><%= routerId %></td>
      </tr>
      <tr>
        <td>{{ lang._('Local AS') }}</td>
        <td><%= localAS %></td>
      </tr>
    </tbody>
  </table>
</script>
<script type="text/x-template" id="routestpl">
  <table class="table table-striped">
    <thead>
      <tr>
        <th data-column-id="status" data-type="raw">{{ lang._('Status') }}</th>
        <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
        <th data-column-id="nexthop" data-type="string">{{ lang._('Next Hop') }}</th>
        <th data-column-id="metric" data-type="numeric">{{ lang._('Metric') }}</th>
        <th data-column-id="locprf" data-type="numeric">{{ lang._('LocPrf') }}</th>
        <th data-column-id="weight" data-type="numeric">{{ lang._('Weight') }}</th>
        <th data-column-id="path" data-type="string">{{ lang._('Path') }}</th>
        <th data-column-id="origin" data-type="string">{{ lang._('Origin') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.forEach(routes, function(route_array, network) { %>
        <% _.forEach(route_array, function(route) { %>
          <% _.forEach(route['nexthops'], function(nexthop) { %>
            <tr>
              <td>
                <% if(typeof(route['valid']) != "undefined" && route['valid']) { %>
                  <abbr title="{{ lang._('Valid') }}">&ast;</abbr>
                <% } %>
                <% if(typeof(route['bestpath']) != "undefined" && route['bestpath']) { %>
                  <abbr title="{{ lang._('Best') }}">&gt;</abbr>
                <% } %>
                <% if(typeof(route['pathFrom']) != "undefined" && route['pathFrom'] == 'internal') { %>
                  <abbr title="{{ lang._('Internal') }}">i</abbr>
                <% } %>
              </td>
              <td><%= network %></td>
              <td><%= nexthop['ip'] %></td>
              <td><%= route['metric'] %></td>
              <td><%= route['locPrf'] %></td>
              <td><%= route['weight'] %></td>
              <td><%= route['path'] %></td>
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
$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/bgpoverview", sendData={}, callback=function(data, status) {
      let content = _.template($('#overviewtpl').html())(data['response']);
      $('#overview').html(content);
      content = _.template($('#routestpl').html())(data['response']);
      $('#routing').html(content);
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
  <li class="active"><a data-toggle="tab" href="#overview">{{ lang._('Overview') }}</a></li>
  <li><a data-toggle="tab" href="#routing">{{ lang._('Routing Table') }}</a></li>
  <li><a data-toggle="tab" href="#neighbors">{{ lang._('Neighbors') }}</a></li>
  <li><a data-toggle="tab" href="#summary">{{ lang._('Summary') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
  <div id="overview" class="tab-pane fade in active"></div>
  <div id="routing" class="tab-pane fade in"></div>
  <div id="neighbors" class="tab-pane fade in">
    <pre id="neighborscontent"></pre>
  </div>
  <div id="summary" class="tab-pane fade in">
    <pre id="summarycontent"></pre>
  </div>
</div>
