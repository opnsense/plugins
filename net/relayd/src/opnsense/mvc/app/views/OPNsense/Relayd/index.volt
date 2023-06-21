{#

Copyright © 2018 by EURO-LOG AG
Copyright (c) 2021 Deciso B.V.
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
      /**
       * get the isSubsystemDirty value and print a notice
       */
      function isSubsystemDirty() {
         ajaxGet(url="/api/relayd/settings/dirty", sendData={}, callback=function(data,status) {
            if (status == "success") {
               if (data.relayd.dirty === true) {
                  $("#configChangedMsg").removeClass("hidden");
               } else {
                  $("#configChangedMsg").addClass("hidden");
               }
            }
         });
      }

      /**
       * chain std_bootgrid_reload from opnsense_bootgrid_plugin.js
       * to get the isSubsystemDirty state on "UIBootgrid" changes
       */
      var opn_std_bootgrid_reload = std_bootgrid_reload;
      std_bootgrid_reload = function(gridId) {
         opn_std_bootgrid_reload(gridId);
         isSubsystemDirty();
      };

      /**
       * apply changes and reload relayd
       */
      $('#btnApplyConfig').unbind('click').click(function(){
         $('#btnApplyConfigProgress').addClass("fa fa-spinner fa-pulse");
         ajaxCall(url="/api/relayd/service/reconfigure", sendData={}, callback=function(data,status) {
            $("#responseMsg").addClass("hidden");
            isSubsystemDirty();
            updateServiceControlUI('relayd');
            if (data.result) {
               $("#responseMsg").html(data['result']);
               $("#responseMsg").removeClass("hidden");
            }
            $('#btnApplyConfigProgress').removeClass("fa fa-spinner fa-pulse");
            $('#btnApplyConfig').blur();
         });
      });

      /**
       * general settings
       */
      mapDataToFormUI({'frm_GeneralSettings':"/api/relayd/settings/get/general/"}).done(function() {
         $("#responseMsg").addClass("hidden");
         formatTokenizersUI();
         $('#relayd\\.general\\.log').selectpicker('refresh');
         updateServiceControlUI('relayd');
         isSubsystemDirty();
      });
      $('#btnSaveGeneral').unbind('click').click(function() {
         $("#btnSaveGeneralProgress").addClass("fa fa-spinner fa-pulse");
         var frm_id = 'frm_GeneralSettings';
         saveFormToEndpoint(url = "/api/relayd/settings/set/general/",formid=frm_id,callback_ok=function() {
            $("#responseMsg").addClass("hidden");
            updateServiceControlUI('relayd');
            isSubsystemDirty();
            $("#btnSaveGeneralProgress").removeClass("fa fa-spinner fa-pulse");
            $("#btnSaveGeneral").blur();
         });
      });

      ['host', 'tablecheck', 'table', 'protocol', 'virtualserver'].forEach(function(element) {
         let endpoints = {
            'search': '/api/relayd/settings/search/' + element + '/',
            'get':    '/api/relayd/settings/get/' + element + '/',
            'set':    '/api/relayd/settings/set/' + element + '/',
            'add':    '/api/relayd/settings/set/' + element + '/',
            'del':    '/api/relayd/settings/del/' + element + '/',
            options: {
                formatters: {
                    'listen_port': function (column, row) {
                        if (row.listen_endport) {
                            return row.listen_startport + ":" + row.listen_endport;
                        } else {
                            return row.listen_startport;
                        }
                    },
                    'commands': function (column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                            '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                            '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                    },
                    'rowtoggle': function (column, row) {
                        if (parseInt(row[column.id], 2) === 1) {
                            return '<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o command-toggle bootgrid-tooltip" data-value="1" data-row-id="' + row.uuid + '"></span>';
                        } else {
                            return '<span style="cursor: pointer;" class="fa fa-fw fa-square-o command-toggle bootgrid-tooltip" data-value="0" data-row-id="' + row.uuid + '"></span>';
                        }
                    },
                }
            }
         };
         if (['virtualserver', 'host', 'table'].includes(element)) {
            endpoints['toggle'] = '/api/relayd/settings/toggle/' + element + '/';
         }
         $("#grid-" + element).UIBootgrid(endpoints);
      });

      // show/hide options depending on other options
      function ShowHideVSFields(){
         var servertype = $('#relayd\\.virtualserver\\.type').val();
         var transport_type = $('#relayd\\.virtualserver\\.transport_type').val();
         var backuptransport_table = $('#relayd\\.virtualserver\\.backuptransport_table').val();
         var transport_tablemode = $('#relayd\\.virtualserver\\.transport_tablemode').val();
         var backuptransport_tablemode = $('#relayd\\.virtualserver\\.backuptransport_tablemode').val();

         $('tr[id="row_relayd.virtualserver.listen_proto"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.transport_type"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.routing_interface"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.stickyaddress"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.protocol"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.backuptransport_tablemode"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.backuptransport_timeout"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.backuptransport_interval"]').addClass('hidden');
         $('tr[id="row_relayd.virtualserver.backuptransport_tablecheck"]').addClass('hidden');
         $('#relayd\\.virtualserver\\.transport_tablemode').empty().append('<option value="roundrobin">Round Robin </option>');
         $('#relayd\\.virtualserver\\.backuptransport_tablemode').empty().append('<option value="roundrobin">Round Robin </option>');

         if(servertype == 'redirect'){
            $('tr[id="row_relayd.virtualserver.listen_proto"]').removeClass('hidden');
            $('tr[id="row_relayd.virtualserver.transport_type"]').removeClass('hidden');
            if(transport_type == 'route'){
               $('tr[id="row_relayd.virtualserver.routing_interface"]').removeClass('hidden');
            }
            $('tr[id="row_relayd.virtualserver.stickyaddress"]').removeClass('hidden');
            $('#relayd\\.virtualserver\\.transport_tablemode').append('<option value="least-states">Least States </option>');
            $('#relayd\\.virtualserver\\.backuptransport_tablemode').append('<option value="least-states">Least States </option>');
            $('#relayd\\.virtualserver\\.transport_tablemode').val(transport_tablemode);
            $('#relayd\\.virtualserver\\.backuptransport_tablemode').val(backuptransport_tablemode);
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
            $('#relayd\\.virtualserver\\.transport_tablemode').val(transport_tablemode);
            $('#relayd\\.virtualserver\\.backuptransport_tablemode').val(backuptransport_tablemode);
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
      $('#relayd\\.virtualserver\\.transport_type').on('changed.bs.select', function(e) {ShowHideVSFields();});
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

<div class="alert alert-info hidden" role="alert" id="configChangedMsg">
   <button class="btn btn-primary pull-right" id="btnApplyConfig" type="button"><b>{{ lang._('Apply changes') }}</b> <i id="btnApplyConfigProgress"></i></button>
   {{ lang._('The Relayd configuration has been changed') }} <br /> {{ lang._('You must apply the changes in order for them to take effect.')}}
</div>
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
      {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings'])}}
      <div class="table-responsive">
         <table class="table table-striped table-condensed table-responsive">
            <tr>
               <td>
                  <button class="btn btn-primary" id="btnSaveGeneral" type="button"><b>{{ lang._('Save') }}</b> <i id="btnSaveGeneralProgress"></i></button>
               </td>
            </tr>
         </table>
      </div>
   </div>
   <div id="host" class="tab-pane fade in">
      <table id="grid-host" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditHost">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
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
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
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
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="listen_address" data-type="string">{{ lang._('Adress') }}</th>
                <th data-column-id="listen_startport" data-formatter="listen_port" data-type="string">{{ lang._('Port') }}</th>
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
   <!-- <div class="col-md-12">
      <hr/>
      <button class="btn btn-primary" id="btnApplyConfig" type="button"><b>{{ lang._('Apply Configuration') }}</b> <i id="btnApplyConfigProgress"></i></button>
      <br/>
      <br/>
   </div>
   -->
</div>
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditHost,         'id':'DialogEditHost',          'label':'Edit Host'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTableCheck,   'id':'DialogEditTableCheck',    'label':'Edit Table Check'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTable,        'id':'DialogEditTable',         'label':'Edit Table'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditProtocol,     'id':'DialogEditProtocol',      'label':'Edit Protocol'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditVirtualServer,'id':'DialogEditVirtualServer', 'label':'Edit Virtual Server'])}}
