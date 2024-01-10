{#
 #
 # Copyright (c) 2014-2019 Deciso B.V.
 # Copyright (c) 2018-2019 Michael Muenz <m.muenz@gmail.com>
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
    <li><a data-toggle="tab" href="#dnsbl">{{ lang._('DNSBL') }}</a></li>
    <li><a data-toggle="tab" href="#acls">{{ lang._('ACLs') }}</a></li>
    <li><a data-toggle="tab" href="#primary-domains">{{ lang._('Primary Zones') }}</a></li>
    <li><a data-toggle="tab" href="#secondary-domains">{{ lang._('Secondary Zones') }}</a></li>
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
    <div id="dnsbl" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':dnsblForm,'id':'frm_dnsbl_settings'])}}
            <div class="col-md-12 __mt">
                <button class="btn btn-primary" id="saveAct_dnsbl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_dnsbl_progress"></i></button>
            </div>
        </div>
    </div>
    <div id="acls" class="tab-pane fade in">
        <div id="acls-area" class="table-responsive">
            <table id="grid-acls" class="table table-condensed table-hover table-striped" data-editDialog="dialogEditBindAcl">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                        <th data-column-id="networks" data-type="string" data-visible="true" data-css-class="long-str">{{ lang._('Networks') }}</th>
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
        </div>
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="saveAct_acl" type="button"><b>{{ lang._('Save') }}</b> <i id="saveAct_acl_progress"></i></button>
            <br /><br />
        </div>
    </div>
    <div id="primary-domains" class="tab-pane fade in">
        <div class="col-md-12">
            <h2>{{ lang._('Zones') }}</h2>
        </div>
        <div id="primary-domains-area" class="table-responsive">
            <table id="grid-primary-domains" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindPrimaryDomain">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="domainname" data-type="string" data-visible="true" data-css-class="zonename">{{ lang._('Zone') }}</th>
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
        </div>
        <hr/>
        <div class="col-md-12">
            <h2>{{ lang._('Records') }}</h2>
        </div>
        <div id="primary-record-area" class="table-responsive">
            <table id="grid-primary-records" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindRecord">
                <thead>
                <tr>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="domain" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="type" data-type="string" data-visible="true">{{ lang._('Type') }}</th>
                    <th data-column-id="value" data-type="string" data-visible="true" data-css-class="long-str">{{ lang._('Value') }}</th>
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
    <div id="secondary-domains" class="tab-pane fade in">
        <div class="col-md-12">
            <h2>{{ lang._('Zones') }}</h2>
        </div>
        <div id="secondary-domains-area" class="table-responsive">
            <table id="grid-secondary-domains" class="table table-condensed table-hover table-striped" data-editAlert="ChangeMessage" data-editDialog="dialogEditBindSecondaryDomain">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                        <th data-column-id="domainname" data-type="string" data-visible="true">{{ lang._('Zone') }}</th>
                        <th data-column-id="primaryip" data-type="string" data-visible="true">{{ lang._('Primary IPs') }}</th>
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
        </div>
        <hr/>
        <div class="col-md-12">
            <div id="ChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <button class="btn btn-primary saveAct_domain" type="button"><b>{{ lang._('Save') }}</b> <i class="saveAct_domain_progress"></i></button>
            <br /><br />
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindAcl,'id':'dialogEditBindAcl','label':lang._('Edit ACL')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindPrimaryDomain,'id':'dialogEditBindPrimaryDomain','label':lang._('Edit Primary Zone')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindSecondaryDomain,'id':'dialogEditBindSecondaryDomain','label':lang._('Edit Secondary Zone')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditBindRecord,'id':'dialogEditBindRecord','label':lang._('Edit Record')])}}

<style>
    #zone-content {
        overflow-x: auto;
    }
    #zone-table {
        display: grid;
        max-height: 400px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        padding-right: 20px;
    }
    .l-number {
        position: relative;
        width: 1%;
        min-width: 40px;
        padding-right: 20px;
        padding-left: 1px;
        font-family: ui-monospace,monospace;
        text-align: right;
        white-space: nowrap;
        vertical-align: top;
        user-select: none;
        filter: brightness(2.0);
        filter: contrast(0.3);
    }
    .long-str {
        word-break: break-word;
    }
    .copy-button {
        display: none;
    }
