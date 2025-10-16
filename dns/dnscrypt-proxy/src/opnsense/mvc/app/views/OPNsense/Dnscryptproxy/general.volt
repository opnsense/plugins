{#
 # Copyright (c) 2014-2018 Deciso B.V.
 # Copyright (c) 2018 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#forwards">{{ lang._('Forwarders') }}</a></li>
    <li><a data-toggle="tab" href="#cloaks">{{ lang._('Overrides') }}</a></li>
    <li><a data-toggle="tab" href="#whitelists">{{ lang._('Whitelists') }}</a></li>
    <li><a data-toggle="tab" href="#servers">{{ lang._('Servers') }}</a></li>
    <li><a data-toggle="tab" href="#dnsbl">{{ lang._('DNSBL') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <div class="col-md-12 __mt">
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="forwards" class="tab-pane fade in">
        <table id="grid-forwards" class="table table-responsive" data-editDialog="dialogEditDnscryptproxyForward">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="8em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="domain" data-type="string" data-visible="true">{{ lang._('Domain') }}</th>
                    <th data-column-id="dnsserver" data-type="string" data-visible="true">{{ lang._('DNS Server') }}</th>
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
            <button class="btn btn-primary" id="saveAct_forward" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_forward_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="cloaks" class="tab-pane fade in">
        <table id="grid-cloaks" class="table table-responsive" data-editDialog="dialogEditDnscryptproxyCloak">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="8em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="destination" data-type="string" data-visible="true">{{ lang._('Destination') }}</th>
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
            <button class="btn btn-primary" id="saveAct_cloak" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_cloak_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="whitelists" class="tab-pane fade in">
        <table id="grid-whitelists" class="table table-responsive" data-editDialog="dialogEditDnscryptproxyWhitelist">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="8em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
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
            <button class="btn btn-primary" id="saveAct_whitelist" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_whitelist_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="servers" class="tab-pane fade in">
        <table id="grid-servers" class="table table-responsive" data-editDialog="dialogEditDnscryptproxyServer">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="8em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="stamp" data-type="string" data-visible="true">{{ lang._('SDNS Stamp') }}</th>
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
            <button class="btn btn-primary" id="saveAct_server" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_server_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="dnsbl" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':dnsblForm,'id':'frm_dnsbl_settings'])}}
            <div class="col-md-12 __mt">
                <button class="btn btn-primary" id="saveAct_dnsbl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_dnsbl_progress"></i></button>
            </div>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditDnscryptproxyForward,'id':'dialogEditDnscryptproxyForward','label':lang._('Edit Forwarders')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDnscryptproxyCloak,'id':'dialogEditDnscryptproxyCloak','label':lang._('Edit Overrides')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDnscryptproxyWhitelist,'id':'dialogEditDnscryptproxyWhitelist','label':lang._('Edit Whitelists')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDnscryptproxyServer,'id':'dialogEditDnscryptproxyServer','label':lang._('Edit Servers')])}}

<script>

$( document ).ready(function() {
    var data_get_map = {'frm_general_settings':"/api/dnscryptproxy/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    var data_get_map2 = {'frm_dnsbl_settings':"/api/dnscryptproxy/dnsbl/get"};
    mapDataToFormUI(data_get_map2).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $("#grid-forwards").UIBootgrid(
        {   'search':'/api/dnscryptproxy/forward/search_forward',
            'get':'/api/dnscryptproxy/forward/get_forward/',
            'set':'/api/dnscryptproxy/forward/set_forward/',
            'add':'/api/dnscryptproxy/forward/add_forward/',
            'del':'/api/dnscryptproxy/forward/del_forward/',
            'toggle':'/api/dnscryptproxy/forward/toggle_forward/'
        }
    );

    $("#grid-cloaks").UIBootgrid(
        {   'search':'/api/dnscryptproxy/cloak/search_cloak',
            'get':'/api/dnscryptproxy/cloak/get_cloak/',
            'set':'/api/dnscryptproxy/cloak/set_cloak/',
            'add':'/api/dnscryptproxy/cloak/add_cloak/',
            'del':'/api/dnscryptproxy/cloak/del_cloak/',
            'toggle':'/api/dnscryptproxy/cloak/toggle_cloak/'
        }
    );

    $("#grid-whitelists").UIBootgrid(
        {   'search':'/api/dnscryptproxy/whitelist/search_whitelist',
            'get':'/api/dnscryptproxy/whitelist/get_whitelist/',
            'set':'/api/dnscryptproxy/whitelist/set_whitelist/',
            'add':'/api/dnscryptproxy/whitelist/add_whitelist/',
            'del':'/api/dnscryptproxy/whitelist/del_whitelist/',
            'toggle':'/api/dnscryptproxy/whitelist/toggle_whitelist/'
        }
    );

    $("#grid-servers").UIBootgrid(
        {   'search':'/api/dnscryptproxy/server/search_server',
            'get':'/api/dnscryptproxy/server/get_server/',
            'set':'/api/dnscryptproxy/server/set_server/',
            'add':'/api/dnscryptproxy/server/add_server/',
            'del':'/api/dnscryptproxy/server/del_server/',
            'toggle':'/api/dnscryptproxy/server/toggle_server/'
        }
    );

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/dnscryptproxy/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnscryptproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('dnscryptproxy');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_forward").click(function(){
        saveFormToEndpoint(url="/api/dnscryptproxy/forward/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_forward_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnscryptproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('dnscryptproxy');
                $("#saveAct_forward_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_cloak").click(function(){
        saveFormToEndpoint(url="/api/dnscryptproxy/cloak/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_cloak_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnscryptproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('dnscryptproxy');
                $("#saveAct_cloak_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_whitelist").click(function(){
        saveFormToEndpoint(url="/api/dnscryptproxy/whitelist/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_whitelist_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnscryptproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('dnscryptproxy');
                $("#saveAct_whitelist_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_server").click(function(){
        saveFormToEndpoint(url="/api/dnscryptproxy/server/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_server_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnscryptproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('dnscryptproxy');
                $("#saveAct_server_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_dnsbl").click(function(){
        saveFormToEndpoint(url="/api/dnscryptproxy/dnsbl/set", formid='frm_dnsbl_settings',callback_ok=function(){
        $("#saveAct_dnsbl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/dnscryptproxy/service/dnsbl", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/dnscryptproxy/service/reconfigure", sendData={}, callback=function(data,status) {
                    updateServiceControlUI('dnscryptproxy');
                    $("#saveAct_dnsbl_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });

    updateServiceControlUI('dnscryptproxy');
});
</script>
