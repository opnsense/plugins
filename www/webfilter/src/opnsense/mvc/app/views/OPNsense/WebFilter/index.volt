{#

Copyright (C) 2018-2020 Cloudfence
Copyright (c) 2019 Deciso B.V.

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

function timeoutCheck() {
    ajaxCall(url="/api/webfilter/service/download", sendData={}, callback=function(data,status) {
        if (data['status'] == 'done') {
            $("#dl_categ_progress").removeClass("fa fa-spinner fa-pulse");
            $("#dl_categ").prop("disabled", false);
            $('#dl_categ_alert').hide();
        } else {
            setTimeout(timeoutCheck, 2500);
        }
    });
}
    $(function() {

        function isSubsystemDirty() {
         ajaxGet("/api/webfilter/settings/dirty", {}, function(data, status) {
            if (status == "success") {
               if (data.webfilter.dirty === true) {
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
       * apply changes and reload webfilter
       */
      $('#btnApplyConfig').off('click').click(function(){
         $('#btnApplyConfigProgress').addClass("fa fa-spinner fa-pulse");
         ajaxCall("/api/webfilter/service/reconfigure", {}, function(data,status) {
            $("#responseMsg").addClass("hidden");
            isSubsystemDirty();
            updateServiceControlUI('webfilter');
            if (data.result) {
               $("#responseMsg").html(data['result']);
               $("#responseMsg").removeClass("hidden");
            }
            $('#btnApplyConfigProgress').removeClass("fa fa-spinner fa-pulse");
            $('#btnApplyConfig').blur();
         });
      });

        const data_get_map = {'frm_webfilter':"/api/webfilter/settings/get"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // request service status on load and update status box
            ajaxCall(url="/api/webfilter/service/status", sendData={}, callback=function(data,status) {
                updateServiceStatusUI(data['status']);
            });
        });

        /***********************************************************************
         * link grid rules
         **********************************************************************/

        $("#grid-rules").UIBootgrid(
            {   search:'/api/webfilter/settings/searchRules',
                get:'/api/webfilter/settings/getRule/',
                set:'/api/webfilter/settings/setRule/',
                add:'/api/webfilter/settings/addRule/',
                del:'/api/webfilter/settings/delRule/',
                options: {
                    rowCount:[10,25,50,100]
                }
            }
        );

        // hook into on-show event for dialog to extend layout.
        $('#DialogRule').on('shown.bs.modal', function (e) {
            $("#action\\.type").change(function(){
                const service_id = 'table_' + $(this).val();
                $(".type_table").hide();
                // $(".table_"+$(this).val()).show();
                $("."+service_id).show();
            });
            $("#action\\.type").change();
        })

        /***********************************************************************
         * Commands
         **********************************************************************/

    ajaxCall(url="/api/webfilter/service/download", sendData={}, callback=function(data,status) { 
        if (data['status'] != 'done') {
            if (data['status'] == 'running') {
                $("#dl_categ_progress").addClass("fa fa-spinner fa-pulse");
                $("#dl_categ").prop("disabled", true);
                setTimeout(timeoutCheck, 2500);
            }
            $('#dl_categ_alert').show();
        }
    });

        // Reconfigure webfilter - activate changes
        $('[id*="reconfigureAct"]').each(function(){
            $(this).click(function(){
                // set progress animation
                $('[id*="reconfigureAct_progress"]').each(function(){
                    $(this).addClass("fa fa-spinner fa-pulse");
                });

            });
        });
        
        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function(){
                const frm_id = $(this).closest("form").attr("id");
                const frm_title = $(this).closest("form").attr("data-title");

                // set progress animation
                $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                // save data for tab
                saveFormToEndpoint(url="/api/webfilter/settings/set",formid=frm_id,callback_ok=function(){

                    // on correct save, perform reconfigure
                    ajaxCall(url="/api/webfilter/service/reconfigure", sendData={}, callback=function(data,status) {
                        if (status != "success" || data['status'] != 'ok') {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_WARNING,
                                title: "{{ lang._('Error reconfiguring WebFilter') }}",
                                message: data['status'],
                                draggable: true
                            });
                        } else {
                            ajaxCall(url="/api/webfilter/service/status", sendData={}, callback=function(data,status) {
                                updateServiceStatusUI(data['status']);
                            });
                        }
                        // when done, disable progress animation.
                        $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");
                    });

                });
            });
        });

        $("#dl_categ").click(function(){
            $("#dl_categ_progress").addClass("fa fa-spinner fa-pulse");
            $("#dl_categ").prop("disabled", true);
            ajaxCall(url="/api/webfilter/service/download", sendData={action:1}, callback_ok=function(){
                setTimeout(timeoutCheck, 2500);
            });
        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

    });

</script>

<div class="alert alert-warning" role="alert" id="dl_categ_alert" style="display:none;min-height:65px;">
    <button class="btn btn-primary pull-right" id="dl_categ" type="button">{{ lang._('Download') }} <i id="dl_categ_progress"></i></button>
    <div style="margin-top: 8px;">{{ lang._('No blacklist categories database found, please download before use. The download and the database build will take several minutes and this message will disappear when it has been completed.') }}</div>
</div>
<div class="alert alert-info hidden" role="alert" id="configChangedMsg">
   <button class="btn btn-primary pull-right" id="btnApplyConfig" type="button"><b>{{ lang._('Apply changes') }}</b> <i id="btnApplyConfigProgress"></i></button>
   {{ lang._('The Web Filter configuration has been changed') }} <br /> {{ lang._('You must apply the changes in order for them to take effect.')}}
</div>
<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':settingsForm]) }}
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#"
           class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#rules-tab').click();"
           class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block"
           style="border-right:0;"><b>{{ lang._('Rules')}}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="rules-tab" href="#rules">{{ lang._('Rules')}}</a>
            </li>
        </ul>
    </li>
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':settingsForm]) }}
    <div id="rules" class="tab-pane fade">
        <table id="grid-rules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRules">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="sequence" data-type="string" data-order="asc">{{ lang._('#') }}</th>
                <th data-column-id="action" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Action') }}</th>
                <th data-column-id="name" data-type="string" data-visible="false">{{ lang._('Name') }}</th>
                <th data-column-id="source" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Source') }}</th>
                <th data-column-id="destination" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Destination') }}</th>
                <th data-column-id="description" data-type="string" data-sortable="true"  data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
</div>


{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogRules,'id':'DialogRules','label':lang._('Edit Rules')])}}
