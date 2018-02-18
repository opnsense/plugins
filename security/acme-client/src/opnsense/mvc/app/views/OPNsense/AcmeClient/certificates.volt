{#

(Partially duplicates code from opnsense_bootgrid_plugin.js.)

Copyright (C) 2017 Frank Wall
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
            set:'/api/acmeclient/certificates/set/',
            add:'/api/acmeclient/certificates/add/',
            del:'/api/acmeclient/certificates/del/',
            toggle:'/api/acmeclient/certificates/toggle/',
            sign:'/api/acmeclient/certificates/sign/',
            revoke:'/api/acmeclient/certificates/revoke/',
        };

        var gridopt = {
            ajax: true,
            selection: true,
            multiSelect: true,
            rowCount:[10,25,50,100,500,1000],
            url: '/api/acmeclient/certificates/search',
            formatters: {
                "commands": function (column, row) {
                    return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-copy\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>" +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-sign\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-repeat\"></span></button>" +
                        "<button type=\"button\" class=\"btn btn-xs btn-default command-revoke\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-power-off\"></span></button>";
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
            // TODO: this should block other sign/revoke actions
            grid_certificates.find(".command-sign").on("click", function(e)
            {
                if (gridParams['sign'] != undefined) {
                    var uuid=$(this).data("row-id");
                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        '{{ lang._('Forcefully (re-)issue the selected certificate?') }}',
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
            // TODO: this should block other sign/revoke actions
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

        });

        /***********************************************************************
         * Commands
         **********************************************************************/

        /**
         * Sign or renew ALL certificates
         * TODO: this should block other sign/revoke actions
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

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#certificates">{{ lang._('Certificates') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="certificates" class="tab-pane fade in active">
        <table id="grid-certificates" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCertificate">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Common Name') }}</th>
                <th data-column-id="altNames" data-type="string">{{ lang._('Multi-Domain (SAN)') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="lastUpdate" data-type="string" data-formatter="certdate">{{ lang._('Issue/Renewal Date') }}</th>
                <th data-column-id="statusCode" data-type="string" data-formatter="acmestatus">{{ lang._('Last Acme Status') }}</th>
                <th data-column-id="statusLastUpdate" data-type="string" data-formatter="acmestatusdate">{{ lang._('Last Acme Run') }}</th>
                <th data-column-id="commands" data-width="11em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="signallcertsAct" type="button"><b>{{ lang._('Issue/Renew Certificates Now') }}</b><i id="signallcertsAct_progress" class=""></i></button>
        <br/>
    </div>
    <div class="col-md-12">
        {{ lang._('Use the Issue/Renew button to let the acme client automatically issue any new certificate and renew existing certificates (only if required). If you want to only issue/renew or revoke a single certificate, use the buttons in the Commands column. This will forcefully issue/renew the certificate, even if it is not required.') }} <b>{{ lang._('The process may take some time and thus will run in the background, you will not get any notification in the GUI. Use the log file to monitor the progress and to see error messages.') }}</b>
        <br/><br/>
    </div>
</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogCertificate,'id':'DialogCertificate','label':lang._('Edit Certificate')])}}
