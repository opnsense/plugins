{#

Copyright © 2018 by EURO-LOG AG
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

<script>

   $( document ).ready(function() {

	   $('#btnConfigTest').unbind('click').click(function(){
		   $('#btnConfigTestProgress').addClass("fa fa-spinner fa-pulse");
	      ajaxCall(url="/api/relayd/service/configtest", sendData={}, callback=function(data,status) {
		   $('#btnConfigTestProgress').removeClass("fa fa-spinner fa-pulse");
	         $('#btnConfigTest').blur();
	         $("#responseMsg").removeClass("hidden");
	         $("#responseMsg").html(data['result']);
	      });
	   });

	   $('#btnReload').unbind('click').click(function(){
	      $('#btnReloadProgress').addClass("fa fa-spinner fa-pulse");
	      ajaxCall(url="/api/relayd/service/reconfigure", sendData={}, callback=function(data,status) {
	         $('#btnReloadProgress').removeClass("fa fa-spinner fa-pulse");
	         $('#btnReload').blur();
	         $("#responseMsg").removeClass("hidden");
	         $("#responseMsg").html(data['result']);
                 updateServiceControlUI('relayd');
	      });
	   });
	   /**
	     * general settings
	     */
	   mapDataToFormUI({'frm_GeneralSettings':"/api/relayd/settings/get/general/"}).done(function(){
		   formatTokenizersUI();
	      $('#relayd\\.general\\.log').selectpicker('refresh');
              updateServiceControlUI('relayd');
	   });
	   $('#btn_ApplyGeneralSettings').unbind('click').click(function(){
		   $("#frm_GeneralSettings_progress").addClass("fa fa-spinner fa-pulse");
	      var frm_id = 'frm_GeneralSettings';
	      saveFormToEndpoint(url = "/api/relayd/settings/set/general/",formid=frm_id,callback_ok=function(){
                  updateServiceControlUI('relayd');
	      });
	      $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");
	      $("#btn_ApplyGeneralSettings").blur();
	   });

	   $("#grid-host").UIBootgrid({
	         'search': '/api/relayd/settings/search/host/',
	         'get':    '/api/relayd/settings/get/host/',
	         'set':    '/api/relayd/settings/set/host/',
	         'add':    '/api/relayd/settings/set/host/',
	         'del':    '/api/relayd/settings/del/host/'
	      });
	   $("#grid-tablecheck").UIBootgrid({
           'search': '/api/relayd/settings/search/tablecheck/',
           'get':    '/api/relayd/settings/get/tablecheck/',
           'set':    '/api/relayd/settings/set/tablecheck/',
           'add':    '/api/relayd/settings/set/tablecheck/',
           'del':    '/api/relayd/settings/del/tablecheck/'
        });
	   $("#grid-table").UIBootgrid({
           'search': '/api/relayd/settings/search/table/',
           'get':    '/api/relayd/settings/get/table/',
           'set':    '/api/relayd/settings/set/table/',
           'add':    '/api/relayd/settings/set/table/',
           'del':    '/api/relayd/settings/del/table/'
        });
	   $("#grid-protocol").UIBootgrid({
           'search': '/api/relayd/settings/search/protocol/',
           'get':    '/api/relayd/settings/get/protocol/',
           'set':    '/api/relayd/settings/set/protocol/',
           'add':    '/api/relayd/settings/set/protocol/',
           'del':    '/api/relayd/settings/del/protocol/'
        });
	   $("#grid-virtualserver").UIBootgrid({
           'search': '/api/relayd/settings/search/virtualserver/',
           'get':    '/api/relayd/settings/get/virtualserver/',
           'set':    '/api/relayd/settings/set/virtualserver/',
           'add':    '/api/relayd/settings/set/virtualserver/',
           'del':    '/api/relayd/settings/del/virtualserver/'
        });

	   // show/hide options depending on other options
	   function ShowHideVSFields(){
		   var servertype = $('#relayd\\.virtualserver\\.type').val();
		   var backuptransport_table = $('#relayd\\.virtualserver\\.backuptransport_table').val();

		   $('tr[id="row_relayd.virtualserver.transport_type"]').addClass('hidden');
		   $('tr[id="row_relayd.virtualserver.stickyaddress"]').addClass('hidden');
		   $('tr[id="row_relayd.virtualserver.protocol"]').addClass('hidden');
		   $('tr[id="row_relayd.virtualserver.backuptransport_tablemode"]').addClass('hidden');
		   $('tr[id="row_relayd.virtualserver.backuptransport_timeout"]').addClass('hidden');
		   $('tr[id="row_relayd.virtualserver.backuptransport_interval"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.backuptransport_tablecheck"]').addClass('hidden');
		   $('#relayd\\.virtualserver\\.transport_tablemode').empty().append('<option value="roundrobin">Round Robin </option>');
		   $('#relayd\\.virtualserver\\.backuptransport_tablemode').empty().append('<option value="roundrobin">Round Robin </option>');

		   if(servertype == 'redirect'){
			   $('tr[id="row_relayd.virtualserver.transport_type"]').removeClass('hidden');
			   $('tr[id="row_relayd.virtualserver.stickyaddress"]').removeClass('hidden');
			   $('#relayd\\.virtualserver\\.transport_tablemode').append('<option value="least-states">Least States </option>');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').append('<option value="least-states">Least States </option>');
			   $('#relayd\\.virtualserver\\.transport_tablemode').val('roundrobin');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').val('roundrobin');
		   } else {
			   $('tr[id="row_relayd.virtualserver.protocol"]').removeClass('hidden');
			   $('#relayd\\.virtualserver\\.transport_tablemode').append('<option value="hash">Hash </option>');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').append('<option value="hash">Hash </option>');
			   $('#relayd\\.virtualserver\\.transport_tablemode').append('<option value="loadbalance">Load Balance </option>');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').append('<option value="loadbalance">Load Balance </option>');
			   $('#relayd\\.virtualserver\\.transport_tablemode').append('<option value="random">Random </option>');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').append('<option value="random">Random </option>');
			   $('#relayd\\.virtualserver\\.transport_tablemode').append('<option value="source-hash">Source Hash </option>');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').append('<option value="source-hash">Source Hash </option>');
			   $('#relayd\\.virtualserver\\.transport_tablemode').val('roundrobin');
			   $('#relayd\\.virtualserver\\.backuptransport_tablemode').val('roundrobin');
		   }

		   $('#relayd\\.virtualserver\\.transport_tablemode').selectpicker('refresh');
		   $('#relayd\\.virtualserver\\.backuptransport_tablemode').selectpicker('refresh');

		   if(backuptransport_table !== '') {
			   $('tr[id="row_relayd.virtualserver.backuptransport_tablemode"]').removeClass('hidden');
			   $('tr[id="row_relayd.virtualserver.backuptransport_tablecheck"]').removeClass('hidden');
			   $('tr[id="row_relayd.virtualserver.backuptransport_timeout"]').removeClass('hidden');
			   $('tr[id="row_relayd.virtualserver.backuptransport_interval"]').removeClass('hidden');
		   }
	   };
	   $('#DialogEditVirtualServer').on('shown.bs.modal', function() {ShowHideVSFields();});
	   $('#relayd\\.virtualserver\\.type').on('changed.bs.select', function(e) {ShowHideVSFields();});
	   $('#relayd\\.virtualserver\\.backuptransport_table').on('changed.bs.select', function(e) {ShowHideVSFields();});

	   function ShowHideTCFields(){
		   var tablechecktype = $('#relayd\\.tablecheck\\.type').val();

		   $('tr[id="row_relayd.tablecheck.path"]').addClass('hidden');
		   $('tr[id="row_relayd.tablecheck.host"]').addClass('hidden');
		   $('tr[id="row_relayd.tablecheck.code"]').addClass('hidden');
		   $('tr[id="row_relayd.tablecheck.digest"]').addClass('hidden');
		   $('tr[id="row_relayd.tablecheck.data"]').addClass('hidden');
		   $('tr[id="row_relayd.tablecheck.expect"]').addClass('hidden');
		   $('tr[id="row_relayd.tablecheck.ssl"]').addClass('hidden');

		   switch (tablechecktype) {
		      case 'send':
			   $('tr[id="row_relayd.tablecheck.data"]').removeClass('hidden');
			   $('tr[id="row_relayd.tablecheck.expect"]').removeClass('hidden');
			   $('tr[id="row_relayd.tablecheck.ssl"]').removeClass('hidden');
			      break;
		      case 'script':
			   $('tr[id="row_relayd.tablecheck.path"]').removeClass('hidden');
			   break;
		      case 'http':
			   var code = $('#relayd\\.tablecheck\\.code').val();
			   var digest = $('#relayd\\.tablecheck\\.digest').val();
			   $('tr[id="row_relayd.tablecheck.path"]').removeClass('hidden');
			   $('tr[id="row_relayd.tablecheck.host"]').removeClass('hidden');
			   if (code !== '') {
				   $('tr[id="row_relayd.tablecheck.code"]').removeClass('hidden');
				   $('tr[id="row_relayd.tablecheck.digest"]').addClass('hidden');
			   } else if (digest !== '') {
				   $('tr[id="row_relayd.tablecheck.code"]').addClass('hidden');
		            $('tr[id="row_relayd.tablecheck.digest"]').removeClass('hidden');
			   } else {
				   $('tr[id="row_relayd.tablecheck.code"]').removeClass('hidden');
		            $('tr[id="row_relayd.tablecheck.digest"]').removeClass('hidden');
			   }
			   $('tr[id="row_relayd.tablecheck.ssl"]').removeClass('hidden');
			   break;
		   }
	   };

	   $('#DialogEditTableCheck').on('shown.bs.modal', function() {ShowHideTCFields();});
	   $('#relayd\\.tablecheck\\.type').on('changed.bs.select', function(e) {ShowHideTCFields();});
	   $('#relayd\\.tablecheck\\.code').on('input', function() {ShowHideTCFields();});
	   $('#relayd\\.tablecheck\\.digest').on('input', function() {ShowHideTCFields();});
   });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>
<ul class="nav nav-tabs" role="tablist" id="maintabs">
   <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General Settings') }}</a></li>
   <li><a data-toggle="tab" href="#host">{{ lang._('Backend Hosts') }}</a></li>
   <li><a data-toggle="tab" href="#tablecheck">{{ lang._('Table Checks') }}</a></li>
   <li><a data-toggle="tab" href="#table">{{ lang._('Tables') }}</a></li>
   <li><a data-toggle="tab" href="#protocol">{{ lang._('Protocols') }}</a></li>
   <li><a data-toggle="tab" href="#virtualserver">{{ lang._('Virtual Server') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
   <div id="general" class="tab-pane fade in active">
      {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings','apply_btn_id':'btn_ApplyGeneralSettings'])}}
   </div>
   <div id="host" class="tab-pane fade in">
      <table id="grid-host" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditHost">
         <thead>
            <tr>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="address" data-type="string">{{ lang._('Address') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
         </thead>
         <tbody>
         </tbody>
         <tfoot>
            <tr>
               <td></td>
               <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
               </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div id="tablecheck" class="tab-pane fade in">
      <table id="grid-tablecheck" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditTableCheck">
         <thead>
            <tr>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
         </thead>
         <tbody>
         </tbody>
         <tfoot>
            <tr>
               <td></td>
               <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
               </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div id="table" class="tab-pane fade in">
      <table id="grid-table" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditTable">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="boolean">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
         </thead>
         <tbody>
         </tbody>
         <tfoot>
            <tr>
               <td></td>
               <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
               </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div id="protocol" class="tab-pane fade in">
      <table id="grid-protocol" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditProtocol">
         <thead>
            <tr>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
         </thead>
         <tbody>
         </tbody>
         <tfoot>
            <tr>
               <td></td>
               <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
               </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div id="virtualserver" class="tab-pane fade in">
      <table id="grid-virtualserver" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditVirtualServer">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="boolean">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
         </thead>
         <tbody>
         </tbody>
         <tfoot>
            <tr>
               <td></td>
               <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
               </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div class="col-md-12">
      <hr/>
      <button class="btn btn-primary" id="btnConfigTest" type="button"><b>{{ lang._('Test Configuration') }}</b><i id="btnConfigTestProgress" class=""></i></button>
      <button class="btn btn-primary" id="btnReload" type="button"><b>{{ lang._('Reload Configuration') }}</b><i id="btnReloadProgress" class=""></i></button>
      <br/>
      <br/>
   </div>
</div>
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditHost,         'id':'DialogEditHost',          'label':'Edit Host'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTableCheck,   'id':'DialogEditTableCheck',    'label':'Edit Table Check'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTable,        'id':'DialogEditTable',         'label':'Edit Table'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditProtocol,     'id':'DialogEditProtocol',      'label':'Edit Protocol'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditVirtualServer,'id':'DialogEditVirtualServer', 'label':'Edit Virtual Server'])}}
