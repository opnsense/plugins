{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
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
<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#users">{{ lang._('Users') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="users" class="tab-pane fade in">
        <table id="grid-users" class="table table-responsive" data-editDialog="DialogEditFreeRADIUSUser">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="username" data-type="string" data-visible="true">{{ lang._('Username') }}</th>
                    <th data-column-id="password" data-type="string" data-visible="true">{{ lang._('Password') }}</th>
                    <th data-column-id="description" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="ip" data-type="string" data-visible="true">{{ lang._('IP Address') }}</th>
                    <th data-column-id="subnet" data-type="string" data-visible="true">{{ lang._('Subnet') }}</th>
                    <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway Address') }}</th>
                    <th data-column-id="vlan" data-type="string" data-visible="true">{{ lang._('VLAN ID') }}</th>
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
$(document).ready(function() {
  var data_get_map = {'frm_bgp_settings':"/api/freeradius/user/get"};
  mapDataToFormUI(data_get_map).done(function(data){
      formatTokenizersUI();
      $('.selectpicker').selectpicker('refresh');
  });
  ajaxCall(url="/api/freeradius/service/status", sendData={}, callback=function(data,status) {
      updateServiceStatusUI(data['status']);
  });

  // link save button to API set action
  $("#saveAct").click(function(){
      saveFormToEndpoint(url="/api/freeradius/user/set",formid='frm_bgp_settings',callback_ok=function(){
        ajaxCall(url="/api/freeradius/service/reconfigure", sendData={}, callback=function(data,status) {
          ajaxCall(url="/api/freeradius/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
          });
        });
      });
  });
  $("#grid-users").UIBootgrid(
    { 'search':'/api/freeradius/user/searchUser',
      'get':'/api/freeradius/user/getUser/',
      'set':'/api/freeradius/user/setUser/',
      'add':'/api/freeradius/user/addUser/',
      'del':'/api/freeradius/user/delUser/',
      'toggle':'/api/freeradius/user/toggleUser/',
      'options':{selection:false, multiSelect:false}
    }
  );
    });
</script>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditFreeRADIUSUser,'id':'DialogEditFreeRADIUSUser','label':lang._('Edit User')])}}
