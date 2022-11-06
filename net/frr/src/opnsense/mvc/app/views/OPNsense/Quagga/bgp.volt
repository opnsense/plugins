{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 - 2020 Michael Muenz <m.muenz@gmail.com>
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
<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#neighbors">{{ lang._('Neighbors') }}</a></li>
    <li><a data-toggle="tab" href="#aspaths">{{ lang._('AS Path Lists') }}</a></li>
    <li><a data-toggle="tab" href="#prefixlists">{{ lang._('Prefix Lists') }}</a></li>
    <li><a data-toggle="tab" href="#communitylists">{{ lang._('Community Lists') }}</a></li>
    <li><a data-toggle="tab" href="#routemaps">{{ lang._('Route Maps') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':bgpForm,'id':'frm_bgp_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="neighbors" class="tab-pane fade in">
        <table id="grid-neighbors" class="table table-responsive" data-editDialog="DialogEditBGPNeighbor">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="address" data-type="string" data-visible="true">{{ lang._('Neighbor Address') }}</th>
                    <th data-column-id="remoteas" data-type="string" data-visible="true">{{ lang._('Remote AS') }}</th>
                    <th data-column-id="linkedPrefixlistIn" data-type="string" data-visible="true">{{ lang._('Prefix List inbound') }}</th>
                    <th data-column-id="linkedPrefixlistOut" data-type="string" data-visible="true">{{ lang._('Prefix List outbound') }}</th>
                    <th data-column-id="linkedRoutemapIn" data-type="string" data-visible="true">{{ lang._('Route Map inbound') }}</th>
                    <th data-column-id="linkedRoutemapOut" data-type="string" data-visible="true">{{ lang._('Route Map outbound') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="10"></td>
                    <td colspan="1">
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                        <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="aspaths" class="tab-pane fade in">
        <table id="grid-aspaths" class="table table-responsive" data-editDialog="DialogEditBGPASPaths">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-sortable="false">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="number" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Number') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Action') }}</th>
                    <th data-column-id="as" data-type="string" data-visible="true" data-sortable="false">{{ lang._('AS Number') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span> {{ lang._('Reload Service') }}</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="prefixlists" class="tab-pane fade in">
        <table id="grid-prefixlists" class="table table-responsive" data-editDialog="DialogEditBGPPrefixLists">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-sortable="false">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Name') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="seqnumber" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Sequence Number') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Action') }}</th>
                    <th data-column-id="network" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Network') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span> {{ lang._('Reload Service') }}</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="communitylists" class="tab-pane fade in">
        <table id="grid-communitylists" class="table table-responsive" data-editDialog="DialogEditBGPCommunityLists">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle" data-sortable="false">{{ lang._('Enabled') }}</th>
                    <th data-column-id="number" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Number') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="seqnumber" data-type="string" data-visible="true" data-sortable="true">{{ lang._('Secquence Number') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Action') }}</th>
                    <th data-column-id="community" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Community') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span> {{ lang._('Reload Service') }}</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="routemaps" class="tab-pane fade in">
        <table id="grid-routemaps" class="table table-responsive" data-editDialog="DialogEditBGPRouteMaps">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="action" data-type="string" data-visible="true">{{ lang._('Action') }}</th>
                    <th data-column-id="id" data-type="string" data-visible="true">{{ lang._('ID') }}</th>
                    <th data-column-id="match" data-type="string" data-visible="true">{{ lang._('AS Path List') }}</th>
                    <th data-column-id="match2" data-type="string" data-visible="true">{{ lang._('Prefix List') }}</th>
                    <th data-column-id="match3" data-type="string" data-visible="true">{{ lang._('Community List') }}</th>
                    <th data-column-id="set" data-type="string" data-visible="true">{{ lang._('Set') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span> {{ lang._('Reload Service') }}</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>

function quagga_update_status() {
    updateServiceControlUI('quagga');
}

$(document).ready(function() {
  var data_get_map = {'frm_bgp_settings':"/api/quagga/bgp/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });
  quagga_update_status();

  // link save button to API set action
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/quagga/bgp/set",formid='frm_bgp_settings',callback_ok=function(){
          $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
          ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
              quagga_update_status();
              $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
          });
      });
  });

  /* allow a user to manually reload the service (for forms which do not do it automatically) */
  $('.reload_btn').click(function reload_handler() {
    $(".reloadAct_progress").addClass("fa-spin");
    ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
        quagga_update_status();
        $(".reloadAct_progress").removeClass("fa-spin");
    });
  });

  $("#grid-neighbors").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchNeighbor',
      'get':'/api/quagga/bgp/getNeighbor/',
      'set':'/api/quagga/bgp/setNeighbor/',
      'add':'/api/quagga/bgp/addNeighbor/',
      'del':'/api/quagga/bgp/delNeighbor/',
      'toggle':'/api/quagga/bgp/toggleNeighbor/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-aspaths").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchAspath',
      'get':'/api/quagga/bgp/getAspath/',
      'set':'/api/quagga/bgp/setAspath/',
      'add':'/api/quagga/bgp/addAspath/',
      'del':'/api/quagga/bgp/delAspath/',
      'toggle':'/api/quagga/bgp/toggleAspath/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-prefixlists").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchPrefixlist',
      'get':'/api/quagga/bgp/getPrefixlist/',
      'set':'/api/quagga/bgp/setPrefixlist/',
      'add':'/api/quagga/bgp/addPrefixlist/',
      'del':'/api/quagga/bgp/delPrefixlist/',
      'toggle':'/api/quagga/bgp/togglePrefixlist/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-communitylists").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchCommunitylist',
      'get':'/api/quagga/bgp/getCommunitylist/',
      'set':'/api/quagga/bgp/setCommunitylist/',
      'add':'/api/quagga/bgp/addCommunitylist/',
      'del':'/api/quagga/bgp/delCommunitylist/',
      'toggle':'/api/quagga/bgp/toggleCommunitylist/',
      'options':{selection:false, multiSelect:false}
    }
  );
  $("#grid-routemaps").UIBootgrid(
    { 'search':'/api/quagga/bgp/searchRoutemap',
      'get':'/api/quagga/bgp/getRoutemap/',
      'set':'/api/quagga/bgp/setRoutemap/',
      'add':'/api/quagga/bgp/addRoutemap/',
      'del':'/api/quagga/bgp/delRoutemap/',
      'toggle':'/api/quagga/bgp/toggleRoutemap/',
      'options':{selection:false, multiSelect:false}
    }
  );
    });
</script>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPNeighbor,'id':'DialogEditBGPNeighbor','label':lang._('Edit Neighbor')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPASPaths,'id':'DialogEditBGPASPaths','label':lang._('Edit AS Paths')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPPrefixLists,'id':'DialogEditBGPPrefixLists','label':lang._('Edit Prefix Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPCommunityLists,'id':'DialogEditBGPCommunityLists','label':lang._('Edit Community Lists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPRouteMaps,'id':'DialogEditBGPRouteMaps','label':lang._('Edit Route Maps')])}}
