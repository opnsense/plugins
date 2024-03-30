{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2024 by Mike Shuey
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
    <li><a data-toggle="tab" href="#iproute">{{ lang._('IP Routes') }}</a></li>
    <li><a data-toggle="tab" href="#ip6route">{{ lang._('IPv6 Routes') }}</a></li>
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

    <!-- Tab: IP Routes -->
    <div id="iproute" class="tab-pane fade in">
	<table id="grid-iproutes" class="table table-responsive" data-editDialog="DialogEditStaticdRoute">
	    <thead>
		<tr>
		    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
		    <th data-column-id="iproute" data-type="string" data-visible="true">{{ lang._('IP Route') }}</th>
		    <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway (optional)') }}</th>
		    <th data-column-id="interfacename" data-type="string" data-visible="true">{{ lang._('Interface') }}</th>
		    <th data-column-id="commands" data-formatter="commands" data-portable="false">{{ lang._('Commands') }}</th>
		</tr>
	    </thead>
	    <tbody>
	    </tbody>
	    <tfoot>
		<tr>
		    <td colspan="3"></td>
		    <td>
			<button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
			<!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
			<button type="button" class="btn btn-xs reload_btn btn-primary"><span class="fa fa-refresh reloadAct_progress"></span> {{ lang._('Reload Service') }}</button>
		    </td>
		</tr>
	    </tfoot>
	</table>
    </div>

    <!-- Tab: IPv6 Routes -->
    <div id="ip6route" class="tab-pane fade in">
	<table id="grid-ip6routes" class="table table-responsive" data-editDialog="DialogEditStaticdRoute6">
	    <thead>
		<tr>
		    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
		    <th data-column-id="ip6route" data-type="string" data-visible="true">{{ lang._('IPv6 Route') }}</th>
		    <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway (optional)') }}</th>
		    <th data-column-id="interfacename" data-type="string" data-visible="true">{{ lang._('Interface') }}</th>
		    <th data-column-id="commands" data-formatter="commands" data-portable="false">{{ lang._('Commands') }}</th>
		</tr>
	    </thead>
	    <tbody>
	    </tbody>
	    <tfoot>
		<tr>
		    <td colspan="3"></td>
		    <td>
			<button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
			<!-- <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button> -->
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
  var data_get_map = {'frm_staticd_settings':"/api/quagga/staticd/get"};
  mapDataToFormUI(data_get_map).done(function(data){
    formatTokenizersUI();
    $('.selectpicker').selectpicker('refresh');
  });

  quagga_update_status();

  // link save button to API set action
  $("#saveAct").click(function(){
    saveFormToEndpoint(url="/api/quagga/staticd/set",formid='frm_staticd_settings',callback_ok=function(){
      $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
      ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function(data,status) {
	updateServiceControlUI('quagga');
	$("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
      });
    });
  });

  /* allow a user to manually reload the service (for forms which do not do it automatically) */
  $('.reload_btn').click(function reload_handler() {
    $(".reloadAct_progress").addClass("fa-spin");
    ajaxCall(url="/api/quagga/service/reconfigure", sendData={}, callback=function (data,status) {
      quagga_update_status();
      $(".reloadAct_progress").removeClass("fa-spin"); 
    });
  });

  $("#grid-iproutes").UIBootgrid(
    { 'search':'/api/quagga/staticd/searchRoute',
      'get':'/api/quagga/staticd/getRoute/',
      'set':'/api/quagga/staticd/setRoute/',
      'add':'/api/quagga/staticd/addRoute/',
      'del':'/api/quagga/staticd/delRoute/',
      'toggle':'/api/quagga/staticd/toggleRoute/',
      'options':{selection:false, multiSelect:false}
    }
  );

  $("#grid-ip6routes").UIBootgrid(
    { 'search':'/api/quagga/staticd/searchRoute6',
      'get':'/api/quagga/staticd/getRoute6/',
      'set':'/api/quagga/staticd/setRoute6/',
      'add':'/api/quagga/staticd/addRoute6/',
      'del':'/api/quagga/staticd/delRoute6/',
      'toggle':'/api/quagga/staticd/toggleRoute6/',
      'options':{selection:false, multiSelect:false}
    }
  );
});
</script>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditStaticdRoute,'id':'DialogEditStaticdRoute','label':lang._('Edit IP Routes')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditStaticdRoute6,'id':'DialogEditStaticdRoute6','label':lang._('Edit IPv6 Routes')])}}
