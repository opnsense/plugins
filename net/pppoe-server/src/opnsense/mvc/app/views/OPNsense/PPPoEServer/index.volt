{#
 # Copyright (C) 2026 VEQNORA
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
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

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#acs">{{ lang._('Access Concentrators') }}</a></li>
    <li><a data-toggle="tab" href="#pools">{{ lang._('Address Pools') }}</a></li>
    <li><a data-toggle="tab" href="#users">{{ lang._('Local Users') }}</a></li>
    <li><a data-toggle="tab" href="#radius">{{ lang._('RADIUS') }}</a></li>
    <li><a data-toggle="tab" href="#sessions" id="sessions_tab">{{ lang._('Sessions') }}</a></li>
    <li><a data-toggle="tab" href="#diagnostics" id="diagnostics_tab">{{ lang._('Diagnostics') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="general" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings']) }}
    </div>
    <div id="acs" class="tab-pane fade in">
        <table id="grid-acs" class="table table-condensed table-hover table-striped" data-editDialog="DialogAC" data-editAlert="ConfigChangeMsg">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="acname" data-type="string">{{ lang._('AC name') }}</th>
                    <th data-column-id="gateway" data-type="string">{{ lang._('Gateway') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="pools" class="tab-pane fade in">
        <table id="grid-pools" class="table table-condensed table-hover table-striped" data-editDialog="DialogPool" data-editAlert="ConfigChangeMsg">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="start" data-type="string">{{ lang._('Start') }}</th>
                    <th data-column-id="end" data-type="string">{{ lang._('End') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="users" class="tab-pane fade in">
        <table id="grid-users" class="table table-condensed table-hover table-striped" data-editDialog="DialogUser" data-editAlert="ConfigChangeMsg">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="username" data-type="string">{{ lang._('Username') }}</th>
                    <th data-column-id="staticip" data-type="string">{{ lang._('Static IP') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="radius" class="tab-pane fade in">
        <table id="grid-radius" class="table table-condensed table-hover table-striped" data-editDialog="DialogRadius" data-editAlert="ConfigChangeMsg">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="host" data-type="string">{{ lang._('Server') }}</th>
                    <th data-column-id="authport" data-type="string">{{ lang._('Auth port') }}</th>
                    <th data-column-id="acctport" data-type="string">{{ lang._('Acct port') }}</th>
                    <th data-column-id="priority" data-type="string">{{ lang._('Priority') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="sessions" class="tab-pane fade in">
        <table id="grid-sessions" class="table table-condensed table-hover table-striped">
            <thead>
                <tr>
                    <th data-column-id="username" data-type="string">{{ lang._('Username') }}</th>
                    <th data-column-id="session_id" data-type="string" data-identifier="true">{{ lang._('Session ID') }}</th>
                    <th data-column-id="iface" data-type="string">{{ lang._('PPP interface') }}</th>
                    <th data-column-id="address" data-type="string">{{ lang._('Client IP') }}</th>
                    <th data-column-id="peer_mac" data-type="string">{{ lang._('Client MAC') }}</th>
                    <th data-column-id="bundle" data-type="string">{{ lang._('Bundle') }}</th>
                    <th data-column-id="uptime" data-type="string" data-formatter="secondsToTime">{{ lang._('Uptime') }}</th>
                    <th data-column-id="input_bytes" data-type="numeric" data-formatter="byteSize">{{ lang._('In') }}</th>
                    <th data-column-id="output_bytes" data-type="numeric" data-formatter="byteSize">{{ lang._('Out') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="sessionCommands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div class="col-md-12">
            <button class="btn btn-default" id="refreshSessions" type="button">
                <span class="fa fa-refresh fa-fw"></span> {{ lang._('Refresh') }}
            </button>
        </div>
    </div>
    <div id="diagnostics" class="tab-pane fade in">
        <div class="col-md-12">
            <h4>{{ lang._('Environment') }}</h4>
            <pre id="diagVersions">{{ lang._('loading...') }}</pre>
            <h4>{{ lang._('Configuration check') }}</h4>
            <pre id="diagValidate">{{ lang._('loading...') }}</pre>
            <h4>{{ lang._('Address pool usage') }}</h4>
            <pre id="diagPools">{{ lang._('loading...') }}</pre>
            <h4>{{ lang._('Netgraph nodes') }}</h4>
            <pre id="diagNetgraph">{{ lang._('loading...') }}</pre>
            <h4>{{ lang._('Generated configuration (secrets masked)') }}</h4>
            <pre id="diagPreview" style="max-height: 400px; overflow-y: auto;">{{ lang._('loading...') }}</pre>
            <h4>{{ lang._('RADIUS test') }}</h4>
            <button class="btn btn-default" id="radiusTestAct" type="button">
                <span class="fa fa-exchange fa-fw"></span> {{ lang._('Test configured RADIUS servers') }}
            </button>
            <pre id="diagRadius" style="display: none;"></pre>
            <p>
                <small>
                    {{ lang._('Prometheus metrics endpoint:') }} <code>/api/pppoeserver/diagnostics/metrics</code> ·
                    {{ lang._('Support bundle (secret-free):') }} <code>/api/pppoeserver/diagnostics/supportBundle</code>
                </small>
            </p>
        </div>
    </div>
    <div class="col-md-12">
        <div id="ConfigChangeMsg" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('Configuration changed, apply to activate.') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="saveAct" type="button"
                data-endpoint="/api/pppoeserver/service/reconfigure"
                data-label="{{ lang._('Save & Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring PPPoE server') }}"
                data-service-widget="pppoeserver"><b>{{ lang._('Save & Apply') }}</b> <i id="saveAct_progress"></i></button>
        <br/><br/>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogAC,'id':'DialogAC','label':lang._('Edit access concentrator')]) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogPool,'id':'DialogPool','label':lang._('Edit address pool')]) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogUser,'id':'DialogUser','label':lang._('Edit local user')]) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogRadius,'id':'DialogRadius','label':lang._('Edit RADIUS server')]) }}

<script>
    'use strict';

    $( document ).ready(function() {
        mapDataToFormUI({'frm_general_settings':"/api/pppoeserver/settings/get"}).done(function(){
            updateServiceControlUI('pppoeserver');
        });

        $("#grid-acs").UIBootgrid({
            search:'/api/pppoeserver/settings/searchAc/',
            get:'/api/pppoeserver/settings/getAc/',
            set:'/api/pppoeserver/settings/setAc/',
            add:'/api/pppoeserver/settings/addAc/',
            del:'/api/pppoeserver/settings/delAc/',
            toggle:'/api/pppoeserver/settings/toggleAc/'
        });

        $("#grid-pools").UIBootgrid({
            search:'/api/pppoeserver/settings/searchPool/',
            get:'/api/pppoeserver/settings/getPool/',
            set:'/api/pppoeserver/settings/setPool/',
            add:'/api/pppoeserver/settings/addPool/',
            del:'/api/pppoeserver/settings/delPool/',
            toggle:'/api/pppoeserver/settings/togglePool/'
        });

        $("#grid-users").UIBootgrid({
            search:'/api/pppoeserver/settings/searchUser/',
            get:'/api/pppoeserver/settings/getUser/',
            set:'/api/pppoeserver/settings/setUser/',
            add:'/api/pppoeserver/settings/addUser/',
            del:'/api/pppoeserver/settings/delUser/',
            toggle:'/api/pppoeserver/settings/toggleUser/'
        });

        $("#grid-radius").UIBootgrid({
            search:'/api/pppoeserver/settings/searchRadius/',
            get:'/api/pppoeserver/settings/getRadius/',
            set:'/api/pppoeserver/settings/setRadius/',
            add:'/api/pppoeserver/settings/addRadius/',
            del:'/api/pppoeserver/settings/delRadius/',
            toggle:'/api/pppoeserver/settings/toggleRadius/'
        });

        const sessionsGrid = $("#grid-sessions").UIBootgrid({
            search: '/api/pppoeserver/sessions/search',
            options: {
                selection: false,
                multiSelect: false,
                formatters: {
                    byteSize: function (column, row) {
                        let value = parseInt(row[column.id], 10);
                        if (isNaN(value)) {
                            return '';
                        }
                        const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
                        let unit = 0;
                        while (value >= 1024 && unit < units.length - 1) {
                            value /= 1024;
                            unit++;
                        }
                        return value.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
                    },
                    secondsToTime: function (column, row) {
                        const total = parseInt(row[column.id], 10);
                        if (isNaN(total)) {
                            return '';
                        }
                        const hours = Math.floor(total / 3600);
                        const mins = Math.floor((total % 3600) / 60);
                        const secs = total % 60;
                        return hours + ':' + ('0' + mins).slice(-2) + ':' + ('0' + secs).slice(-2);
                    },
                    sessionCommands: function (column, row) {
                        return '<button type="button" class="btn btn-xs btn-default command-disconnect" ' +
                               'data-row-id="' + $('<i/>').text(row.session_id).html() + '" ' +
                               'title="{{ lang._('Disconnect') }}"><span class="fa fa-times fa-fw"></span></button>';
                    }
                }
            }
        });

        $("#grid-sessions").on("loaded.rs.jquery.bootgrid", function () {
            $(".command-disconnect").off('click').on("click", function () {
                const sessionId = $(this).data("row-id");
                stdDialogConfirm(
                    "{{ lang._('Confirm disconnect') }}",
                    "{{ lang._('Disconnect this PPPoE session?') }}",
                    "{{ lang._('Yes') }}", "{{ lang._('Cancel') }}",
                    function () {
                        ajaxCall("/api/pppoeserver/sessions/disconnect", {'session_id': sessionId}, function () {
                            sessionsGrid.bootgrid("reload");
                        });
                    }
                );
            });
        });

        $("#refreshSessions, #sessions_tab").on("click", function () {
            sessionsGrid.bootgrid("reload");
        });

        function loadDiagnostics() {
            ajaxGet("/api/pppoeserver/diagnostics/versions", {}, function (data) {
                $("#diagVersions").text(
                    "mpd5: " + (data.mpd5 || '?') + "\nplugin: " + (data.plugin || '?') + "\nos: " + (data.os || '?')
                );
            });
            ajaxGet("/api/pppoeserver/diagnostics/validate", {}, function (data) {
                $("#diagValidate").text(
                    data.status === 'ok' ? "{{ lang._('No problems detected.') }}" : (data.problems || []).join("\n")
                );
            });
            ajaxGet("/api/pppoeserver/diagnostics/poolStatus", {}, function (data) {
                if (data.status === 'ok' && data.pools && data.pools.length) {
                    $("#diagPools").text(data.pools.map(function (p) {
                        return p.name + ": " + p.used + " / " + p.total + " {{ lang._('used') }}";
                    }).join("\n"));
                } else {
                    $("#diagPools").text(data.message || "{{ lang._('No pool data (service not running?)') }}");
                }
            });
            ajaxGet("/api/pppoeserver/diagnostics/netgraph", {}, function (data) {
                $("#diagNetgraph").text(data.status === 'ok'
                    ? (data.nodes + " {{ lang._('nodes') }}\n" + (data.listing || ''))
                    : (data.message || 'unavailable'));
            });
            ajaxGet("/api/pppoeserver/diagnostics/configPreview", {}, function (data) {
                $("#diagPreview").text(data.status === 'ok' ? data.preview : (data.message || 'unavailable'));
            });
        }
        $("#diagnostics_tab").on("click", loadDiagnostics);

        $("#radiusTestAct").on("click", function () {
            const btn = $(this);
            btn.prop('disabled', true);
            ajaxCall("/api/pppoeserver/diagnostics/radiusTest", {}, function (data) {
                btn.prop('disabled', false);
                let text;
                if (data.status === 'ok') {
                    text = (data.results || []).map(function (r) {
                        return r.host + ": " + r.status + " - " + r.detail;
                    }).join("\n");
                } else {
                    text = data.message || 'failed';
                }
                $("#diagRadius").show().text(text);
            });
        });

        $("#saveAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/pppoeserver/settings/set", 'frm_general_settings', function() {
                    dfObj.resolve();
                }, true, function() {
                    dfObj.reject();
                });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('pppoeserver');
                $("#ConfigChangeMsg").hide();
            }
        });
    });
</script>
