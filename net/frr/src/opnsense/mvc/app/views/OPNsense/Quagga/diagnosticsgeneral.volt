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
<script src="/ui/js/quagga/lodash.js"></script>
<script type="text/x-template" id="routestpl">
  <table class="table table-striped">
    <thead>
      <tr>
        <th data-column-id="code" data-type="string" data-formatter="code">{{ lang._('Code') }}</th>
        <th data-column-id="selected" data-type="boolean" data-visible="false">{{ lang._('Selected') }}</th>
        <th data-column-id="installed" data-type="boolean" data-visible="false">{{ lang._('Installed') }}</th>
        <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
        <th data-column-id="ad" data-type="numeric">{{ lang._('Administrative Distance') }}</th>
        <th data-column-id="metric" data-type="numeric">{{ lang._('Metric') }}</th>
        <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
        <th data-column-id="via" data-type="string">{{ lang._('Via') }}</th>
        <th data-column-id="time" data-type="string">{{ lang._('Time') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.forEach(routes, function(route_array, network) { %>
        <% _.forEach(route_array, function(route) { %>
          <% _.forEach(route['nexthops'], function(nexthop) { %>
            <tr>
              <td><%= route['protocol'] %></td>
              <td><%= (typeof(route['selected']) != "undefined" && route['selected']) %></td>
              <td><%= (typeof(route['installed']) != "undefined" && route['installed']) %></td>
              <td><%= network %></td>
              <td><%= route['distance'] %></td>
              <td><%= route['metric'] %></td>
              <td><%= nexthop['interfaceName'] %></td>
              <td><%= (typeof(nexthop['ip']) != "undefined" ? nexthop['ip'] : '{{ lang._('Directly Attached') }}') %></td>
              <td><%= route['uptime'] %></td>
            </tr>
          <% }); %>
        <% }); %>
      <% }); %>
    </tbody>
  </table>
</script>

<script type="text/javascript">
function translateProtocol(data) {
  let tr = [];

  // routing table tab
  tr['kernel'] = {short: 'K', long: '{{ lang._('Kernel') }}'};
  tr['connected'] = {short: 'C', long: '{{ lang._('Connected') }}'};
  tr['bgp'] = {short: 'B', long: '{{ lang._('BGP') }}'};
  tr['ospf'] = {short: 'O', long: '{{ lang._('OSPF') }}'};
  tr['ospf6'] = {short: 'O', long: '{{ lang._('OSPFv3') }}'};

  return _.has(tr,data) ? tr[data] : data;
}

let dataconverters = {
  boolean: {
    from: function (value) { return (value == 'true') || (value == true); },
    to: function (value) { return value; }
  }
}

let dataformatters = {
  code: function(column, row) {
    let result = row.code;
    let protocol = translateProtocol(row.code);

    if(typeof(protocol) != "string") result = '<abbr title="' + protocol['long'] + '">' + protocol['short'] + '</abbr> ';
    if(row.selected) result += '<abbr title="{{ lang._('Selected') }}">&gt;</abbr> ';
    if(row.installed) result += '<abbr title="{{ lang._('FIB') }}">&ast;</abbr>';

    return result;
  }
};

$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/generalroute", sendData={}, callback=function(data, status) {
    let content = _.template($('#routestpl').html())({
      routes: data['response']['ipv4']
    });
    $('#routing').html(content);
    $('#routing table').bootgrid({
      converters: dataconverters,
      formatters: dataformatters
    });
    content = _.template($('#routestpl').html())({
      routes: data['response']['ipv6']
    });
    $('#routing6').html(content);
    $('#routing6 table').bootgrid({
      converters: dataconverters,
      formatters: dataformatters
    });
  });

  ajaxCall(url="/api/quagga/diagnostics/generalrunningconfig", sendData={}, callback=function(data, status) {
      $("#runningconfig").text(data['response']);
  });
});
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
  <li class="active"><a data-toggle="tab" href="#routing">{{ lang._('IPv4 Routes') }}</a></li>
  <li><a data-toggle="tab" href="#routing6">{{ lang._('IPv6 Routes') }}</a></li>
  <li><a data-toggle="tab" href="#showrun">{{ lang._('Running Configuration') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
  <div id="routing" class="tab-pane fade in active"></div>
  <div id="routing6" class="tab-pane fade in"></div>
  <div id="showrun" class="tab-pane fade in">
    <pre id="runningconfig"></pre>
  </div>
</div>
