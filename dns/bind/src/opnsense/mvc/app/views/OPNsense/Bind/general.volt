{#

OPNsense® is Copyright © 2014 – 2019 by Deciso B.V.
This file is Copyright © 2018 - 2019 by Michael Muenz <m.muenz@gmail.com>
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
    <li><a data-toggle="tab" href="#dnsbl">{{ lang._('DNSBL') }}</a></li>
    <li><a data-toggle="tab" href="#acls">{{ lang._('ACLs') }}</a></li>
    <li><a data-toggle="tab" href="#master-domains">{{ lang._('Master Zones') }}</a></li>
    <li><a data-toggle="tab" href="#slave-domains">{{ lang._('Slave Zones') }}</a></li>
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
    <div id="dnsbl" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':dnsblForm,'id':'frm_dnsbl_settings'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct_dnsbl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_dnsbl_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="acls" class="tab-pane fade in">
        <table id="grid-acls" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="dialogEditBindAcl">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="networks" data-type="string" data-visible="true">{{ lang._('Networks') }}</th>
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
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_acl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_acl_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="master-domains" class="tab-pane fade in">
        <div class="col-md-12">
            <h2>{{ lang._('Zones') }}</h2>
        </div>
        <table id="grid-master-domains" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindMasterDomain">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="type" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                    <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                    <th data-column-id="ttl" data-type="string" data-visible="true">{{ lang._('TTL') }}</th>
                    <th data-column-id="refresh" data-type="string" data-visible="true">{{ lang._('Refresh') }}</th>
                    <th data-column-id="retry" data-type="string" data-visible="true">{{ lang._('Retry') }}</th>
                    <th data-column-id="expire" data-type="string" data-visible="true">{{ lang._('Expire') }}</th>
                    <th data-column-id="negative" data-type="string" data-visible="true">{{ lang._('Negative TTL') }}</th>
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
        <hr/>
        <div id="master-record-area">
            <div class="col-md-12">
                <h2>{{ lang._('Records') }}</h2>
            </div>
            <table id="grid-master-records" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindRecord">
                <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="domain" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="type" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
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
                        <button id="recordAddBtn" data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button id="recordDelBtn" data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
        <div class="col-md-12">
            <div id="ChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <hr />
            <button class="btn btn-primary saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i class="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="slave-domains" class="tab-pane fade in">
        <div class="col-md-12">
            <h2>{{ lang._('Zones') }}</h2>
        </div>
        <table id="grid-slave-domains" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindSlaveDomain">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="type" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                    <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                    <th data-column-id="masterip" data-type="string" data-visible="true">{{ lang._('Master IPs') }}</th>
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
        <hr/>
        <div class="col-md-12">
            <div id="ChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <hr />
            <button class="btn btn-primary saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i class="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindAcl,'id':'dialogEditBindAcl','label':lang._('Edit ACL')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindMasterDomain,'id':'dialogEditBindMasterDomain','label':lang._('Edit Master Zone')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindSlaveDomain,'id':'dialogEditBindSlaveDomain','label':lang._('Edit Slave Zone')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindRecord,'id':'dialogEditBindRecord','label':lang._('Edit Record')])}}

<script>
$( document ).ready(function() {
    let data_get_map = {'frm_general_settings':"/api/bind/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    let data_get_map2 = {'frm_dnsbl_settings':"/api/bind/dnsbl/get"};
    mapDataToFormUI(data_get_map2).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    updateServiceControlUI('bind');

    $("#grid-acls").UIBootgrid(
        {   'search':'/api/bind/acl/searchAcl',
            'get':'/api/bind/acl/getAcl/',
            'set':'/api/bind/acl/setAcl/',
            'add':'/api/bind/acl/addAcl/',
            'del':'/api/bind/acl/delAcl/',
            'toggle':'/api/bind/acl/toggleAcl/'
        }
    );

    $("#grid-master-domains").UIBootgrid({
        'search':'/api/bind/domain/searchMasterDomain',
        'get':'/api/bind/domain/getDomain/',
        'set':'/api/bind/domain/setDomain/',
        'add':'/api/bind/domain/addMasterDomain/',
        'del':'/api/bind/domain/delDomain/',
        'toggle':'/api/bind/domain/toggleDomain/',
        options:{
            selection: true,
            multiSelect: false,
            rowSelect: true,
            rowCount: [3,7,14,20,50,100,-1]
        }
    }).on("selected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-master-records").bootgrid('reload');
    }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-master-records").bootgrid('reload');
    }).on("loaded.rs.jquery.bootgrid", function (e) {
        let ids = $("#grid-master-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-master-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $("#grid-slave-domains").UIBootgrid({
        'search':'/api/bind/domain/searchSlaveDomain',
        'get':'/api/bind/domain/getDomain/',
        'set':'/api/bind/domain/setDomain/',
        'add':'/api/bind/domain/addSlaveDomain/',
        'del':'/api/bind/domain/delDomain/',
        'toggle':'/api/bind/domain/toggleDomain/',
        options:{
            selection: false,
            multiSelect: false,
            rowSelect: false,
            rowCount: [7,14,20,50,100,-1]
        }
    }).on("loaded.rs.jquery.bootgrid", function (e) {
        let ids = $("#grid-slave-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-slave-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $("#grid-master-records").UIBootgrid({
        'search':'/api/bind/record/searchRecord',
        'get':'/api/bind/record/getRecord/',
        'set':'/api/bind/record/setRecord/',
        'add':'/api/bind/record/addRecord/',
        'del':'/api/bind/record/delRecord/',
        'toggle':'/api/bind/record/toggleRecord/',
        options:{
            useRequestHandlerOnGet: true,
            requestHandler: function(request) {
                let ids = $("#grid-master-domains").bootgrid("getSelectedRows");
                if (ids.length > 0) {
                    request['domain'] = ids[0];
                    $("#recordAddBtn").show();
                    $("#recordDelBtn").show();
                    $("#master-record-area").show();
                } else {
                    request['domain'] = 'not_found';
                    $("#recordAddBtn").hide();
                    $("#recordDelBtn").hide();
                    $("#master-record-area").hide();
                }
                return request;
            }
        }
    });

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/bind/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/bind/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('bind');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_dnsbl").click(function(){
        saveFormToEndpoint(url="/api/bind/dnsbl/set", formid='frm_dnsbl_settings',callback_ok=function(){
        $("#saveAct_dnsbl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/bind/service/dnsbl", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/bind/service/reconfigure", sendData={}, callback=function(data,status) {
                    updateServiceControlUI('bind');
                    $("#saveAct_dnsbl_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });

    $("#saveAct_acl").click(function(){
        saveFormToEndpoint(url="/api/bind/acl/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_acl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/bind/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('bind');
                $("#saveAct_acl_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $(".saveAct_domain").click(function(){
        $(".saveAct_domain_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall("/api/bind/service/reconfigure", {}, function(data,status) {
            updateServiceControlUI('bind');
            $(".saveAct_domain_progress").removeClass("fa fa-spinner fa-pulse");
        });
    });

    $('#domain\\.transferkeyalgo').on('change', function(e) {
        if (e.target.selectedIndex === 0) {
            $('#domain\\.transferkey,#domain\\.transferkeyname').val('').attr('readonly', true);
        } else {
            $('#domain\\.transferkey,#domain\\.transferkeyname').attr('readonly', false);
        }
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
