{#

(Partially duplicates code from opnsense_bootgrid_plugin.js.)

Copyright (C) 2017-2021 Frank Wall
Copyright (C) 2015 Deciso B.V.
OPNsense® is Copyright © 2014-2015 by Deciso B.V.
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

    $( document ).ready(function() {

        /***********************************************************************
         * link grid actions
         **********************************************************************/

        var gridParams = {
            search:'/api/acmeclient/certificates/search',
            get:'/api/acmeclient/certificates/get/',
            set:'/api/acmeclient/certificates/update/',
            add:'/api/acmeclient/certificates/add/',
            del:'/api/acmeclient/certificates/del/',
            toggle:'/api/acmeclient/certificates/toggle/',
            sign:'/api/acmeclient/certificates/sign/',
            revoke:'/api/acmeclient/certificates/revoke/',
            removekey:'/api/acmeclient/certificates/removekey/',
            automation:'/api/acmeclient/certificates/automation/',
            import:'/api/acmeclient/certificates/import/',
        };

        var gridopt = {
            ajax: true,
            selection: true,
            multiSelect: true,
            rowCount:[10,25,50,100,500,1000],
            url: '/api/acmeclient/certificates/search',
            formatters: {
                "commands": function (column, row) {
                    return "<button type=\"button\" title=\"{{ lang._('Edit certificate') }}\" class=\"btn btn-xs btn-default command-edit bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                        "<button type=\"button\" title=\"{{ lang._('Copy certificate') }}\" class=\"btn btn-xs btn-default command-copy bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Issue or renew certificate') }}\" class=\"btn btn-xs btn-default command-sign bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-repeat\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('(Re-) Import certificate') }}\" class=\"btn btn-xs btn-default command-import bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-certificate\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Run automations') }}\" class=\"btn btn-xs btn-default command-automation bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-paper-plane\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Revoke certificate') }}\" class=\"btn btn-xs btn-default command-revoke bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-power-off\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Reset certificate') }}\" class=\"btn btn-xs btn-default command-removekey bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-history\"></span></button>" +
                        "<button type=\"button\" title=\"{{ lang._('Remove certificate') }}\" class=\"btn btn-xs btn-default command-delete bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
                },
                "rowtoggle": function (column, row) {
                    if (parseInt(row[column.id], 2) == 1) {
                        return "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                    } else {
                        return "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                    }
                },
                "certdate": function (column, row) {
                    if (row.lastUpdate == "" || row.lastUpdate == undefined) {
                        return "{{ lang._('pending') }}";
                    } else {
                        var certdate = new Date(row.lastUpdate*1000);
                        return certdate.toLocaleString();
                    }
                },
                "acmestatus": function (column, row) {
                    if (row.statusCode == "" || row.statusCode == undefined) {
                        // fallback to lastUpdate value (unset if cert was never issued/imported)
                        if (row.lastUpdate == "" || row.lastUpdate == undefined) {
                            return "{{ lang._('unknown') }}";
                        } else {
                            return "{{ lang._('OK') }}";
                        }
                    } else if (row.statusCode == "100") {
                        return "{{ lang._('unknown') }}";
                    } else if (row.statusCode == "200") {
                        return "{{ lang._('OK') }}";
                    } else if (row.statusCode == "250") {
                        return "{{ lang._('cert revoked') }}";
                    } else if (row.statusCode == "300") {
                        return "{{ lang._('configuration error') }}";
                    } else if (row.statusCode == "400") {
                        return "{{ lang._('validation failed') }}";
                    } else if (row.statusCode == "500") {
                        return "{{ lang._('internal error') }}";
                    } else {
                        return "{{ lang._('unknown') }}";
                    }
                },
                "acmestatusdate": function (column, row) {
                    if (row.statusLastUpdate == "" || row.statusCode == undefined) {
                        // fallback to lastUpdate value
                        if (row.lastUpdate == "" || row.lastUpdate == undefined) {
                            return "{{ lang._('unknown') }}";
                        } else {
                            var legacydate = new Date(row.lastUpdate*1000);
                            return legacydate.toLocaleString();
                        }
                    } else {
                        var statusdate = new Date(row.statusLastUpdate*1000);
                        return statusdate.toLocaleString();
                    }
                }
            },
        };

        /**
         * reload bootgrid, return to current selected page
         */
        function std_bootgrid_reload(gridId) {
            var currentpage = $("#"+gridId).bootgrid("getCurrentPage");
            $("#"+gridId).bootgrid("reload");
            // absolutely not perfect, bootgrid.reload doesn't seem to support when().done()
            setTimeout(function(){
                $('#'+gridId+'-footer  a[data-page="'+currentpage+'"]').click();
            }, 400);
        }

        /**
         * copy actions for selected items from opnsense_bootgrid_plugin.js
         */
        var grid_certificates = $("#grid-certificates").bootgrid(gridopt).on("loaded.rs.jquery.bootgrid", function (e)
        {
            // toggle all rendered tooltips (once for all)
            $('.bootgrid-tooltip').tooltip();

            // scale footer on resize
            $(this).find("tfoot td:first-child").attr('colspan',$(this).find("th").length - 1);
            $(this).find('tr[data-row-id]').each(function(){
                if ($(this).find('[class*="command-toggle"]').first().data("value") == "0") {
                    $(this).addClass("text-muted");
                }
            });

            // edit dialog id to use
            var editDlg = $(this).attr('data-editDialog');
            var gridId = $(this).attr('id');

            // link Add new to child button with data-action = add
            $(this).find("*[data-action=add]").click(function(){
                if ( gridParams['get'] != undefined && gridParams['add'] != undefined) {
                    var urlMap = {};
                    urlMap['frm_' + editDlg] = gridParams['get'];
                    mapDataToFormUI(urlMap).done(function(){
                        // update selectors
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                        // clear validation errors (if any)
                        clearFormValidation('frm_' + editDlg);
                    });

                    // show dialog for edit
                    $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                    //
                    $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                        saveFormToEndpoint(url=gridParams['add'],
                            formid='frm_' + editDlg, callback_ok=function(){
                                $("#"+editDlg).modal('hide');
                                $("#"+gridId).bootgrid("reload");
                            }, true);
                    });
                }  else {
                    console.log("[grid] action add missing")
                }
            });

            // link delete selected items action
            $(this).find("*[data-action=deleteSelected]").click(function(){
                if ( gridParams['del'] != undefined) {
                    stdDialogConfirm('{{ lang._('Confirm removal') }}',
                        '{{ lang._('Do you want to remove the selected item?') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                        var rows =$("#"+gridId).bootgrid('getSelectedRows');
                        if (rows != undefined){
                            var deferreds = [];
                            $.each(rows, function(key,uuid){
                                deferreds.push(ajaxCall(url=gridParams['del'] + uuid, sendData={},null));
                            });
                            // refresh after load
                            $.when.apply(null, deferreds).done(function(){
                                std_bootgrid_reload(gridId);
                            });
                        }
                    });
                } else {
                    console.log("[grid] action del missing")
                }
            });

        });

        /**
         * copy actions for items from opnsense_bootgrid_plugin.js
         */
        grid_certificates.on("loaded.rs.jquery.bootgrid", function(){
            // edit dialog id to use
            var editDlg = $(this).attr('data-editDialog');
            var gridId = $(this).attr('id');

            // edit item
            grid_certificates.find(".command-edit").on("click", function(e)
            {
                if (editDlg != undefined && gridParams['get'] != undefined) {
                    var uuid = $(this).data("row-id");
                    var urlMap = {};
                    urlMap['frm_' + editDlg] = gridParams['get'] + uuid;
                    mapDataToFormUI(urlMap).done(function () {
                        // update selectors
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                        // clear validation errors (if any)
                        clearFormValidation('frm_' + editDlg);
                    });

                    // show dialog for pipe edit
                    $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                    // define save action
                    $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                        if (gridParams['set'] != undefined) {
                            saveFormToEndpoint(url=gridParams['set']+uuid,
                                formid='frm_' + editDlg, callback_ok=function(){
                                    $("#"+editDlg).modal('hide');
                                    std_bootgrid_reload(gridId);
                                }, true);
                        } else {
                            console.log("[grid] action set missing")
                        }
                    });
                } else {
                    console.log("[grid] action get or data-editDialog missing")
                }
            });

            // copy item, save as new
            grid_certificates.find(".command-copy").on("click", function(e)
            {
                if (editDlg != undefined && gridParams['get'] != undefined) {
                    var uuid = $(this).data("row-id");
                    var urlMap = {};
                    urlMap['frm_' + editDlg] = gridParams['get'] + uuid;
                    mapDataToFormUI(urlMap).done(function () {
                        // update selectors
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                        // clear validation errors (if any)
                        clearFormValidation('frm_' + editDlg);
                    });

                    // show dialog for pipe edit
                    $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                    // define save action
                    $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                        if (gridParams['add'] != undefined) {
                            saveFormToEndpoint(url=gridParams['add'],
                                formid='frm_' + editDlg, callback_ok=function(){
                                    $("#"+editDlg).modal('hide');
                                    std_bootgrid_reload(gridId);
                                }, true);
                        } else {
                            console.log("[grid] action add missing")
                        }
                    });
                } else {
                    console.log("[grid] action get or data-editDialog missing")
                }
            });

            // delete item
            grid_certificates.find(".command-delete").on("click", function(e)
            {
                if (gridParams['del'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirm removal') }}',
                        '{{ lang._('Do you want to remove the selected item?') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                        ajaxCall(url=gridParams['del'] + uuid,
                            sendData={},callback=function(data,status){
                                // reload grid after delete
                                $("#"+gridId).bootgrid("reload");
                            });
                    });
                } else {
                    console.log("[grid] action del missing")
                }
            });

            // toggle item
            grid_certificates.find(".command-toggle").on("click", function(e)
            {
                if (gridParams['toggle'] != undefined) {
                    var uuid=$(this).data("row-id");
                    $(this).addClass("fa-spinner fa-pulse");
                    ajaxCall(url=gridParams['toggle'] + uuid,
                        sendData={},callback=function(data,status){
                            // reload grid after toggle
                            std_bootgrid_reload(gridId);
                        });
                } else {
                    console.log("[grid] action toggle missing")
                }
            });

            // sign cert
            grid_certificates.find(".command-sign").on("click", function(e)
            {
                if (gridParams['sign'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        '{{ lang._('Forcefully issue or renew the selected certificate?') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                        // Handle HAProxy integration (no-op if not applicable)
                        ajaxCall(url="/api/acmeclient/settings/fetchHAProxyIntegration", sendData={}, callback=function(data,status) {
                            ajaxCall(url=gridParams['sign'] + uuid,sendData={},callback=function(data,status){
                                // reload grid after sign
                                $("#"+gridId).bootgrid("reload");
                            });
                        });
                    });
                } else {
                    console.log("[grid] action sign missing")
                }
            });

            // revoke cert
            grid_certificates.find(".command-revoke").on("click", function(e)
            {
                if (gridParams['revoke'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        '{{ lang._('Revoke selected certificate?') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                        ajaxCall(url=gridParams['revoke'] + uuid,
                            sendData={},callback=function(data,status){
                                // reload grid after sign
                                $("#"+gridId).bootgrid("reload");
                            });
                    }, 'danger');
                } else {
                    console.log("[grid] action revoke missing")
                }
            });

            // remove private key
            grid_certificates.find(".command-removekey").on("click", function(e)
            {
                if (gridParams['removekey'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        '{{ lang._('Really remove the private key?%s%sThe certificate will be completely reset. This is useful when the private key has been compromised or when you have changed the key options and want to regenerate the private key.%sNote that you have to revalidate the certificate afterwards in order to create a new private key and a matching certificate.') | format('<br/>', '<br/>', '<br/>') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                        ajaxCall(url=gridParams['removekey'] + uuid,
                            sendData={},callback=function(data,status){
                                // reload grid after sign
                                $("#"+gridId).bootgrid("reload");
                            });
                    }, 'danger');
                } else {
                    console.log("[grid] action removekey missing")
                }
            });

            // run automation
            grid_certificates.find(".command-automation").on("click", function(e)
            {
                if (gridParams['automation'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        '{{ lang._('Rerun all automations for the selected certificate?') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                        ajaxCall(url=gridParams['automation'] + uuid,
                            sendData={},callback=function(data,status){
                                // reload grid after sign
                                $("#"+gridId).bootgrid("reload");
                            });
                    });
                } else {
                    console.log("[grid] action automation missing")
                }
            });

            // import certificate into trust storage
            grid_certificates.find(".command-import").on("click", function(e)
            {
                if (gridParams['import'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        '{{ lang._('(Re-) import the selected certificate and associated CA certificates into the trust storage?') }}',
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                        ajaxCall(url=gridParams['import'] + uuid,
                            sendData={},callback=function(data,status){
                                // reload grid after sign
                                $("#"+gridId).bootgrid("reload");
                            });
                    });
                } else {
                    console.log("[grid] action import missing")
                }
            });

        });

        // Hide options that are irrelevant in this context.
        $('#DialogCertificate').on('shown.bs.modal', function (e) {
            $("#certificate\\.aliasmode").change(function(){
                $(".aliasmode").hide();
                $(".aliasmode_"+$(this).val()).show();
            });
            $("#certificate\\.aliasmode").change();
        })


        /***********************************************************************
         * Commands
         **********************************************************************/

        /**
         * Sign or renew ALL certificates
         */
        $("#signallcertsAct").click(function(){
            //$("#signallcertsAct_progress").addClass("fa fa-spinner fa-pulse");
            // Handle HAProxy integration (no-op if not applicable)
            ajaxCall(url="/api/acmeclient/settings/fetchHAProxyIntegration", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/acmeclient/service/signallcerts", sendData={}, callback=function(data,status) {
                    // when done, disable progress animation.
                    //$("#signallcertsAct_progress").removeClass("fa fa-spinner fa-pulse");
                });
            });
        });

    });

