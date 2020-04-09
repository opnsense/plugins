{#

  OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
  This file is Copyright © 2017 by Fabian Franz
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
          <li><a data-toggle="tab" href="#ipv4">{{ lang._('IPv4') }}</a></li>
          <li><a data-toggle="tab" href="#ipv6">{{ lang._('IPv6') }}</a></li>
      </ul>
      <div class="tab-content content-box tab-content">
          <div id="general" class="tab-pane fade in active">
              <div class="content-box" style="padding-bottom: 1.5em;">
                  {{ partial("layout_partials/base_form",['fields':staticdForm,'id':'frm_staticd_settings'])}}
                  <div class="col-md-12">
                      <hr />
                      <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
                  </div>
              </div>
          </div>
  
      <!-- Tab: IPv4 -->
      <div id="ipv4" class="tab-pane fade in">
        <table id="grid-ipv4" class="table table-responsive" data-editDialog="DialogEditv4">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="ipaddr" data-type="string" data-visible="true">{{ lang._('Network') }}</th>
                    <th data-column-id="netmask" data-type="string" data-visible="true">{{ lang._('Mask') }}</th>
                    <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway') }}</th>
                    <th data-column-id="interfacename" data-type="string" data-visible="true">{{ lang._('Interface Name') }}</th>
                    <th data-column-id="distance" data-type="string" data-visible="true">{{ lang._('Distance') }}</th>                    
                    <th data-column-id="blackhole" data-type="string" data-visible="true">{{ lang._('Blackhole') }}</th>                                        
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

      <!-- Tab: IPv6 -->
      <div id="ipv6" class="tab-pane fade in">
        <table id="grid-ipv6" class="table table-responsive" data-editDialog="DialogEditv6">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="ipaddr" data-type="string" data-visible="true">{{ lang._('Network') }}</th>
                    <th data-column-id="netmask" data-type="string" data-visible="true">{{ lang._('Mask') }}</th>
                    <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway') }}</th>
                    <th data-column-id="interfacename" data-type="string" data-visible="true">{{ lang._('Interface Name') }}</th>
                    <th data-column-id="distance" data-type="string" data-visible="true">{{ lang._('Distance') }}</th>                    
                    <th data-column-id="blackhole" data-type="string" data-visible="true">{{ lang._('Blackhole') }}</th>                                        
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
  
  <script>
  
  function quagga_update_status() {
    updateServiceControlUI('quagga');
  }
  
  $( document ).ready(function() {
    var data_get_map = {'frm_staticd_settings':"/api/quagga/staticdsettings/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });
    quagga_update_status();
  
    // link save button to API set action
    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/quagga/staticdsettings/set",formid='frm_staticd_settings',callback_ok=function(){
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
  
  
    $("#grid-ipv4").UIBootgrid(
      { 'search':'/api/quagga/staticdsettings/searchStaticdv4',
        'get':'/api/quagga/staticdsettings/getStaticdv4/',
        'set':'/api/quagga/staticdsettings/setStaticdv4/',
        'add':'/api/quagga/staticdsettings/addStaticdv4/',
        'del':'/api/quagga/staticdsettings/delStaticdv4/',
        'toggle':'/api/quagga/staticdsettings/toggleStaticdv4/',
        'options':{selection:false, multiSelect:false}
      }
    );
    $("#grid-ipv6").UIBootgrid(
      { 'search':'/api/quagga/staticdsettings/searchStaticdv6',
        'get':'/api/quagga/staticdsettings/getStaticdv6/',
        'set':'/api/quagga/staticdsettings/setStaticdv6/',
        'add':'/api/quagga/staticdsettings/addStaticdv6/',
        'del':'/api/quagga/staticdsettings/delStaticdv6/',
        'toggle':'/api/quagga/staticdsettings/toggleStaticdv6/',
        'options':{selection:false, multiSelect:false}
      }
    );
  });
  </script>
  
{{ partial("layout_partials/base_dialog",['fields':formDialogEditv4,'id':'DialogEditv4','label':lang._('Edit IPv4')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditv6,'id':'DialogEditv6','label':lang._('Edit IPv6')])}}
  