{#

Copyright (C) 2021 Andreas Stuerz
OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
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

        // Get cronjobs
        var cronjobs_data_get_map = {'frm_cronjobs':"/api/haproxy/maintenance/get"};
        // load initial data
        mapDataToFormUI(cronjobs_data_get_map).done(function(data){
            // Add link to cron job edit page: First iterate over all cron settings.
            // FIXME: Oh boy, this is ugly. Should be refactored.
            $.each(data.frm_cronjobs.haproxy.maintenance.cronjobs, function(key, value) {
                // Check if cron setting is enabled.
                if (value == 1) {
                    // Find the matching cron job reference.
                    cron_cfg = key + 'Cron';
                    $.each(data.frm_cronjobs.haproxy.maintenance.cronjobs, function(cronkey, cronvalue) {
                        // Check if it is the correct entry for this cron setting.
                        if (cronkey == cron_cfg) {
                            // Get the cron job UUID.
                            $.each(cronvalue, function(refkey, refvalue) {
                                // Only the "selected" item belongs to this entry.
                                if (refvalue.selected == 1) {
                                    // Find the correct container for this cron setting.
                                    content_id = "[id=\"haproxy.maintenance.cronjobs." + key + "\"]";
                                    $(content_id).each(function(){
                                        // Finally add the link to the cron job edit page.
                                        cron_link = "<br><a href=\"/ui/cron/item/open/" + refkey + "\"><span class=\"fa fa-pencil\"></span> {{ lang._('Configure cron job') }}</a>";
                                        $(this).closest("td").append(cron_link);
                                    });
                                };
                            });
                        };
                    });
                };
            });

            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // Save & reconfigure cron to activate changes
        $('[id*="saveAndReconfigureAct"]').each(function(){
            $(this).click(function(){
                // set progress animation
                $('[id*="saveAndReconfigureAct_progress"]').each(function(){
                    $(this).addClass("fa fa-spinner fa-pulse");
                });

                // extract the form id from the button id
                var frm_id = "frm_" + $(this).attr("id").split('_')[1]

                // save data for this tab
                saveFormToEndpoint(url="/api/haproxy/maintenance/set",formid=frm_id,callback_ok=function(){
                    // Handle cron integration
                    ajaxCall(url="/api/haproxy/maintenance/fetchCronIntegration", sendData={}, callback=function(data,status) {
                    });

                    // when done, disable progress animation
                    $('[id*="saveAndReconfigureAct_progress"]').each(function(){
                        $(this).removeClass("fa fa-spinner fa-pulse");
                        // reload page to show or hide links to cron edit page
                        setTimeout(function () {
                            window.location.reload(true)
                        }, 300);
                    });
                });
            });
        });

        // grid-certificates
        function syncErrorMessage(modified, deleted) {
            message = ``;
            modified.forEach(function(item) {
                message += `<b>{{ lang._('Public Service Name:') }} ${item.frontend_name}</b></br></br>`;

                item.update.forEach(function(update) {
                    message += `<b>{{ lang._('UPDATE / NEW:') }} ${update.cert}:</b></br>`;
                    message += `<pre>${update.messages.join("")}</pre>`;
                });

                item.add.forEach(function(add) {
                    message += `<b>{{ lang._('ADD:') }} ${add.cert}:</b></br>`;
                    message += `<pre>${add.messages.join("</br>")}</pre>`;
                });

                item.remove.forEach(function(remove) {
                    message += `<b>{{ lang._('REMOVE:') }} ${remove.cert}:</b></br>`;
                    message += `<pre>${remove.messages.join("</br>")}</pre>`;
                });
                message += `</br>`;
            });

            message += `<b>{{ lang._('CERTIFICATES:') }}</b></br></br>`;
            deleted.forEach(function(del) {
                message += `<b>{{ lang._('DELETE:') }} ${del.cert}:</b></br>`;
                message += `<pre>${del.messages.join("</br>")}</pre>`;
            });

            return message;
        }

        function showDiffDialog(payload) {
            $.post('/api/haproxy/maintenance/certDiff', payload, function(data) {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_INFO,
                    title: "{{ lang._('Diff between configured and active SSL certificates') }}",
                    message: `<pre>${data}</pre>`,
                    buttons: [{
                        label: '{{ lang._('Close') }}',
                        action: function(dialog){
                          dialog.close();
                        }
                    }]
                });
            });
        }

        function applyDiffDialog(payload, requested_count) {
            $.post('/api/haproxy/maintenance/certActions', payload, function(data_actions) {
                question = ''
                question += `<pre>${data_actions}</pre>`;
                question += '<b>{{ lang._('Apply SSL certificates to HAProxy?') }}</b></br></br>';

                stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                    question,
                    '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                    $.post('/api/haproxy/maintenance/certSync', payload, function(data) {
                        modified_count = data.result.add_count + data.result.remove_count + data.result.update_count;

                        if (requested_count != modified_count) {
                            var error_msg = syncErrorMessage(data.result.modified, data.result.deleted);
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_DANGER,
                                title: "{{ lang._('Error applying SSL certificates to HAProxy') }}",
                                message: error_msg,
                                buttons: [{
                                    label: '{{ lang._('Close') }}',
                                    action: function(dialog){
                                      dialog.close();
                                    }
                                }]
                            });
                        }
                        $("#grid-certificates").bootgrid("reload");
                    });
                });
            });
        }

        $("#grid-certificates").bootgrid('destroy');
        var grid_certificates = $("#grid-certificates").UIBootgrid({
            search: '/api/haproxy/maintenance/searchCertificateDiff',
            options: {
                ajax: true,
                selection: true,
                multiSelect: true,
                keepSelection: true,
                rowCount:[10,25,50,100,500,1000],
                searchSettings: {
                    delay: 250,
                    characters: 1
                },
                formatters: {
                    "commands": function (column, row) {
                        buttons = ""
                        buttons += "<button type=\"button\"  data-action=\"showDiff\" title=\"{{ lang._('Show diff') }}\" class=\"btn btn-xs btn-default\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-info-circle\"></span></button>"
                        buttons += " <button type=\"button\" data-action=\"applyDiff\" title=\"{{ lang._('Apply changes') }}\" class=\"btn btn-xs btn-default\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-refresh\"></span></button>"
                        return buttons;
                    },
                },
            }
        }).on("loaded.rs.jquery.bootgrid", function(){
            grid_certificates.find("*[data-action=showDiff]").off().on("click", function(e) {
                var row_id = $(this).data("row-id");
                var frontend_ids = row_id;
                var payload = {
                  'frontend_ids': frontend_ids,
                };
                showDiffDialog(payload);
            });

            grid_certificates.find("*[data-action=applyDiff]").off().on("click", function(e) {
                var row_id = $(this).data("row-id");
                var rows = $("#grid-certificates").bootgrid("getCurrentRows");
                var row =  rows.filter(function(row) {
			return row.id == row_id;
                })[0];
                var requested_count = row.total_count;
                var frontend_ids = row.id
                var payload = {
                  'frontend_ids': frontend_ids,
                };

                applyDiffDialog(payload, requested_count);
            });

            grid_certificates.find("*[data-action=showDiffBulk]").off().on("click", function(e) {
                var rows = $("#grid-certificates").bootgrid("getSelectedRows");
                var payload = {
                  'frontend_ids': rows.join()
                };
                if (rows != undefined && rows.length > 0) {
                    showDiffDialog(payload);
                }
            });

            grid_certificates.find("*[data-action=applyDiffBulk]").off().on("click", function(e) {
                var rows = $("#grid-certificates").bootgrid("getSelectedRows");
                var frontend_ids = rows.join();
                var all_rows = $("#grid-certificates").bootgrid("getCurrentRows");
                var requested_count = 0;
                all_rows.forEach(function(row) {
                    if (rows.indexOf(row.id) != -1) {
                        requested_count = requested_count + row.total_count;
                    }
                });
                var payload = {
                  'frontend_ids': frontend_ids,
                };
                if (rows != undefined && rows.length > 0) {
                    applyDiffDialog(payload, requested_count);
                }
            });
        });

        // Apply all changes
        $("*[data-action=applyDiffAll]").off().on("click", function(e) {
            $('[id*="applyDiffAll_progress"]').each(function(){
                $(this).addClass("fa fa-spinner fa-pulse");
            });
            var all_rows = $("#grid-certificates").bootgrid("getCurrentRows");
            var requested_count = 0;
            all_rows.forEach(function(row) {
                requested_count = requested_count + row.total_count;
            });

            var payload = {};
            $.post('/api/haproxy/maintenance/certSyncBulk', payload, function(data) {
                modified_count = data.result.add_count + data.result.remove_count + data.result.update_count;
                if (requested_count != modified_count) {
                    var error_msg = syncErrorMessage(data.result.modified, data.result.deleted);
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_DANGER,
                        title: "{{ lang._('Error applying SSL certificates to HAProxy') }}",
                        message: error_msg,
                        buttons: [{
                            label: '{{ lang._('Close') }}',
                            action: function(dialog){
                              dialog.close();
                            }
                        }]
                    });
                }
                $("#grid-certificates").bootgrid("reload");
                $("#applyDiffAll_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        // grid-status
        $("#grid-status").bootgrid('destroy');
        var grid_status = $("#grid-status").UIBootgrid({
            search: '/api/haproxy/maintenance/searchServer',
            options: {
                ajax: true,
                selection: true,
                multiSelect: true,
                keepSelection: true,
                rowCount:[10,25,50,100,500,1000],
                searchSettings: {
                    delay: 250,
                    characters: 1
                },
                formatters: {
                    "commands": function (column, row) {
                        buttons = ""
                        buttons += "<button type=\"button\"  title=\"{{ lang._('Set state to ready') }}\" class=\"btn btn-xs btn-default command-set-state\" data-state=\"ready\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-check\"></span></button>"
                        buttons += " <button type=\"button\" title=\"{{ lang._('Set state to drain') }}\" class=\"btn btn-xs btn-default command-set-state\" data-state=\"drain\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-sort-amount-desc\"></span></button>"
                        buttons += " <button type=\"button\" title=\"{{ lang._('Set state to maintenance') }}\" class=\"btn btn-xs btn-default command-set-state\" data-state=\"maint\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-wrench\"></span></button>"
                        buttons += " <button type=\"button\" title=\"{{ lang._('Change server weight') }}\" class=\"btn btn-xs btn-default command-set-weight\" data-weight=\"" + row.weight + "\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-balance-scale\"></span></button>"
                        return buttons;
                    },
                },
            }
        }).on("loaded.rs.jquery.bootgrid", function(){
            // set single - server state
            grid_status.find(".command-set-state").off().on("click", function(e) {
                var uuid = $(this).data("row-id");
                var backend = uuid.split("/")[0];
                var server = uuid.split("/")[1];
                var state = $(this).data("state");
                var payload = {
                  'backend': backend,
                  'server': server,
                  'state': state
                };

                question = '<b>{{ lang._('Server: ') }}' + uuid + '</b></br>';
                question += '<b>{{ lang._('State: ') }}' + state + '</b></br></br>';
                question += '{{ lang._('Set administrative state for this server?') }} </br></br>';

                stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                    question,
                    '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                        $.post('/api/haproxy/maintenance/serverState', payload, function(data) {
                            if (data.status != 'ok') {
                                BootstrapDialog.show({
                                    type: BootstrapDialog.TYPE_DANGER,
                                    title: "{{ lang._('Error setting HAProxy server administrative state') }}",
                                    message: data.message,
                                    buttons: [{
                                        label: '{{ lang._('Close') }}',
                                        action: function(dialog){
                                          dialog.close();
                                        }
                                    }]
                                });
                            } else {
                                $("#grid-status").bootgrid("reload");
                            }
                        });
                });
            });

            // set single - server weight
            grid_status.find(".command-set-weight").off().on("click", function(e) {
                var uuid = $(this).data("row-id");
                var backend = uuid.split("/")[0];
                var server = uuid.split("/")[1];
                var currentWeight = $(this).data("weight");

                question = '<b>{{ lang._('Server: ') }}' + uuid + '</b></br></br>';
                question += '<b>{{ lang._('Weight: ') }}</b>';
                question += '<div class="form-group" style="display: block;">';
                question += '<input class="form-control" id="newWeight" value="' + currentWeight  + '" type="text"/>';
                question += '</div>';
                question += '{{ lang._('Set weight for this server?') }} </br></br>';

                stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                    question,
                    '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {

                    var payload = {
                      'backend': backend,
                      'server': server,
                      'weight': $("#newWeight").val()
                    };

                    $.post('/api/haproxy/maintenance/serverWeight', payload, function(data) {
                        if (data.status != 'ok') {
                            BootstrapDialog.show({
                                type: BootstrapDialog.TYPE_DANGER,
                                title: "{{ lang._('Error setting HAProxy server weight') }}",
                                message: data.message,
                                buttons: [{
                                    label: '{{ lang._('Close') }}',
                                    action: function(dialog){
                                      dialog.close();
                                    }
                                }]
                            });
                        } else {
                            $("#grid-status").bootgrid("reload");
                        }
                    });
                });
            });

            // set bulk - server state
            grid_status.find("*[data-action=setStateBulk]").off().on("click", function(e) {
                var rows = $("#grid-status").bootgrid("getSelectedRows");
                var server_ids = rows.join()
                var state = $(this).data("state");
                var payload = {
                  'server_ids': server_ids,
                  'state': state
                };

                if (rows != undefined && rows.length > 0) {
                    question = '<b>{{ lang._('Selected server: ') }}</b></br>';
                    question += '<ul>';
                    $.each(rows, function(key, id){
                        question += '<li>' + id + '</li>';
                    });
                    question += '</ul>';
                    question += '<b>{{ lang._('State: ') }}' + state + '</b></br></br>';
                    question += '{{ lang._('Set administrative state for all selected servers?') }} </br></br>';

                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        question,
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                            $.post('/api/haproxy/maintenance/serverStateBulk', payload, function(data) {
                                if (data.status != 'ok') {
                                    BootstrapDialog.show({
                                        type: BootstrapDialog.TYPE_DANGER,
                                        title: "{{ lang._('Error setting HAProxy server administrative state') }}",
                                        message: data.message,
                                        buttons: [{
                                            label: '{{ lang._('Close') }}',
                                            action: function(dialog){
                                              dialog.close();
                                              // reload - because some are successfully executed
                                              $("#grid-status").bootgrid("reload");
                                            }
                                        }]
                                    });
                                } else {
                                    $("#grid-status").bootgrid("deselect");
                                    $("#grid-status").bootgrid("reload");
                                }
                            });
                    });
                }
            });

            // set bulk - server weight
            grid_status.find("*[data-action=setWeightBulk]").off().on("click", function(e) {
                var rows = $("#grid-status").bootgrid("getSelectedRows");
                var server_ids = rows.join()

                if (rows != undefined && rows.length > 0) {
                    question = '<b>{{ lang._('Selected server: ') }}</b></br>';
                    question += '<ul>';
                    $.each(rows, function(key, id){
                        question += '<li>' + id + '</li>';
                    });
                    question += '</ul>';
                    question += '<b>{{ lang._('Weight: ') }}</b>';
                    question += '<div class="form-group" style="display: block;">';
                    question += '<input class="form-control" id="newBulkWeight" value="" type="text"/>';
                    question += '</div>';
                    question += '{{ lang._('Set weight for all selected servers?') }} </br></br>';

                    stdDialogConfirm('{{ lang._('Confirmation Required') }}',
                        question,
                        '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function() {
                            var payload = {
                              'server_ids': server_ids,
                              'weight': $("#newBulkWeight").val()
                            };

                            $.post('/api/haproxy/maintenance/serverWeightBulk', payload, function(data) {
                                if (data.status != 'ok') {
                                    BootstrapDialog.show({
                                        type: BootstrapDialog.TYPE_DANGER,
                                        title: "{{ lang._('Error setting HAProxy server weight') }}",
                                        message: data.message,
                                        buttons: [{
                                            label: '{{ lang._('Close') }}',
                                            action: function(dialog){
                                              dialog.close();
                                              // reload - because some are successfully executed
                                              $("#grid-status").bootgrid("reload");

                                            }
                                        }]
                                    });
                                } else {
                                    $("#grid-status").bootgrid("deselect");
                                    $("#grid-status").bootgrid("reload");
                                }
                            });
                    });
                }
            });

        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });
