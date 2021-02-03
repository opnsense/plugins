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
                        buttons += "<button type=\"button\" title=\"Set administrative state to ready. Puts the server in normal mode.\" class=\"btn btn-xs btn-default command-set-state\" data-state=\"ready\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-check\"></span></button>"
                        buttons += " <button type=\"button\" title=\"Set administrative state to drain. Removes the server from load balancing but still allows it to be health checked and to accept new persistent connections\" class=\"btn btn-xs btn-default command-set-state\" data-state=\"drain\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-sort-amount-desc\"></span></button>"
                        buttons += " <button type=\"button\" title=\"Set administrative state to maintenance. Disables any traffic to the server as well as any health checks.\" class=\"btn btn-xs btn-default command-set-state\" data-state=\"maint\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-wrench\"></span></button>"
                        buttons += " <button type=\"button\" title=\"Change a server's weight.\" class=\"btn btn-xs btn-default command-set-weight\" data-weight=\"" + row.weight + "\" data-row-id=\"" + row.id + "\"><span class=\"fa fa-balance-scale\"></span></button>"
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
                    question += '{{ lang._('Set administrative state for all selected server?') }} </br></br>';

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
                    question += '{{ lang._('Set weight for all selected server?') }} </br></br>';

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
    });
</script>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
    <li class="active"><a data-toggle="tab" href="#server"><b>{{ lang._('Server') }}</b></a></li>
</ul>

<div class="content-box tab-content">
    <div id="server" class="tab-pane fade in active">
        <!-- tab page "server" -->
        <table id="grid-status" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
            <tr>
                <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('id') }}</th>
                <th data-column-id="pxname" data-type="string">{{ lang._('Proxy') }}</th>
                <th data-column-id="svname" data-type="string">{{ lang._('Server') }}</th>
                <th data-column-id="addr" data-type="string">{{ lang._('Address') }}</th>
                <th data-column-id="status" data-type="string">{{ lang._('Status') }}</th>
                <th data-column-id="check_status" data-type="string">{{ lang._('Check Status') }}</th>
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
                    <button data-action="setStateBulk" title="Set administrative state to ready for all selected items." data-state="ready" type="button" class="btn btn-xs btn-default"><span class="fa fa-check"></span></button>
                    <button data-action="setStateBulk" title="Set administrative state to drain for all selected items." data-state="drain" type="button" class="btn btn-xs btn-default"><span class="fa fa-sort-amount-desc"></span></button>
                    <button data-action="setStateBulk" title="Set administrative state to maintenance for all selected items." data-state="maint" type="button" class="btn btn-xs btn-default"><span class="fa fa-wrench"></span></button>
                    <button data-action="setWeightBulk" data-weight="" type="button" class="btn btn-xs btn-default"><span class="fa fa-balance-scale"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog_processing") }}
