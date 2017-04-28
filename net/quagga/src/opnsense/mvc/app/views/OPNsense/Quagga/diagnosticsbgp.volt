{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 Michael Muenz
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
  <table>
    <tr>
      <td>{{ lang._('Table Version') }}</td>
      <td><%= bgp_overview['table_version'] %></td>
    </tr>
    <tr>
      <td>{{ lang._('Local Router ID') }}</td>
      <td><%= bgp_overview['local_router_id'] %></td>
    </tr>
  </table>
  <table>
    <thead>
      <tr>
        <th>{{ lang._('Status') }}</th>
        <th>{{ lang._('Network') }}</th>
        <th>{{ lang._('Next Hop') }}</th>
        <th>{{ lang._('Metric') }}</th>
        <th>{{ lang._('LocPrf') }}</th>
        <th>{{ lang._('Weight') }}</th>
        <th>{{ lang._('Path') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.each(bgp_overview['output'], function (row) { %>
        <tr>
          <td>
            <% _.each(row['status'], function(element) { %>
              <abbr title="<%= translate(element['dn']) %>"><%= element['abb'] %></abbr>
            <% }) %>
          </td>
          <td><%= row['Network'] %></td>
          <td><%= row['Next Hop'] %></td>
          <td><%= row['Metric'] %></td>
          <td><%= row['LocPrf'] %></td>
          <td><%= row['Weight'] %></td>
          <td>
            <% _.each(row['Path'], function(element) { %>
              <abbr title="<%= translate(element['dn']) %>"><%= element['abb'] %></abbr>
            <% }) %>
          </td>
        </tr>
      <% }); %>
    </tbody>
  </table>
</script>
<script type="text/javascript" src="/ui/js/quagga/lodash.js"></script>
<script type="text/javascript">
function translate(x) {
  return x;
}
$(document).ready(function() {
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status']);
  });

  ajaxCall(url="/api/quagga/diagnostics/showipbgp", sendData={}, callback=function(data,status) {
      content = _.template($('#overviewtpl').html())(data['response'])
      $('#overview').html(content)
  });

  ajaxCall(url="/api/quagga/diagnostics/showipbgpsummary", sendData={}, callback=function(data,status) {
      $("#summarycontent").text(data['response']);
  });
});
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#overview">{{ lang._('Overview') }}</a></li>
    <li><a data-toggle="tab" href="#summary">{{ lang._('Summary') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="overview" class="tab-pane fade in active">
      {{ lang._('loading...') }}
    </div>
    <div id="summary" class="tab-pane fade in">
      <pre id="summarycontent"></pre>
    </div>
</div>
