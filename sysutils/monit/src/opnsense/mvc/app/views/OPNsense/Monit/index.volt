{#

Copyright © 2017 by EURO-LOG AG
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
<script type="text/javascript">

   $( document ).ready(function() {

      /**
      * UI functions
      */

      $('#btn_configtest').unbind('click').click(function(){
         $('#btn_configtest_progress').addClass("fa fa-spinner fa-pulse");
         ajaxCall(url="/api/monit/service/configtest", sendData={}, callback=function(data,status) {
            $('#btn_configtest_progress').removeClass("fa fa-spinner fa-pulse");
            $('#btn_configtest').blur();
            $("#responseMsg").removeClass("hidden");
            $("#responseMsg").html(data['result']);
         });
      });

      $('#btn_reload').unbind('click').click(function(){
         $('#btn_reload_progress').addClass("fa fa-spinner fa-pulse");
         ajaxCall(url="/api/monit/service/reload", sendData={}, callback=function(data,status) {
            $('#btn_reload_progress').removeClass("fa fa-spinner fa-pulse");
            $('#btn_reload').blur();
            $("#responseMsg").removeClass("hidden");
            $("#responseMsg").html(data['result']);
            ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
               updateServiceStatusUI(data['status']);
            });
         });
      });


      /**
       * general settings
       */
      // load data
      mapDataToFormUI({'frm_GeneralSettings':"/api/monit/settings/getGeneral"}).done(function(){
         formatTokenizersUI();
         $('.selectpicker').selectpicker('refresh');
         ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
         });
      });

      // show/hide httpd/mmonit options
      function ShowHideGeneralFields(){
         if ($('#monit\\.general\\.httpdEnabled')[0].checked) {
            $('tr[for="monit.general.httpdPort"]').removeClass('hidden');
            $('tr[for="monit.general.httpdAllow"]').removeClass('hidden');
            $('tr[for="monit.general.mmonitUrl"]').removeClass('hidden');
            $('tr[for="monit.general.mmonitTimeout"]').removeClass('hidden');
            $('tr[for="monit.general.mmonitRegisterCredentials"]').removeClass('hidden');
         } else {
            $('tr[for="monit.general.httpdPort"]').addClass('hidden');
            $('tr[for="monit.general.httpdAllow"]').addClass('hidden');
            $('tr[for="monit.general.mmonitUrl"]').addClass('hidden');
            $('tr[for="monit.general.mmonitTimeout"]').addClass('hidden');
            $('tr[for="monit.general.mmonitRegisterCredentials"]').addClass('hidden');
         }
      };
      $('#monit\\.general\\.httpdEnabled').unbind('click').click(function(){
         ShowHideGeneralFields();
      });
      $('#show_advanced_frm_GeneralSettings').click(function(){
         ShowHideGeneralFields();
      });

      $('#btn_ApplyGeneralSettings').unbind('click').click(function(){
         $("#frm_GeneralSettings_progress").addClass("fa fa-spinner fa-pulse");
         var frm_id = 'frm_GeneralSettings';
         saveFormToEndpoint(url = "/api/monit/settings/setGeneral",formid=frm_id,callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                //
                ajaxCall(url="/api/monit/service/restart", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");

                    if (status != "success" || data['result'] != 'OK' ) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: 'Error',
                            message: JSON.stringify(data),
                            draggable: true
                        });
                    } else {
                        ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
                            updateServiceStatusUI(data['status']);
                        });
                    }
                });
            }
         );
         $("#btn_ApplyGeneralSettings").blur();
      });

      /**
       * alert settings
       */
      function openAlertDialog(uuid) {
         var editDlg = "DialogEditAlert";
         var setUrl = "/api/monit/settings/setAlert/";
         var getUrl = "/api/monit/settings/getAlert/";
         var urlMap = {};
         urlMap['frm_' + editDlg] = getUrl + uuid;
         mapDataToFormUI(urlMap).done(function () {
            $('.selectpicker').selectpicker('refresh');
            clearFormValidation('frm_' + editDlg);
            $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
            $('#'+editDlg).on('hidden.bs.modal', function () {
               parent.history.back();
            });
         });
      };

      $("#grid-alerts").UIBootgrid({
         'search':'/api/monit/settings/searchAlert',
         'get':'/api/monit/settings/getAlert/',
         'set':'/api/monit/settings/setAlert/',
         'add':'/api/monit/settings/addAlert/',
         'del':'/api/monit/settings/delAlert/',
         'toggle':'/api/monit/settings/toggleAlert/'
      });

      /**
       * service settings
       */

      // show hide fields according to selected service type
      function ShowHideFields(){
         var servicetype = $('#monit\\.service\\.type').val();
         $('tr[for="monit.service.pidfile"]').addClass('hidden');
         $('tr[for="monit.service.match"]').addClass('hidden');
         $('tr[for="monit.service.path"]').addClass('hidden');
         $('tr[for="monit.service.address"]').addClass('hidden');
         $('tr[for="monit.service.interface"]').addClass('hidden');
         $('tr[for="monit.service.start"]').removeClass('hidden');
         $('tr[for="monit.service.stop"]').removeClass('hidden');
         switch (servicetype) {
            case 'process':
               var pidfile = $('#monit\\.service\\.pidfile').val();
               var match = $('#monit\\.service\\.match').val();
               if (pidfile !== '') {
                  $('tr[for="monit.service.pidfile"]').removeClass('hidden');
                  $('tr[for="monit.service.match"]').addClass('hidden');
               } else if (match !== '') {
                  $('tr[for="monit.service.pidfile"]').addClass('hidden');
                  $('tr[for="monit.service.match"]').removeClass('hidden');
               } else {
                  $('tr[for="monit.service.pidfile"]').removeClass('hidden');
                  $('tr[for="monit.service.match"]').removeClass('hidden');
               }
               break;
            case 'host':
               $('tr[for="monit.service.address"]').removeClass('hidden');
               break;
            case 'network':
               var address = $('#monit\\.service\\.address').val();
               var interface = $('#monit\\.service\\.interface').val();
               console.log('-' + address + '-' + interface + '-');
               if (address !== '') {
                  $('tr[for="monit.service.address"]').removeClass('hidden');
                  $('tr[for="monit.service.interface"]').addClass('hidden');
               } else if (interface !== '') {
                  $('tr[for="monit.service.address"]').addClass('hidden');
                  $('tr[for="monit.service.interface"]').removeClass('hidden');
               } else {
                  $('tr[for="monit.service.address"]').removeClass('hidden');
                  $('tr[for="monit.service.interface"]').removeClass('hidden');
               }
               break;
            case 'system':
               $('tr[for="monit.service.start"]').addClass('hidden');
               $('tr[for="monit.service.stop"]').addClass('hidden');
               break;
            default:
               $('tr[for="monit.service.path"]').removeClass('hidden');
         }
      };
      $('#DialogEditService').on('shown.bs.modal', function() {ShowHideFields();});
      $('#monit\\.service\\.type').on('changed.bs.select', function(e) {ShowHideFields();});
      $('#monit\\.service\\.pidfile').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.match').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.path').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.address').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.interface').on('changed.bs.select', function(e) {ShowHideFields();});

      $("#grid-services").UIBootgrid({
         'search':'/api/monit/settings/searchService',
         'get':'/api/monit/settings/getService/',
         'set':'/api/monit/settings/setService/',
         'add':'/api/monit/settings/addService/',
         'del':'/api/monit/settings/delService/',
         'toggle':'/api/monit/settings/toggleService/'
      });


      /**
       * service test settings
       */

      // show hide execute field
      function ShowHideExecField(){
         var actiontype = $('#monit\\.test\\.action').val();
         $('tr[for="monit.test.path"]').addClass('hidden');
         if (actiontype === 'exec') {
            $('tr[for="monit.test.path"]').removeClass('hidden');
         }
      };
      $('#DialogEditTest').on('shown.bs.modal', function() {ShowHideExecField();});
      $('#monit\\.test\\.action').on('changed.bs.select', function(e) {ShowHideExecField();});

      function openTestDialog(uuid) {
         var editDlg = "TestEditAlert";
         var setUrl = "/api/monit/settings/setTest/";
         var getUrl = "/api/monit/settings/getTest/";
         var urlMap = {};
         urlMap['frm_' + editDlg] = getUrl + uuid;
         mapDataToFormUI(urlMap).done(function () {
            $('.selectpicker').selectpicker('refresh');
            clearFormValidation('frm_' + editDlg);
            $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
            $('#'+editDlg).on('hidden.bs.modal', function () {
               parent.history.back();
            });
         });
      };

      $("#grid-tests").UIBootgrid({
         'search':'/api/monit/settings/searchTest',
         'get':'/api/monit/settings/getTest/',
         'set':'/api/monit/settings/setTest/',
         'add':'/api/monit/settings/addTest/',
         'del':'/api/monit/settings/delTest/'
      });

   });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">

