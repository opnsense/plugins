{#
 # Copyright (C) 2026 Cedrik Pischem
 # Copyright (C) 2026 François Maymil
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 #    notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script>
    $( document ).ready(function() {
        let poll = null;
        let errorMessage = function(data) {
            const value = data.error !== undefined ? data.error : data.status;
            return $("<span />").text(htmlDecode(value === undefined ? '' : String(value)));
        };
        let jobAction = function(action, jobId) {
            ajaxCall("/api/iperf/client/" + action + "/" + jobId, {}, function (data) {
                if (data.status !== 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Iperf Client') }}",
                        message: errorMessage(data)
                    });
                }
                $("#grid-jobs").bootgrid("reload");
            });
        };
        let viewResult = function(jobId) {
            ajaxGet("/api/iperf/client/view/" + jobId, {}, function (data) {
                if (data.status !== 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Iperf Client') }}",
                        message: errorMessage(data)
                    });
                } else {
                    BootstrapDialog.show({
                        size: BootstrapDialog.SIZE_WIDE,
                        title: "{{ lang._('Iperf Client Results') }}",
                        message: $("<pre style='white-space:pre-wrap;word-break:break-word;' />").text(
                            htmlDecode(JSON.stringify(data.data, null, 2))
                        )
                    });
                }
            });
        };
        $("#grid-jobs").UIBootgrid({
            search: '/api/iperf/client/search_jobs',
            datakey: 'id',
            options: {
                selection: false,
                formatters: {
                    "direction": function (column, row) {
                        return row.reverse == '1' ? "{{ lang._('Download') }}" : "{{ lang._('Upload') }}";
                    },
                    "error": function (column, row) {
                        const value = row.error == 'no_result' && row.error_seconds !== undefined ?
                            row.error + ' (' + row.error_seconds + 's)' : row.error;
                        return document.createTextNode(
                            htmlDecode(value === undefined ? '' : String(value))
                        );
                    },
                    "rate": function (column, row) {
                        return row[column.id] === '' ? '' :
                            byteFormat(row[column.id], 2, true).replace(/ B$/, ' G') + 'bit/s';
                    },
                    "status": function (column, row) {
                        if (row.status == 'running') {
                            return '<i class="fa fa-fw fa-spinner fa-pulse"></i>';
                        } else if (row.status == 'error') {
                            return '<i class="fa fa-fw fa-exclamation-triangle"></i>';
                        } else {
                            return '<i class="fa fa-fw fa-check"></i>';
                        }
                    }
                }
            },
            commands: {
                start: {
                    filter: (cell) => commandFilter(cell, 'start'),
                    title: "{{ lang._('Start') }}",
                    method: function() {
                        jobAction('start', $(this).data('row-id'));
                    },
                    classname: 'fa fa-fw fa-play',
                    requires: []
                },
                stop: {
                    filter: (cell) => commandFilter(cell, 'stop'),
                    title: "{{ lang._('Stop') }}",
                    method: function() {
                        jobAction('stop', $(this).data('row-id'));
                    },
                    classname: 'fa fa-fw fa-stop',
                    requires: []
                },
                view: {
                    title: "{{ lang._('View results') }}",
                    method: function() {
                        viewResult($(this).data('row-id'));
                    },
                    classname: 'fa fa-fw fa-file-text-o',
                    requires: []
                },
                delete: {
                    title: "{{ lang._('Remove') }}",
                    method: function() {
                        jobAction('remove', $(this).data('row-id'));
                    },
                    classname: 'fa fa-fw fa-trash-o',
                    requires: []
                }
            }
        }).on("loaded.rs.jquery.bootgrid", function () {
            /* refresh only while a job is running */
            clearTimeout(poll);
            if ($("#grid-jobs").bootgrid("getCurrentRows").some(row => row.status == 'running')) {
                poll = setTimeout(function(){ $("#grid-jobs").bootgrid("reload"); }, 5000);
            }
        });

        function commandFilter(cell, action) {
            const active = cell.getData().status == 'running';
            return action == 'stop' ? active : !active;
        }

        mapDataToFormUI({'frm_ClientSettings': "/api/iperf/client/get"}).done(function(){
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_start_new").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                let callb = function (data) {
                    if (data.status !== undefined && data.status !== 'ok') {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: "{{ lang._('Iperf Client') }}",
                            message: errorMessage(data)
                        });
                    }
                    $("#grid-jobs").bootgrid("reload");
                    dfObj.reject(); /* do not execute regular data_endpoint */
                }
                saveFormToEndpoint("/api/iperf/client/set", 'frm_ClientSettings', callb, true, callb);
                return dfObj;
            }
        });
    });
</script>

<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':clientForm,'id':'frm_ClientSettings'])}}
    {{ partial('layout_partials/base_apply_button', {'button_id': 'btn_start_new', 'data_endpoint': '', 'data_label': lang._('Start')}) }}
</div>
<div class="content-box">
    <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="status" data-width="2em" data-sortable="false" data-formatter="status">&nbsp;</th>
                <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="started" data-type="string" data-order="desc">{{ lang._('Started') }}</th>
                <th data-column-id="server" data-type="string">{{ lang._('Server') }}</th>
                <th data-column-id="port" data-type="string">{{ lang._('Port') }}</th>
                <th data-column-id="interface" data-type="string">{{ lang._('Source interface') }}</th>
                <th data-column-id="protocol" data-type="string">{{ lang._('Protocol') }}</th>
                <th data-column-id="parallel" data-type="string">{{ lang._('Streams') }}</th>
                <th data-column-id="reverse" data-type="string" data-formatter="direction">{{ lang._('Direction') }}</th>
                <th data-column-id="sent" data-type="numeric" data-formatter="rate">{{ lang._('Sent') }}</th>
                <th data-column-id="received" data-type="numeric" data-formatter="rate">{{ lang._('Received') }}</th>
                <th data-column-id="error" data-type="string" data-formatter="error">{{ lang._('Error') }}</th>
                <th data-column-id="commands" data-width="12em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
