{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Fabian Franz
This file is Copyright © 2017 by Michael Muenz
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
        <li><a data-toggle="tab" href="#interfaces">{{ lang._('Interfaces') }}</a></li>
    </ul>
    <div class="tab-content content-box tab-content">
        <div id="general" class="tab-pane fade in active">
            <div class="content-box" style="padding-bottom: 1.5em;">
                {{ partial("layout_partials/base_form",['fields':ospf6Form,'id':'frm_ospf6_settings'])}}
                <div class="col-md-12">
                    <hr />
                    <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
                </div>
            </div>
        </div>

    <!-- Tab: Interfaces -->
    <div id="interfaces" class="tab-pane fade in">
        <table id="grid-interfaces" class="table table-responsive" data-editDialog="DialogEditInterface">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="interfacename" data-type="string" data-visible="true">{{ lang._('Interface Name') }}</th>
                    <th data-column-id="area" data-type="string" data-visible="true">{{ lang._('Area') }}</th>
                    <th data-column-id="networktype" data-type="string" data-visible="true">{{ lang._('Network Type') }}</th>
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

<script type="text/javascript">
$( document ).ready(function() {
  var data_get_map = {'frm_ospf6_settings':"/api/quagga/ospf6settings/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });

  ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status']);
  });

  // link save button to API set action
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/quagga/ospf6settings/set",formid='frm_ospf6_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
          ajaxCall(url="/api/quagga/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
          });
          $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
        });
      });
  });

  $("#grid-interfaces").UIBootgrid(
    { 'search':'/api/quagga/ospf6settings/searchInterface',
      'get':'/api/quagga/ospf6settings/getInterface/',
      'set':'/api/quagga/ospf6settings/setInterface/',
      'add':'/api/quagga/ospf6settings/addInterface/',
      'del':'/api/quagga/ospf6settings/delInterface/',
      'toggle':'/api/quagga/ospf6settings/toggleInterface/',
      'options':{selection:false, multiSelect:false}
    }
  );
});
</script>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditInterface,'id':'DialogEditInterface','label':lang._('Edit Interface')])}}
