{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
Copyright (C) 2017 Fabian Franz
Copyright (C) 2017 - 2021 Michael Muenz <m.muenz@gmail.com>
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
</ul>
<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':bfdForm,'id':'frm_bfd_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="neighbors" class="tab-pane fade in">
        <table id="grid-neighbors" class="table table-responsive" data-editDialog="DialogEditBFDNeighbor">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="address" data-type="string" data-visible="true">{{ lang._('Neighbor Address') }}</th>
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
                        <button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span></button>
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
  var data_get_map = {'frm_bfd_settings':"/api/quagga/bfd/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });
  quagga_update_status();

  // link save button to API set action
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/quagga/bfd/set",formid='frm_bfd_settings',callback_ok=function(){
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
    { 'search':'/api/quagga/bfd/searchNeighbor',
      'get':'/api/quagga/bfd/getNeighbor/',
      'set':'/api/quagga/bfd/setNeighbor/',
      'add':'/api/quagga/bfd/addNeighbor/',
      'del':'/api/quagga/bfd/delNeighbor/',
      'toggle':'/api/quagga/bfd/toggleNeighbor/',
      'options':{selection:false, multiSelect:false}
    }
  );
});
</script>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBFDNeighbor,'id':'DialogEditBFDNeighbor','label':lang._('Edit Neighbor')])}}