</style>
<script>
function zone_test(zonename) {
    let payload = {
        'zone': zonename,
    };
    ajaxCall(url = "/api/bind/general/zonetest/", payload, callback = function(data, status) {
        if (data['response'].indexOf('Zone check completed successfully') == -1) {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DANGER,
                closeByBackdrop: false,
                title: "{{ lang._('Primay zone check failed') }}",
                message: data['response'],
                buttons: [{
                        label: "{{ lang._('Show zone content') }}",
                        action: function(dlg) {
                            $(this).closest(".modal-dialog").find("div.bootstrap-dialog-body").append('<div id="zone-wait">{{ lang._("Loading zone content..") }}<div>');
                            zone_show(payload);
                        },
                    },
                    {
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        },
                    }
                ]
            });
        } else {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: "{{ lang._('Zone check completed successfully') }}",
                message: data['response'],
                buttons: [{
                        label: "{{ lang._('Show zone content') }}",
                        action: function(dlg) {
                            $(this).closest(".modal-dialog").find("div.bootstrap-dialog-body").append('<div id="zone-wait">{{ lang._("Loading zone content..") }}<div>');
                            zone_show(payload);
                        },
                    },
                    {
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        },
                    }
                ]
            });
        }
    });
}

function zone_show(payload) {
    ajaxCall(url = "/api/bind/general/zoneshow/", payload, callback = function(data, status) {
        if (data['time'] && data['zone_content']) {
            $("#zone-wait").remove();
            let L = 0;
            let content = [];
            content.push('<tr><td class="l-number"></td><td class="conf-line">; zone file dump from ' + data['path'] + '</td></tr>');
            content.push('<tr><td class="l-number"></td><td class="conf-line">; zone file created at ' + data['time'] + '</td></tr>');
            $.each(data['zone_content'], function(index, line) {
                L += 1;
                content.push('<tr><td class="l-number">' + L.toString() + '</td><td class="conf-line">' + line + '</td></tr>');
            });
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_INFO,
                title: "{{ lang._('Zone loaded successfully') }}",
                message: '<div id="zone-content"><table><tbody id="zone-table">' + content.join('') + '</tbody></table></div>',
                onshown: function(dialogRef) {
                    if ((typeof navigator.clipboard === 'object') && (typeof navigator.clipboard.writeText === 'function')) {
                        $(".copy-button").show();
                    }
                },
                buttons: [{
                        label: '<i id="copy-progress" class="fa fa-spinner fa-pulse" style="display: none"></i> {{ lang._("Copy to clipboard") }}',
                        cssClass: 'copy-button',
                        action: function() {
                            zone_copy();
                        }
                    },
                    {
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        }
                    }
                ]
            });
        } else {
            $("#zone-wait").text("{{ lang._('Empty response from the backend. Please check logs.') }}");
        }
    });
}

function zone_copy(dlg) {
    $('#copy-progress').show();
    let conf_to_clipboard = [];
    $('.conf-line').each(function() {
        conf_to_clipboard.push($(this).text())
    });
    navigator.clipboard.writeText(conf_to_clipboard.join('\n'));
    setTimeout(() => {
        $("#copy-progress").hide();
    }, 1000);
}