</script>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    <li class="active"><a data-toggle="tab" href="#server"><b>{{ lang._('Servers') }}</b></a></li>
    <li><a data-toggle="tab" href="#ssl-certs"><b>{{ lang._('SSL Certificates') }}</b></a></li>
    <li><a data-toggle="tab" href="#cronjobs"><b>{{ lang._('Cron Jobs') }}</b></a></li>
</ul>

<div class="content-box tab-content">
    <div id="server" class="tab-pane fade in active">
        <!-- tab page "server" -->
        <table id="grid-status" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
            <tr>
                <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('id') }}</th>
                <th data-column-id="pxname" data-width="9em" data-type="string">{{ lang._('Virtual Service') }}</th>
                <th data-column-id="svname" data-width="9em" data-type="string">{{ lang._('Real Server') }}</th>
                <th data-column-id="addr" data-type="string">{{ lang._('Address') }}</th>
                <th data-column-id="status" data-type="string">{{ lang._('Status') }}</th>
                <th data-column-id="check_status" data-width="8em" data-type="string">{{ lang._('Check Status') }}</th>
                <th data-column-id="weight" data-type="string">{{ lang._('Weight') }}</th>
                <th data-column-id="scur" data-type="string">{{ lang._('Sessions') }}</th>
                <th data-column-id="bin" data-type="string">{{ lang._('Bytes in') }}</th>
                <th data-column-id="bout" data-type="string">{{ lang._('Bytes out') }}</th>
                <th data-column-id="act" data-type="string">{{ lang._('Active') }}</th>
                <th data-column-id="downtime" data-type="string">{{ lang._('Downtime') }}</th>
                <th data-column-id="lastchg" data-type="string">{{ lang._('Last Change') }}</th>
                <th data-column-id="commands" data-width="8em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="setStateBulk" title="{{ lang._('Set state to ready (bulk)') }}" data-state="ready" type="button" class="btn btn-xs btn-default"><span class="fa fa-check"></span></button>
                    <button data-action="setStateBulk" title="{{ lang._('Set state to drain (bulk)') }}" data-state="drain" type="button" class="btn btn-xs btn-default"><span class="fa fa-sort-amount-desc"></span></button>
                    <button data-action="setStateBulk" title="{{ lang._('Set state to maintenance (bulk)') }}" data-state="maint" type="button" class="btn btn-xs btn-default"><span class="fa fa-wrench"></span></button>
                    <button data-action="setWeightBulk" title="{{ lang._('Change server weight (bulk)') }}" data-weight="" type="button" class="btn btn-xs btn-default"><span class="fa fa-balance-scale"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
          <p>{{ lang._("%sThe following commands are available to change a server's state in runtime:%s") | format('<b>', '</b>') }}</p>
          <ul>
            <li><span class="fa fa-check"></span> {{ lang._('%sSet state to ready:%s This puts the server in normal mode.') | format('<b>', '</b>') }}</li>
            <li><span class="fa fa-sort-amount-desc"></span> {{ lang._('%sSet state to drain:%s This removes the server from load balancing. Health checks will continue to run and it still accepts new persistent connections.') | format('<b>', '</b>') }}</li>
            <li><span class="fa fa-wrench"></span> {{ lang._('%sSet state to maintenance:%s This disables any traffic to the server. Health checks will also be disabled.') | format('<b>', '</b>') }}</li>
            <li><span class="fa fa-balance-scale"></span> {{ lang._("%sChange server weight:%s Adjust the server's weight relative to other servers. Servers will receive a load proportional to their weight.") | format('<b>', '</b>') }}</li>
          </ul>
          <p>{{ lang._('%sNOTE:%s These changes will not be persisted across restarts of HAProxy.') | format('<b>', '</b>') }}</p>
        </div>
    </div>

    <div id="ssl-certs" class="tab-pane fade in">
        <!-- tab page "ssl-certs" -->
        <table id="grid-certificates" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
            <tr>
                <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('id') }}</th>
                <th data-column-id="frontend_name" data-type="string">{{ lang._('Public Service Name') }}</th>
                <th data-column-id="update_count" data-type="string">{{ lang._('Update Certificates') }}</th>
                <th data-column-id="add_count" data-type="string">{{ lang._('Add Certificates') }}</th>
                <th data-column-id="remove_count" data-type="string">{{ lang._('Remove Certificates') }}</th>
                <th data-column-id="commands" data-width="8em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="showDiffBulk" title="{{ lang._('Show diff (bulk)') }}" type="button" class="btn btn-xs btn-default"><span class="fa fa-info-circle"></span></button>
                    <button data-action="applyDiffBulk" title="{{ lang._('Apply changes (bulk)') }}" type="button" class="btn btn-xs btn-default"><span class="fa fa-refresh"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <hr/>
            <button data-action="applyDiffAll" class="btn btn-primary" type="button"><b>{{ lang._('Apply') }}</b><i id="applyDiffAll_progress" class=""></i></button>
            <br/>
            <br/>
        </div>
        <div class="col-md-12">
          <p>{{ lang._("%sApply SSL certificate changes in runtime:%s") | format('<b>', '</b>') }}</p>
          <ul>
            <li><span class="fa fa-info-circle"></span> {{ lang._('%sShow diff:%s Show difference between configured SSL certificates and SSL certificates from the running HAProxy service.') | format('<b>', '</b>') }}</li>
            <li><span class="fa fa-refresh"></span> {{ lang._('%sApply changes:%s Apply all changes by syncing all shown SSL certificates into running HAProxy service.') | format('<b>', '</b>') }}</li>
          </ul>
          <p>{{ lang._('%sNOTE:%s Changes can only be applied for Public Services that already exist in the running HAProxy service. When adding or removing Public Services HAProxy must be reloaded or restarted.') | format('<b>', '</b>') }}</p>
        </div>
    </div>

    <div id="cronjobs" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':maintenanceCronjobsForm,'id':'frm_cronjobs'])}}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAndReconfigureAct_cronjobs" type="button"><b>{{ lang._('Apply') }}</b> <i id="saveAndReconfigureAct_progress"></i></button>
            </div>
            <div class="col-md-12">
              <br/>
              {{ lang._('%sNOTE:%s When enabling multiple cron jobs, please adjust them so that they do not run at the same time. Check the %scron settings page%s for more cron job details and additional customization options.') | format('<b>', '</b>', '<a href="/ui/cron">', '</a>') }}
              <br/>
            </div>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog_processing") }}
