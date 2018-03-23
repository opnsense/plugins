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
<table>
  <thead>
    <tr>
      <th data-column-id="code" data-type="raw">{{ lang._('Code') }}</th>
      <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
      <th data-column-id="ad" data-type="numeric">{{ lang._('Administrative Distance') }}</th>
      <th data-column-id="metric" data-type="numeric">{{ lang._('Metric') }}</th>
      <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
      <th data-column-id="time" data-type="string">{{ lang._('Time') }}</th>
    </tr>
  </thead>
  <tbody>
    <% _.each(general_routes, function(entry) { %>
      <tr>
        <td>
          <% _.each(entry['code'], function(code) { %>
            <abbr title="<%= translate(code['long']) %>"><%= (code['short']) %></abbr>
          <% }); %>
        </td>
        <td><%= entry['network'] %></td>
        <td><%= entry['ad'] %></td>
        <td><%= entry['metric'] %></td>
        <td><%= entry['interface'] %></td>
        <td><%= entry['time'] %></td>
      </tr>
    <% }); %>
  </tbody>
</table>
</script>

<script>
function translate(content) {
  tr = {};
  tr['kernel route'] = '{{ lang._('Kernel Route') }}';
  tr['FIB route'] = '{{ lang._('FIB Route') }}';
  tr['connected'] = '{{ lang._('Connected') }}';
  tr['selected route'] = '{{ lang._('Selected Route') }}';
  tr['OSPF'] = '{{ lang._('OSPF') }}';
  tr['RIP'] = '{{ lang._('RIP') }}';
  tr['BGP'] = '{{ lang._('BGP') }}';
  if (_.has(tr,content))
  {
    return tr[content];
  }
  else
  {
    return content;
  }
}

dataconverters = {
    boolean: {
        from: function (value) { return (value == 'true') || (value == true); },
        to: function (value) { return checkmark(value) }
    },
    raw: {
        from: function (value) {
            console.log(value)
            return value
        },
        to: function (value) {
            console.log(value);
            return value
        }
    }
}

$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/generalroutes", sendData={}, callback=function(data,status) {
    content = _.template($('#routestpl').html())(data['response'])
    $('#routing').html(content)
    //$('#routing table').bootgrid({converters: dataconverters})
  });
  ajaxCall(url="/api/quagga/diagnostics/generalroutes6", sendData={}, callback=function(data,status) {
    content = _.template($('#routestpl').html())({general_routes: data['response']['general_routes6']})
    $('#routing6').html(content)
    //$('#routing6 table').bootgrid({converters: dataconverters})
  });
  ajaxCall(url="/api/quagga/diagnostics/showrunningconfig", sendData={}, callback=function(data,status) {
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