</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    <li {% if showIntro|default('0')=='1' %}class="active"{% endif %}><a data-toggle="tab" id="certificates-introduction" href="#subtab_certificates-introduction"><b>{{ lang._('Introduction') }}</b></a></li>
    <li {% if showIntro|default('0')=='0' %}class="active"{% endif %}><a data-toggle="tab" id="certificates-tab" href="#certificates"><b>{{ lang._('Certificates') }}</b></a></li>
</ul>

<div class="content-box tab-content">

    <div id="subtab_certificates-introduction" class="tab-pane fade {% if showIntro|default('0')=='1' %}in active{% endif %}">
        <div class="col-md-12">
            <h1>{{ lang._('Certificates') }}</h1>
            <p>{{ lang._('This plugin supports an unlimited number of certificates. However, the CA may restrict the number of certificates per week or implement other rate-limits. Retrying a failed validation many times in a row may also cause further attempts to fail due to rate-limits. The CA documentation should contain further information.') }}</p>
            <p>{{ lang._('The following principles apply when managing certificates with this plugin:') }}</p>
            <ul>
              <li>{{ lang._('Certificates must be %svalidated%s by the CA before they can be used. This process runs in the background and may take several minutes to complete. The progress can be monitored by using the %slog files%s.') | format('<b>', '</b>', '<a href="/ui/acmeclient/logs">', '</a>') }}</li>
              <li>{{ lang._('Certificates are stored in the %sOPNsense certificate storage%s. When a CA has completed the validation of a certificate request, the resulting certificate is then automatically imported into the OPNsense certificate storage. The same applies when renewing certificates, the existing entry in the OPNsense certificate storage will automatically be updated.') | format('<a href="/system_certmanager.php">', '</a>') }}</li>
              <li>{{ lang._('When removing a certificate from the plugin, the certificate in the %sOPNsense certificate storage%s is %sNOT removed%s, because it may still be used by a core application or another plugin. Obsolete certificates should be manually removed from the OPNsense certificate storage. Note that when creating a new certificate with the same name, a new certificated will be imported into the OPNsense certificate storage (instead of updating the existing entry).') | format('<a href="/system_certmanager.php">', '</a>', '<b>', '</b>') }}</li>
            </ul>
            <p>{{ lang._('When experiencing issues, try setting the log level to "debug" on the %ssettings%s page.') | format('<a href="/ui/acmeclient#settings">', '</a>') }}</p>
        </div>
    </div>

    <div id="certificates" class="tab-pane fade {% if showIntro|default('0')=='0' %}in active{% endif %}">
        <table id="grid-certificates" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCertificate">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Common Name') }}</th>
                <th data-column-id="altNames" data-type="string">{{ lang._('Multi-Domain (SAN)') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="lastUpdate" data-type="string" data-formatter="certdate">{{ lang._('Issue/Renewal Date') }}</th>
                <th data-column-id="statusCode" data-type="string" data-formatter="acmestatus">{{ lang._('Last ACME Status') }}</th>
                <th data-column-id="statusLastUpdate" data-type="string" data-formatter="acmestatusdate">{{ lang._('Last ACME Run') }}</th>
                <th data-column-id="commands" data-width="13em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="signallcertsAct" type="button"><b>{{ lang._('Issue/Renew All Certificates') }}</b><i id="signallcertsAct_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
    </div>
</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogCertificate,'id':'DialogCertificate','label':lang._('Edit Certificate')])}}
