{#

OPNsense® is Copyright © 2014 – 2018 by Deciso B.V.
This file is Copyright © 2019 by Michael Muenz <m.muenz@gmail.com>
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
    <li><a data-toggle="tab" href="#command">{{ lang._('Commands') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="command" class="tab-pane fade in">
        <table id="grid-command" class="table table-responsive" data-editDialog="dialogEditNrpeCommand">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="nrpecommand" data-type="string" data-visible="true">{{ lang._('Command') }}</th>
                    <th data-column-id="arguments" data-type="string" data-visible="true">{{ lang._('Arguments') }}</th>
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
            <button class="btn btn-primary"  id="saveAct_command" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_command_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditNrpeCommand,'id':'dialogEditNrpeCommand','label':lang._('Edit Commands')])}}

<script>

$(function() {
    var data_get_map = {'frm_general_settings':"/api/nrpe/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $("#grid-command").UIBootgrid(
        {   'search':'/api/nrpe/command/search_command',
            'get':'/api/nrpe/command/get_command/',
            'set':'/api/nrpe/command/set_command/',
            'add':'/api/nrpe/command/add_command/',
            'del':'/api/nrpe/command/del_command/',
            'toggle':'/api/nrpe/command/toggle_command/'
        }
    );

    updateServiceControlUI('nrpe');

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/nrpe/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/nrpe/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('nrpe');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_command").click(function(){
        saveFormToEndpoint(url="/api/nrpe/command/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_command_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/nrpe/service/reconfigure", sendData={}, callback=function(data,status) {
                $("#saveAct_command_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

});
</script>
