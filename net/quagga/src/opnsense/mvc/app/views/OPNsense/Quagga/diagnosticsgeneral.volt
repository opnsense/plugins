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
<script type="text/javascript" src="/ui/js/quagga/lodash.js"></script>
<script type="text/x-template" id="routestpl">
<table>
  <thead>
    <tr>
      <th>{{ lang._('Code') }}</th>
      <th>{{ lang._('Network') }}</th>
      <th>{{ lang._('Administrative Distance') }}</th>
      <th>{{ lang._('Metric') }}</th>
      <th>{{ lang._('Interface') }}</th>
      <th>{{ lang._('Time') }}</th>
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
$(document).ready(function() {
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status'])
  });
  ajaxCall(url="/api/quagga/diagnostics/generalroutes", sendData={}, callback=function(data,status) {
  content = _.template($('#routestpl').html())(data['response'])
  $('#routing').html(content)
});


});
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#routing">{{ lang._('Routing Table') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="routing" class="tab-pane fade in active">
    </div>
</div>
