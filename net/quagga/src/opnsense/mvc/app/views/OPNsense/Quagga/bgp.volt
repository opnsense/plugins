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

#}{{ partial("layout_partials/base_form",['fields':bgpForm,'id':'frm_bgp_settings'])}}


<script type="text/javascript">
$(document).ready(function() {
  var data_get_map = {'frm_bgp_settings':"/api/quagga/bgp/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });
  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status']);
  });

  // link save button to API set action
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/quagga/bgp/set",formid='frm_bgp_settings',callback_ok=function(){
        ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
          ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
          });
        });
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

    });
</script>
    
<div class="col-md-12">
    <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
</div>

<h2>{{ lang._('Neighbors') }}</h2>
<div class="tab-content content-box tab-content">
<div id="neighbors" class="tab-pane fade in active">

<table id="grid-neighbors" class="table table-responsive" data-editDialog="DialogEditBGPNeighbor">
<thead>
            <tr>
                <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="address" data-type="string" data-visible="true">{{ lang._('Neighbor Address') }}</th>
                <th data-column-id="remoteas" data-type="string" data-visible="true">{{ lang._('Remote AS') }}</th>
                <th data-column-id="updatesource" data-type="string" data-visible="true">{{ lang._('Update Source Address') }}</th>
                <th data-column-id="nexthopself" data-type="string" data-formatter="rowtoggle">{{ lang._('Next Hop Self') }}</th>
                <th data-column-id="defaultoriginate" data-type="string" data-formatter="rowtoggle">{{ lang._('Default Originate') }}</th>
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
                </td>
            </tr>
</tfoot>
</table>

</div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBGPNeighbor,'id':'DialogEditBGPNeighbor','label':'Edit Neighbor'])}}
