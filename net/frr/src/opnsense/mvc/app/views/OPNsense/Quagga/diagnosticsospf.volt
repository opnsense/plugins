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
        <td><%= checkmark(rfc2328Conform) %></td>
      </tr>
      <tr>
        <td>{{ lang._('ASBR') }}</td>
        <td><%= checkmark(asbrRouter == "injectingExternalRoutingInformation") %></td>
      </tr>
      <tr>
        <td>{{ lang._('Router ID') }}</td>
        <td><%= routerId %></td>
      </tr>
      <tr>
        <td>{{ lang._('RFC1583 Compatibility') }}</td>
        <td><%= checkmark(typeof rfc1583Compatibility != "undefined" && rfc1583Compatibility) %></td>
      </tr>
      <tr>
        <td>{{ lang._('Opaque Capability') }}</td>
        <td><%= checkmark(typeof opaqueCapable != "undefined" && opaqueCapable) %></td>
      </tr>
      <tr>
        <td>{{ lang._('Initial SPF Scheduling Delay') }}</td>
        <td><%= spfScheduleDelayMsecs %> {{ lang._('Milliseconds') }}</td>
      </tr>
      <tr>
        <td>{{ lang._('Minimum Hold Time') }}</td>
        <td><%= holdtimeMinMsecs %> {{ lang._('Milliseconds') }}</td>
      </tr>
      <tr>
        <td>{{ lang._('Maximum Hold Time') }}</td>
        <td><%= holdtimeMaxMsecs %> {{ lang._('Milliseconds') }}</td>
      </tr>
      <tr>
        <td>{{ lang._('Current Hold Time Multipier') }}</td>
        <td><%= holdtimeMultplier %></td>
      </tr>
      <tr>
        <td>{{ lang._('Refresh Timer') }}</td>
        <td><%= refreshTimerMsecs %> {{ lang._('Milliseconds') }}</td>
      </tr>
      <tr>
        <td>{{ lang._('Areas Attached Count') }}</td>
        <td><%= attachedAreaCounter %></td>
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
        <td><%= lsaExternalCounter %></td>
        <td><%= lsaExternalChecksum %></td>
      </tr>
      <tr>
        <td>{{ lang._('Opaque AS LSA') }}</td>
        <td><%= lsaAsopaqueCounter %></td>
        <td><%= lsaAsOpaqueChecksum %></td>
      </tr>
    </tbody>
  </table>

  <% if (areas) { %>
    <h2>{{ lang._('Areas') }}</h2>
    <% _.forEach(areas, function(area, areaname) { %>
      <br /> 
      <table class="table table-striped">
        <thead>
          <tr>
            <th><%= areaname %></th>
            <th>{{ lang._('Count') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ lang._('Interfaces: Total') }}</td>
            <td><%= area['areaIfTotalCounter'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('Interfaces: Active') }}</td>
            <td><%= area['areaIfActiveCounter'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('Fully Adjacent Neighbor Count') }}</td>
            <td><%= area['nbrFullAdjacentCounter'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('SPF Execution Count') }}</td>
            <td><%= area['spfExecutedCounter'] %></td>
          </tr>
        </tbody>
      </table>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>{{ lang._('LSA Type') }}</th>
            <th>{{ lang._('Count') }}</th>
            <th>{{ lang._('Checksum') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ lang._('Router') }}</td>
            <td><%= area['lsaRouterNumber'] %></td>
            <td><%= area['lsaRouterChecksum'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('Network') }}</td>
            <td><%= area['lsaNetworkNumber'] %></td>
            <td><%= area['lsaNetworkChecksum'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('Summary') }}</td>
            <td><%= area['lsaSummaryNumber'] %></td>
            <td><%= area['lsaSummaryChecksum'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('ASBR Summary') }}</td>
            <td><%= area['lsaAsbrNumber'] %></td>
            <td><%= area['lsaAsbrChecksum'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('NSSA') }}</td>
            <td><%= area['lsaNssaNumber'] %></td>
            <td><%= area['lsaNssaChecksum'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('Opaque Link') }}</td>
            <td><%= area['lsaOpaqueLinkNumber'] %></td>
            <td><%= area['lsaOpaqueLinkChecksum'] %></td>
          </tr>
          <tr>
            <td>{{ lang._('Opaque Area') }}</td>
            <td><%= area['lsaOpaqueAreaNumber'] %></td>
            <td><%= area['lsaOpaqueAreaNumber'] %></td>
          </tr>
        </tbody>
      </table>
    <% }); %>
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
  <table class="table table-striped">
  <thead>
    <tr>
      <th data-column-id="type" data-type="string" data-formatter="route_type">{{ lang._('Type') }}</th>
      <th data-column-id="network" data-type="string">{{ lang._('Network') }}</th>
      <th data-column-id="cost" data-type="numeric">{{ lang._('Cost') }}</th>
      <th data-column-id="area" data-type="string">{{ lang._('Area') }}</th>
      <th data-column-id="via" data-type="string">{{ lang._('Via') }}</th>
      <th data-column-id="viainterface" data-type="string">{{ lang._('Via interface') }}</th>
    </tr>
  </thead>
  <tbody>
    <% _.forEach(routes, function(route, network) { %>
      <tr>
        <td><%= route['routeType'] %></td>
        <td><%= network %></td>
        <td><%= route['cost'] %></td>
        <td><%= route['area'] %></td>
        <td><%= (typeof route['nexthops'][0]['via'] != "undefined" ? route['nexthops'][0]['ip'] : translate('directly attached')) %></td>
        <td><%= (typeof route['nexthops'][0]['via'] != "undefined" ? route['nexthops'][0]['via'] : route['nexthops'][0]['directly attached to']) %></td>
      </tr>
      <% route['nexthops'].shift(); %>
      <% _.forEach(route['nexthops'], function(nexthop) { %>
        <tr>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td><%= (typeof nexthop['via'] != "undefined" ? nexthop['ip'] : translate('directly attached')) %></td>
          <td><%= (typeof nexthop['via'] != "undefined" ? nexthop['via'] : nexthop['directly attached to']) %></td>
        </tr>
      <% }); %>
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
        <th data-column-id="deadtime" data-type="string">{{ lang._('Dead Time') }} &lsqb;ms&rsqb;</th>
        <th data-column-id="address" data-type="string">{{ lang._('Address') }}</th>
        <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
        <th data-column-id="rxmtl" data-type="numeric">{{ lang._('Retransmit Counter') }}</th>
        <th data-column-id="rqstl" data-type="numeric">{{ lang._('Request Counter') }}</th>
        <th data-column-id="dbsml" data-type="numeric">{{ lang._('DB Summary Counter') }}</th>
      </tr>
    </thead>
    <tbody>
      <% _.forEach(neighbors, function(connections, neighborId) { %>
        <% _.forEach(connections, function(connection) { %>
          <tr>
            <td><%= neighborId %></td>
            <td><%= connection['priority'] %></td>
            <td><%= translate(connection['state']) %></td>
            <td><%= connection['deadTimeMsecs'] %></td>
            <td><%= connection['address'] %></td>
            <td><%= connection['ifaceName'] %></td>
            <td><%= connection['retransmitCounter'] %></td>
            <td><%= connection['requestCounter'] %></td>
            <td><%= connection['dbSummaryCounter'] %></td>
          </tr>
        <% }); %>
      <% }); %>
    </tbody>
  </table>
</script>
<script type="text/x-template" id="interfacetpl">
  <% _.forEach(interfaces, function(interface, interfacename) { %>
    <h2><%= interfacename %></h2>
    <table class="table table-striped">
      <tbody>
        <% _.forEach(interface, function(propertyvalue, propertyname) { %>
          <tr>
            <td><%= translate(propertyname) %></td>
            <td>
              <% if (typeof(propertyvalue) == "boolean")  { %>
                <%= checkmark(propertyvalue) %>
              <% } else { %>
                <%= translate(propertyvalue) %>
              <% } %>
            </td>
          </tr>
        <% }); %>
      </tbody>
    </table>
  <% }); %>
</script>
<script type="text/javascript" src="/ui/js/quagga/lodash.js"></script>
<script type="text/javascript">
function translate(data) {
  let tr = [];
  // routing table tab
  tr['N'] = '{{ lang._('Network') }}';
  tr['R'] = '{{ lang._('Router') }}';
  tr['IA'] = '{{ lang._('OSPF inter area') }}';
  tr['N1'] = '{{ lang._('OSPF NSSA external type 1') }}';
  tr['N2'] = '{{ lang._('OSPF NSSA external type 2') }}';
  tr['E1'] = '{{ lang._('OSPF external type 1') }}';
  tr['E2'] = '{{ lang._('OSPF external type 2') }}';
  tr['directly attached'] = '{{ lang._('Directly Attached') }}';

  // neighbor tab
  tr['Full/DR'] = '{{ lang._('Full (Designated Router)') }}';

  // interfaces tab
  tr['ifUp'] = '{{ lang._('Up') }}';
  tr['ifIndex'] = '{{ lang._('Index') }}';
  tr['mtuBytes'] = '{{ lang._('MTU') }} &lsqb;{{ lang._('Bytes') }}&rsqb;';
  tr['bandwidthMbit'] = '{{ lang._('Bandwidth') }} &lsqb;Mbit/s&rsqb;';
  tr['ifFlags'] = '{{ lang._('Flags') }}';
  tr['ospfEnabled'] = '{{ lang._('OSPF Enabled') }}';
  tr['ipAddress'] = '{{ lang._('Address') }}';
  tr['ipAddressPrefixlen'] = '{{ lang._('Prefix Length') }}';
  tr['ospfIfType'] = '{{ lang._('Type') }}';
  tr['localIfUsed'] = '{{ lang._('Local Interface') }}';
  tr['area'] = '{{ lang._('Area') }}';
  tr['routerId'] = '{{ lang._('Router ID') }}';
  tr['networkType'] = '{{ lang._('Network Type') }}';
  tr['cost'] = '{{ lang._('Cost') }}';
  tr['transmitDelaySecs'] = '{{ lang._('Transmit Delay') }} &lsqb;s&rsqb;';
  tr['state'] = '{{ lang._('State') }}';
  tr['priority'] = '{{ lang._('Priority') }}';
  tr['mcastMemberOspfAllRouters'] = '{{ lang._('Multicast') }} {{ lang._('Group') }} {{ lang._('Member') }} OSPFAllRouters';
  tr['timerMsecs'] = '{{ lang._('Hello Timer') }} &lsqb;ms&rsqb;';
  tr['timerDeadSecs'] = '{{ lang._('Dead Timer') }} &lsqb;s&rsqb;';
  tr['timerWaitSecs'] = '{{ lang._('Wait Timer') }} &lsqb;s&rsqb;';
  tr['timerRetransmitSecs'] = '{{ lang._('Retransmit Timer') }} &lsqb;s&rsqb;';
  tr['timerPassiveIface'] = '{{ lang._('Passive Interface') }}';
  tr['timerHelloInMsecs'] = '{{ lang._('Hello Due In') }} &lsqb;ms&rsqb;';
  tr['nbrCount'] = '{{ lang._('Neighbor Count') }}';
  tr['nbrAdjacentCount'] = '{{ lang._('Adjacent Neighbor Count') }}';

  return _.has(tr,data) ? tr[data] : data;
}

function checkmark(bin) {
  return "<i class=\"fa " + (bin ? "fa-check-square" : "fa-square") + " text-muted\"></i>";
}

dataformatters = {
  route_type: function(column, row) {
    let result = ''

    _.forEach(row.type.split(' '), function(routeType) {
      if(translate(routeType) == routeType) result += routeType;
      else result += '<abbr title="' + translate(routeType) + '">' + routeType + '</abbr>';
      result += ' ';
    });

    return result;
  }
};

$(document).ready(function() {
  updateServiceControlUI('quagga');

  ajaxCall(url="/api/quagga/diagnostics/ospfoverview", sendData={}, callback=function(data, status) {
    let content = _.template($('#overviewtpl').html())(data['response']);
    $('#overview').html(content);
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfdatabase", sendData={}, callback=function(data, status) {
    let content = _.template($('#databasetpl').html())(data['response']);
    $('#database').html(content);
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfroute", sendData={}, callback=function(data, status) {
    let content = _.template($('#routestpl').html())({
      routes: data['response']
    });
    $('#routing').html(content);
    $('#routing table').bootgrid({
      formatters: dataformatters
    });
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfneighbor", sendData={}, callback=function(data, status) {
    let content = _.template($('#neighbortpl').html())(data['response']);
    $('#neighbor').html(content);
    $('#neighbor table').bootgrid({
      formatters: dataformatters
    });
  });
  ajaxCall(url="/api/quagga/diagnostics/ospfinterface", sendData={}, callback=function(data, status) {
    let content = _.template($('#interfacetpl').html())(data['response']);
    $('#interface').html(content);
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
  <div id="overview" class="tab-pane fade in active"></div>
  <div id="routing" class="tab-pane fade in"></div>
  <div id="database" class="tab-pane fade in"></div>
  <div id="neighbor" class="tab-pane fade in"></div>
  <div id="interface" class="tab-pane fade in"></div>
</div>
