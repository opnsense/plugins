{#
 # Copyright (C) 2026 Cedrik Pischem
 # Copyright (C) 2017 Fabian Franz
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
        let jobAction = function(action, jobId) {
            ajaxCall("/api/iperf/instance/" + action + "/" + jobId, {}, function (data) {
                if (data.status !== 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Iperf Server') }}",
                        message: data.error !== undefined ? data.error : data.status
                    });
                }
                $("#grid-jobs").bootgrid("reload");
            });
        };
        $("#grid-jobs").UIBootgrid({
            search: '/api/iperf/instance/search_jobs',
            datakey: 'id',
            options: {
                selection: false,
                formatters: {
                    "status": function (column, row) {
                        if (row.status == 'running') {
                            return '<i class="fa fa-fw fa-spinner fa-pulse"></i>';
                        } else if (row.status == 'listening') {
                            return '<i class="fa fa-fw fa-circle text-success" title="{{ lang._('Listening') }}"></i>';
                        } else if (row.status == 'stopped') {
                            return '<i class="fa fa-fw fa-stop text-muted" title="{{ lang._('Stopped') }}"></i>';
                        } else if (row.status == 'error') {
                            return '<i class="fa fa-fw fa-circle text-danger" title="{{ lang._('Error') }}"></i>';
                        } else {
                            return '<i class="fa fa-fw fa-check"></i>';
                        }
                    }
                }
            },
            commands: {
                start: {
                    title: "{{ lang._('Start') }}",
                    method: function() {
                        jobAction('start', $(this).data('row-id'));
                    },
                    classname: 'fa fa-fw fa-play',
                    requires: []
                },
                stop: {
                    title: "{{ lang._('Stop') }}",
                    method: function() {
                        jobAction('stop', $(this).data('row-id'));
                    },
                    classname: 'fa fa-fw fa-stop',
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
            /* refresh while a server is listening or handling a test */
            clearTimeout(poll);
            if ($("#grid-jobs").bootgrid("getCurrentRows").some(
                row => row.status == 'listening' || row.status == 'running'
            )) {
                poll = setTimeout(function(){ $("#grid-jobs").bootgrid("reload"); }, 5000);
            }
        });

        mapDataToFormUI({'frm_InstanceSettings': "/api/iperf/instance/get"}).done(function(){
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_start_new").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                let callb = function (data) {
                    if (data.status !== 'ok') {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: "{{ lang._('Iperf Server') }}",
                            message: data.error !== undefined ? data.error : data.status
                        });
                    }
                    $("#grid-jobs").bootgrid("reload");
                    dfObj.reject(); /* do not execute regular data_endpoint */
                }
                saveFormToEndpoint("/api/iperf/instance/set", 'frm_InstanceSettings', callb, true, callb);
                return dfObj;
            }
        });
    });
</script>

<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':instanceForm,'id':'frm_InstanceSettings'])}}
    {{ partial('layout_partials/base_apply_button', {'button_id': 'btn_start_new', 'data_endpoint': '', 'data_label': lang._('Start')}) }}
</div>
<div class="content-box">
    <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="status" data-width="2em" data-sortable="false" data-formatter="status">&nbsp;</th>
                <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="started" data-type="string" data-order="desc">{{ lang._('Started') }}</th>
                <th data-column-id="port" data-type="string">{{ lang._('Port') }}</th>
                <th data-column-id="sent" data-type="string">{{ lang._('Sent (Mbit/s)') }}</th>
                <th data-column-id="received" data-type="string">{{ lang._('Received (Mbit/s)') }}</th>
                <th data-column-id="error" data-type="string">{{ lang._('Error') }}</th>
                <th data-column-id="commands" data-width="12em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
