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

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#users">{{ lang._('Users') }}</a></li>
    <li><a data-toggle="tab" href="#domains">{{ lang._('Outbound Domains') }}</a></li>
    <li><a data-toggle="tab" href="#showregistrations">{{ lang._('Current registrations') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="users" class="tab-pane fade in">
        <table id="grid-users" class="table table-responsive" data-editDialog="dialogEditSiproxdUser">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="username" data-type="string" data-visible="true">{{ lang._('Username') }}</th>
                    <th data-column-id="password" data-type="string" data-visible="true">{{ lang._('Password') }}</th>
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
            <button class="btn btn-primary"  id="saveAct_user" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_user_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="domains" class="tab-pane fade in">
        <table id="grid-domains" class="table table-responsive" data-editDialog="dialogEditSiproxdDomain">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="host" data-type="string" data-visible="true">{{ lang._('Host') }}</th>
                    <th data-column-id="port" data-type="string" data-visible="true">{{ lang._('Port') }}</th>
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
            <button class="btn btn-primary"  id="saveAct_domain" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="showregistrations" class="tab-pane fade in">
      <pre id="showregistrations-cmd"></pre>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditSiproxdUser,'id':'dialogEditSiproxdUser','label':lang._('Edit User')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditSiproxdDomain,'id':'dialogEditSiproxdDomain','label':lang._('Edit Outbound Domain')])}}

<script>
$( document ).ready(function() {
    var data_get_map = {'frm_general_settings':"/api/siproxd/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    ajaxCall(url="/api/siproxd/service/showregistrations", sendData={}, callback=function(data,status) {
        $("#showregistrations-cmd").text(data['response']);
    });

    ajaxCall(url="/api/siproxd/service/status", sendData={}, callback=function(data,status) {
        updateServiceStatusUI(data['status']);
    });

    $("#grid-users").UIBootgrid(
        {   'search':'/api/siproxd/user/searchUser',
            'get':'/api/siproxd/user/get_user/',
            'set':'/api/siproxd/user/set_user/',
            'add':'/api/siproxd/user/add_user/',
            'del':'/api/siproxd/user/del_user/',
            'toggle':'/api/siproxd/user/toggle_user/'
        }
    );

    $("#grid-domains").UIBootgrid(
        {   'search':'/api/siproxd/domain/searchDomain',
            'get':'/api/siproxd/domain/get_domain/',
            'set':'/api/siproxd/domain/set_domain/',
            'add':'/api/siproxd/domain/add_domain/',
            'del':'/api/siproxd/domain/del_domain/',
            'toggle':'/api/siproxd/domain/toggle_domain/'
        }
    );

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/siproxd/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/siproxd/service/reconfigure", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/siproxd/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['status']);
                });
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_user").click(function(){
        saveFormToEndpoint(url="/api/siproxd/user/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_user_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/siproxd/service/reconfigure", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/siproxd/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['status']);
                });
                $("#saveAct_user_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_domain").click(function(){
        saveFormToEndpoint(url="/api/siproxd/domain/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_domain_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/siproxd/service/reconfigure", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/siproxd/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['status']);
                });
                $("#saveAct_domain_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

});
</script>