$(document).ready(function() {
    let data_get_map = {
        'frm_general_settings': "/api/bind/general/get"
    };
    mapDataToFormUI(data_get_map).done(function(data) {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    let data_get_map2 = {
        'frm_dnsbl_settings': "/api/bind/dnsbl/get"
    };
    mapDataToFormUI(data_get_map2).done(function(data) {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    updateServiceControlUI('bind');

    $("#grid-acls").UIBootgrid({
        'search': '/api/bind/acl/searchAcl',
        'get': '/api/bind/acl/getAcl/',
        'set': '/api/bind/acl/setAcl/',
        'add': '/api/bind/acl/addAcl/',
        'del': '/api/bind/acl/delAcl/',
        'toggle': '/api/bind/acl/toggleAcl/'
    });

    $("#grid-primary-domains").UIBootgrid({
        'search': '/api/bind/domain/searchPrimaryDomain',
        'get': '/api/bind/domain/getDomain/',
        'set': '/api/bind/domain/setDomain/',
        'add': '/api/bind/domain/addPrimaryDomain/',
        'del': '/api/bind/domain/delDomain/',
        'toggle': '/api/bind/domain/toggleDomain/',
        commands: {
            'bind-checkzone': {
                'title': "Check & preview",
                'classname': "fa fa-fw fa-stethoscope  ",
                'sequence': 300,
            },
        },
        options: {
            selection: true,
            multiSelect: false,
            rowSelect: true,
            rowCount: [3, 7, 14, 20, 50, 100, -1]
        }
    }).on("selected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-primary-records").bootgrid('reload');
    }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-primary-records").bootgrid('reload');
    }).on("loaded.rs.jquery.bootgrid", function(e) {
        // Checkzone button
        $("#grid-primary-domains").find(".command-bind-checkzone").off("click").on("click", function(ev) {
            if (!$(this).closest("tr").hasClass("text-muted")) {
                let zonename = $(this).closest('tr').find('td.zonename').text();
                zone_test(zonename);
            } else {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('For zone Check and Show to work, the zone must be enabled and the configuration applied.') }}",
                    buttons: [{
                        label: 'Ok',
                        action: function(dlg) {
                            dlg.close();
                        },
                    }]
                });
            }
        });

        let ids = $("#grid-primary-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-primary-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $("#grid-secondary-domains").UIBootgrid({
        'search': '/api/bind/domain/searchSecondaryDomain',
        'get': '/api/bind/domain/getDomain/',
        'set': '/api/bind/domain/setDomain/',
        'add': '/api/bind/domain/addSecondaryDomain/',
        'del': '/api/bind/domain/delDomain/',
        'toggle': '/api/bind/domain/toggleDomain/',
        options: {
            selection: false,
            multiSelect: false,
            rowSelect: false,
            rowCount: [7, 14, 20, 50, 100, -1]
        }
    }).on("loaded.rs.jquery.bootgrid", function(e) {
        let ids = $("#grid-secondary-domains").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-secondary-domains").bootgrid('select', [ids[0].uuid]);
        }
    });

    $("#grid-primary-records").UIBootgrid({
        'search': '/api/bind/record/searchRecord',
        'get': '/api/bind/record/getRecord/',
        'set': '/api/bind/record/setRecord/',
        'add': '/api/bind/record/addRecord/',
        'del': '/api/bind/record/delRecord/',
        'toggle': '/api/bind/record/toggleRecord/',
        options: {
            useRequestHandlerOnGet: true,
            requestHandler: function(request) {
                let ids = $("#grid-primary-domains").bootgrid("getSelectedRows");
                if (ids.length > 0) {
                    request['domain'] = ids[0];
                    $("#recordAddBtn").show();
                    $("#recordDelBtn").show();
                    $("#primary-record-area").show();
                } else {
                    request['domain'] = 'not_found';
                    $("#recordAddBtn").hide();
                    $("#recordDelBtn").hide();
                    $("#primary-record-area").hide();
                }
                return request;
            }
        }
    });

    $("#saveAct").click(function() {
        saveFormToEndpoint(url = "/api/bind/general/set", formid = 'frm_general_settings', callback_ok = function() {
            $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
                updateServiceControlUI('bind');
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#saveAct_dnsbl").click(function() {
        saveFormToEndpoint(url = "/api/bind/dnsbl/set", formid = 'frm_dnsbl_settings', callback_ok = function() {
            $("#saveAct_dnsbl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/bind/service/dnsbl", sendData = {}, callback = function(data, status) {
                ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
                    updateServiceControlUI('bind');
                    $("#saveAct_dnsbl_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });
    });

    $("#saveAct_acl").click(function() {
        saveFormToEndpoint(url = "/api/bind/acl/set", formid = 'frm_general_settings', callback_ok = function() {
            $("#saveAct_acl_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url = "/api/bind/service/reconfigure", sendData = {}, callback = function(data, status) {
                updateServiceControlUI('bind');
                $("#saveAct_acl_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $(".saveAct_domain").click(function() {
        $(".saveAct_domain_progress").addClass("fa fa-spinner fa-pulse");
        ajaxCall("/api/bind/service/reconfigure", {}, function(data, status) {
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
    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        history.pushState(null, null, e.target.hash);
    });
});
</script>
