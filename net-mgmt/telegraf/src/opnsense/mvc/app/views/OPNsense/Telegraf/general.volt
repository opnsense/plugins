{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Michael Muenz <m.muenz@gmail.com>
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

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#keys">{{ lang._('Tags') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="keys" class="tab-pane fade in">
        <table id="grid-keys" class="table table-responsive" data-editDialog="dialogEditTelegrafKey">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="value" data-type="string" data-visible="true">{{ lang._('Value') }}</th>
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
                    </td>
                </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_key" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_key_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditTelegrafKey,'id':'dialogEditTelegrafKey','label':lang._('Edit Mapping')])}}

<script>
    $( document ).ready(function() {
        var data_get_map = {'frm_general_settings':"/api/telegraf/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

    updateServiceControlUI('telegraf');

    $("#grid-keys").UIBootgrid(
        {   'search':'/api/telegraf/key/searchKey',
            'get':'/api/telegraf/key/get_key/',
            'set':'/api/telegraf/key/set_key/',
            'add':'/api/telegraf/key/add_key/',
            'del':'/api/telegraf/key/del_key/',
            'toggle':'/api/telegraf/key/toggle_key/'
        }
    );

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/telegraf/general/set", formid='frm_general_settings',callback_ok=function(){
		    $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/telegraf/service/reconfigure", sendData={}, callback=function(data,status) {
                            updateServiceControlUI('telegraf');
			    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
        $("#saveAct_key").click(function(){
            saveFormToEndpoint(url="/api/telegraf/key/set", formid='frm_general_settings',callback_ok=function(){
		    $("#saveAct_key_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/telegraf/service/reconfigure", sendData={}, callback=function(data,status) {
                            updateServiceControlUI('telegraf');
			    $("#saveAct_key_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
    });
</script>
