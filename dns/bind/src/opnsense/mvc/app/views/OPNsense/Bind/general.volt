{#

OPNsense® is Copyright © 2014 – 2018 by Deciso B.V.
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
    <li><a data-toggle="tab" href="#domains">{{ lang._('Domains') }}</a></li>
    <li><a data-toggle="tab" href="#records">{{ lang._('Records') }}</a></li>
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
        <table id="grid-acls" class="table table-responsive" data-editDialog="dialogEditBindAcl">
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
    <div id="domains" class="tab-pane fade in">
        <table id="grid-domains" class="table table-responsive" data-editDialog="dialogEditBindDomain">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="type" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                    <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Domain') }}</th>
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
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="records" class="tab-pane fade in">
        <table id="grid-records" class="table table-responsive" data-editDialog="dialogEditBindRecord">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="domain" data-type="string" data-visible="true">{{ lang._('Domain') }}</th>
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
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_record" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_record_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindAcl,'id':'dialogEditBindAcl','label':lang._('Edit ACL')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindDomain,'id':'dialogEditBindDomain','label':lang._('Edit Domains')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindRecord,'id':'dialogEditBindRecord','label':lang._('Edit Records')])}}

<script>
$( document ).ready(function() {
    var data_get_map = {'frm_general_settings':"/api/bind/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    var data_get_map2 = {'frm_dnsbl_settings':"/api/bind/dnsbl/get"};
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
    $("#grid-domains").UIBootgrid(
        {   'search':'/api/bind/domain/searchDomain',
            'get':'/api/bind/domain/getDomain/',
            'set':'/api/bind/domain/setDomain/',
            'add':'/api/bind/domain/addDomain/',
            'del':'/api/bind/domain/delDomain/',
            'toggle':'/api/bind/domain/toggleDomain/'
        }
    );
    $("#grid-records").UIBootgrid(
        {   'search':'/api/bind/record/searchRecord',
            'get':'/api/bind/record/getRecord/',
            'set':'/api/bind/record/setRecord/',
            'add':'/api/bind/record/addRecord/',
            'del':'/api/bind/record/delRecord/',
            'toggle':'/api/bind/record/toggleRecord/'
        }
    );

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

    $("#saveAct_domain").click(function(){
        saveFormToEndpoint(url="/api/bind/domain/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_domain_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/bind/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('bind');
                $("#saveAct_domain_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_record").click(function(){
        saveFormToEndpoint(url="/api/bind/record/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_record_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/bind/service/reconfigure", sendData={}, callback=function(data,status) {
                updateServiceControlUI('bind');
                $("#saveAct_record_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

});
</script>
