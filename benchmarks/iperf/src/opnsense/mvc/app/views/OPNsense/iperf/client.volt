{#
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
        $("#grid-jobs").UIBootgrid({
            search: '/api/iperf/client/search_jobs',
            datakey: 'id',
            options: {
                selection: false,
                formatters: {
                    "direction": function (column, row) {
                        return row.reverse == '1' ? "{{ lang._('Download') }}" : "{{ lang._('Upload') }}";
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
                delete: {
                    title: "{{ lang._('Remove') }}",
                    method: function() {
                        ajaxCall("/api/iperf/client/remove/" + $(this).data('row-id'), {}, function () {
                            $("#grid-jobs").bootgrid("reload");
                        });
                    },
                    classname: 'fa fa-fw fa-trash-o',
                    requires: []
                }
            }
        });
        setInterval(function(){ $("#grid-jobs").bootgrid("reload"); }, 5000);

        mapDataToFormUI({'frm_ClientSettings': "/api/iperf/client/get"}).done(function(){
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_start_new").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                let callb = function (data) {
                    if (data.result && data.result === 'ok') {
                        ajaxCall("/api/iperf/client/start/" + data.uuid, {}, function(data){
                            if (data.status !== 'ok') {
                                BootstrapDialog.show({
                                    type: BootstrapDialog.TYPE_WARNING,
                                    title: "{{ lang._('Iperf Client') }}",
                                    message: data.status_msg !== undefined ? data.status_msg : data.status
                                });
                            }
                            $("#grid-jobs").bootgrid("reload");
                        });
                    }
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
                <th data-column-id="protocol" data-type="string">{{ lang._('Protocol') }}</th>
                <th data-column-id="parallel" data-type="string">{{ lang._('Streams') }}</th>
                <th data-column-id="reverse" data-type="string" data-formatter="direction">{{ lang._('Direction') }}</th>
                <th data-column-id="sent" data-type="string">{{ lang._('Sent (Mbit/s)') }}</th>
                <th data-column-id="received" data-type="string">{{ lang._('Received (Mbit/s)') }}</th>
                <th data-column-id="error" data-type="string">{{ lang._('Error') }}</th>
                <th data-column-id="commands" data-width="4em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
