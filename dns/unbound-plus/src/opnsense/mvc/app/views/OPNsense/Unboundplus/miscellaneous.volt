{#
 # Copyright (c) 2019 Deciso B.V.
 # Copyright (c) 2019 - 2020 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#upstream">{{ lang._('Upstream DNS') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':miscellaneousForm,'id':'frm_miscellaneous_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="upstream" class="tab-pane fade in">
        <table id="grid-upstream" class="table table-responsive" data-editDialog="dialogEditUnboundplusUpstream">
            <thead>
                <tr>
                    <th data-column-id="enable" data-type="string" data-formatter="rowtoggle">{{ lang._('Enable') }}</th>
                    <th data-column-id="enabledot" data-type="string" data-formatter="rowtoggle">{{ lang._('Enable DoT') }}</th>
                    <th data-column-id="server" data-type="string" data-visible="true">{{ lang._('Server IP') }}</th>
                    <th data-column-id="port" data-type="string" data-visible="true">{{ lang._('Server Port') }}</th>
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
            <button class="btn btn-primary"  id="saveAct_upstream" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_upstream_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditUnboundplusUpstream,'id':'dialogEditUnboundplusUpstream','label':lang._('Edit Upstream DNS')])}}
    
<script>
    $(function() {
        var data_get_map = {'frm_miscellaneous_settings':"/api/unboundplus/miscellaneous/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#grid-upstream").UIBootgrid(
            {   'search':'/api/unboundplus/upstream/searchUpstream',
                'get':'/api/unboundplus/upstream/getUpstream/',
                'set':'/api/unboundplus/upstream/setUpstream/',
                'add':'/api/unboundplus/upstream/addUpstream/',
                'del':'/api/unboundplus/upstream/delUpstream/',
                'toggle':'/api/unboundplus/upstream/toggleUpstream/'
            }
        );

        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/unboundplus/miscellaneous/set", formid='frm_miscellaneous_settings',callback_ok=function(){
                $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/unboundplus/service/reloadunbound", sendData={}, callback=function(data,status) {
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/unboundplus/upstream/set", formid='frm_upstream_settings',callback_ok=function(){
                $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/unboundplus/service/reloadunbound", sendData={}, callback=function(data,status) {
                    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });
</script>