</div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
   <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General Settings') }}</a></li>
   <li><a data-toggle="tab" href="#alerts">{{ lang._('Alert Settings') }}</a></li>
   <li><a data-toggle="tab" href="#services">{{ lang._('Service Settings') }}</a></li>
   <li><a data-toggle="tab" href="#tests">{{ lang._('Service Tests Settings') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
   <div id="general" class="tab-pane fade in active">
      <!-- monit general settings -->
      {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings','apply_btn_id':'btn_ApplyGeneralSettings'])}}
   </div>
   <div id="alerts" class="tab-pane fade in">
      <table id="grid-alerts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditAlert">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="recipient" data-width="12em" data-type="string">{{ lang._('Recipient') }}</th>
                <th data-column-id="noton" data-width="2em" data-type="string" data-align="right" data-formatter="boolean"></th>
                <th data-column-id="events" data-type="string">{{ lang._('Events') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
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
   <div id="services" class="tab-pane fade in">
      <table id="grid-services" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditService">
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
   <div id="tests" class="tab-pane fade in">
      <table id="grid-tests" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditTest">
         <thead>
            <tr>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="condition" data-type="string">{{ lang._('Condition') }}</th>
                <th data-column-id="action" data-type="string">{{ lang._('Action') }}</th>
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
         <button class="btn btn-primary" id="btn_configtest" type="button"><b>{{ lang._('Test Configuration') }}</b><i id="btn_configtest_progress" class=""></i></button>
         <button class="btn btn-primary" id="btn_reload" type="button"><b>{{ lang._('Reload Configuration') }}</b><i id="btn_reload_progress" class=""></i></button>
         <br/>
         <br/>
      </div>
</div>
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditAlert,'id':'DialogEditAlert','label':'Edit Alert&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "ALERT MESSAGES".</small>'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditService,'id':'DialogEditService','label':'Edit Service'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTest,'id':'DialogEditTest','label':'Edit Test&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "SERVICE TESTS".</small>'])}}
