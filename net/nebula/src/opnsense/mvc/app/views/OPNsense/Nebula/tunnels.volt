{#
 # Copyright (c) 2026 Henry Stern <henry@stern.ca>
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

<script>
    'use strict';

    $(document).ready(function () {

        // Deep-link: /ui/nebula/tunnels?instance=<uuid> pre-selects an instance
        // (the Status page links here that way).
        const deepLinkInstance = new URLSearchParams(window.location.search).get('instance') || '';

        let currentInstance = deepLinkInstance;   // '' = All instances
        let allRows = [];                          // full fetched dataset (one Refresh)

        function esc(s) {
            return $('<div/>').text(s === undefined || s === null ? '' : String(s)).html();
        }

        // Comparable key for default IP ordering (correct for v4; v6 -> string).
        function ipKey(ip) {
            const v4 = /^(\d+)\.(\d+)\.(\d+)\.(\d+)$/.exec(ip || '');
            if (v4) {
                return '4:' + [v4[1], v4[2], v4[3], v4[4]]
                    .map(function (o) { return ('00' + o).slice(-3); }).join('.');
            }
            return '6:' + (ip || '');
        }

        // -----------------------------------------------------------------------
        // Column formatters — receive (column, row), return cell HTML. The grid
        // runs in static (ajax:false) mode, so search/sort operate on the row
        // DATA fields (not the rendered HTML): that's why the backend ships a
        // `remotes` string (all underlay addrs, space-joined) for the Known
        // Remotes column even though we render it compact.
        // -----------------------------------------------------------------------
        function via_formatter(column, row) {
            if (row.handshaking) {
                return '<span class="label label-warning">{{ lang._('Handshaking') }}</span>';
            }
            return row.relayed
                ? '<span class="label label-default">{{ lang._('Relay') }}</span>'
                : '<span class="label label-success">{{ lang._('Direct') }}</span>';
        }
        function groups_formatter(column, row) {
            if (!row.groups) { return '—'; }
            return String(row.groups).split(', ').map(esc).join('<br/>');
        }
        function remotes_formatter(column, row) {
            const addrs = Array.isArray(row.remoteAddrs) ? row.remoteAddrs : [];
            if (!addrs.length) { return '—'; }
            return addrs.map(esc).join('<br/>');
        }
        function actions_formatter(column, row) {
            const v = esc(row.vpn || ''), u = esc(row.instance_uuid || '');
            // Query-lighthouse applies to any peer; close/change-remote only make
            // sense for an established tunnel, so hide them while handshaking.
            const lh =
                '<button type="button" class="btn btn-xs btn-default tun-lh" ' +
                'data-uuid="' + u + '" data-vpn="' + v + '" title="{{ lang._('Query lighthouse') }}">' +
                '<i class="fa fa-search"></i></button>';
            if (row.handshaking) {
                return lh;
            }
            return [
                '<button type="button" class="btn btn-xs btn-default tun-close" ',
                'data-uuid="', u, '" data-vpn="', v, '" title="{{ lang._('Close tunnel') }}">',
                '<i class="fa fa-times-circle"></i></button> ',
                lh, ' ',
                '<button type="button" class="btn btn-xs btn-default tun-rem" ',
                'data-uuid="', u, '" data-vpn="', v, '" title="{{ lang._('Change remote') }}">',
                '<i class="fa fa-exchange"></i></button>'
            ].join('');
        }

        // -----------------------------------------------------------------------
        // Per-peer debug verbs (only fire on explicit click)
        // -----------------------------------------------------------------------
        function debugCall(uuid, action, params, cb) {
            ajaxCall('/api/nebula/instance/debug/' + encodeURIComponent(uuid) + '/' + action,
                params || {}, function (data) { cb(data || {}); });
        }

        function afterAction(data, okMsg, onOk) {
            if (data && data.ok) {
                if (okMsg && typeof $.notify === 'function') {
                    $.notify(okMsg, {className: 'success', position: 'top right'});
                }
                if (onOk) { onOk(); }
            } else {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Nebula debug server') }}',
                    message: esc((data && (data.error || data.raw)) || '{{ lang._('command failed') }}')
                });
            }
        }

        function closeTunnel(uuid, vpn) {
            BootstrapDialog.confirm({
                title: '{{ lang._('Close tunnel') }}',
                message: '{{ lang._('Close the tunnel to') }} ' + esc(vpn) + '? ' +
                    '{{ lang._('It will re-handshake on the next packet.') }}',
                type: BootstrapDialog.TYPE_WARNING,
                callback: function (ok) {
                    if (!ok) { return; }
                    debugCall(uuid, 'close', {vpn: vpn}, function (d) {
                        afterAction(d, '{{ lang._('Tunnel closed') }}', fetchPeers);
                    });
                }
            });
        }

        function queryLighthouse(uuid, vpn) {
            debugCall(uuid, 'querylh', {vpn: vpn}, function (d) {
                let body = (d && (d.raw !== undefined ? d.raw : d.error)) || '{{ lang._('(no response)') }}';
                // query-lighthouse returns JSON — pretty-print it when it parses.
                if (d && d.ok && typeof body === 'string') {
                    try { body = JSON.stringify(JSON.parse(body), null, 2); } catch (e) { /* leave raw */ }
                }
                BootstrapDialog.show({
                    type: d && d.ok ? BootstrapDialog.TYPE_INFO : BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Query lighthouse') }} — ' + esc(vpn),
                    message: $("<pre style='white-space:pre-wrap;'/>").text(body)
                });
            });
        }

        function changeRemote(uuid, vpn) {
            let $inp = $("<input type='text' class='form-control'/>")
                .attr('placeholder', '{{ lang._('host:port, e.g. 198.51.100.1:4242') }}');
            BootstrapDialog.show({
                title: '{{ lang._('Change remote') }}',
                message: $("<div/>")
                    .append($("<label/>").text('{{ lang._('New underlay address for') }} ' + (vpn || '') + ':'))
                    .append($inp),
                buttons: [
                    {label: '{{ lang._('Cancel') }}', action: function (d) { d.close(); }},
                    {label: '{{ lang._('Change remote') }}', cssClass: 'btn-primary', action: function (d) {
                        let v = ($inp.val() || '').trim();
                        if (v) {
                            debugCall(uuid, 'changeremote', {vpn: vpn, remote: v}, function (r) {
                                afterAction(r, '{{ lang._('Remote changed') }}');
                            });
                            d.close();
                        }
                    }}
                ]
            });
        }

        function connectPeer() {
            // Connect needs a single instance target — use the filtered instance.
            if (!currentInstance) {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_WARNING,
                    title: '{{ lang._('Connect to peer') }}',
                    message: '{{ lang._('Select a single instance in the filter first.') }}'
                });
                return;
            }
            let $ip = $("<input type='text' class='form-control'/>")
                .attr('placeholder', '{{ lang._('peer nebula IP, e.g. 192.168.100.9') }}');
            BootstrapDialog.show({
                title: '{{ lang._('Connect to peer') }}',
                message: $("<div/>").append($ip).append(
                    $("<p class='text-muted' style='margin-top:6px;'/>").text(
                        '{{ lang._('The lighthouse resolves the underlay address.') }}')),
                buttons: [
                    {label: '{{ lang._('Cancel') }}', action: function (d) { d.close(); }},
                    {label: '{{ lang._('Create tunnel') }}', cssClass: 'btn-primary', action: function (d) {
                        let ip = ($ip.val() || '').trim();
                        if (ip) {
                            debugCall(currentInstance, 'createtunnel', {vpn: ip}, function (r) {
                                afterAction(r, '{{ lang._('Tunnel requested') }}', fetchPeers);
                            });
                            d.close();
                        }
                    }}
                ]
            });
        }

        // -----------------------------------------------------------------------
        // Grid — OPNsense UIBootgrid in static (ajax:false) mode. Created ONCE;
        // data is pushed in client-side via bootgrid('replace', rows). All
        // sort/search/paging happen locally over that snapshot until the next
        // Refresh. This is the same pattern as core Diagnostics/routes.volt.
        // -----------------------------------------------------------------------
        $('#grid-tunnels').UIBootgrid({
            datakey: 'vpn',
            options: {
                ajax: false,
                selection: false,
                multiSelect: false,
                rowCount: [20, 50, 100, true],
                formatters: {
                    via: via_formatter,
                    groups: groups_formatter,
                    remotes: remotes_formatter,
                    actions: actions_formatter
                }
            }
        });

        // Push the current snapshot (filtered by instance, IP-sorted) into the grid.
        // replace() throws on an empty array, so clear() is used when nothing matches.
        function applyData() {
            const rows = allRows
                .filter(function (r) { return !currentInstance || r.instance_uuid === currentInstance; })
                .sort(function (a, b) { return ipKey(a.vpn).localeCompare(ipKey(b.vpn)); });
            if (rows.length) {
                $('#grid-tunnels').bootgrid('replace', rows);
            } else {
                $('#grid-tunnels').bootgrid('clear');
            }
        }

        function fetchPeers() {
            ajaxGet('/api/nebula/peer/search', {}, function (data) {
                allRows = (data && Array.isArray(data.rows)) ? data.rows : [];
                applyData();
            });
        }

        // Action buttons are formatter-rendered, so delegate on document (the grid
        // re-renders its rows on sort/page; delegation survives that).
        $(document)
            .on('click', '.tun-close', function () { closeTunnel($(this).data('uuid'), $(this).data('vpn')); })
            .on('click', '.tun-lh',    function () { queryLighthouse($(this).data('uuid'), $(this).data('vpn')); })
            .on('click', '.tun-rem',   function () { changeRemote($(this).data('uuid'), $(this).data('vpn')); });

        // -----------------------------------------------------------------------
        // Instance filter (All instances default; deep-link preselect). Changing
        // it re-filters the existing snapshot client-side — no refetch.
        // -----------------------------------------------------------------------
        const $filter = $('#nebula-tunnel-instance');
        ajaxGet('/api/nebula/instance/search_item', {}, function (data) {
            $filter.append($('<option/>').val('').text('{{ lang._('All instances') }}'));
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    $filter.append($('<option/>').val(row.uuid).text(row.description || row.uuid));
                });
            }
            $filter.val(deepLinkInstance);
            if ($filter.hasClass('selectpicker')) { $filter.selectpicker('refresh'); }
            currentInstance = $filter.val() || '';
            fetchPeers();
        });

        $filter.on('changed.bs.select change', function () {
            currentInstance = $(this).val() || '';
            applyData();   // client-side re-filter, no refetch
        });

        // -----------------------------------------------------------------------
        // Toolbar
        // -----------------------------------------------------------------------
        $('#btn-tunnel-refresh').on('click', function () { fetchPeers(); });
        $('#btn-tunnel-connect').on('click', function () { connectPeer(); });

        updateServiceControlUI('nebula');
    });
</script>

<div class="tab-content content-box">
    <div class="col-md-12" style="padding: 10px 15px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
        <label for="nebula-tunnel-instance" style="margin: 0; white-space: nowrap;">
            {{ lang._('Instance') }}
        </label>
        <select id="nebula-tunnel-instance" class="selectpicker" data-width="280px"
                data-live-search="true"></select>
        <button id="btn-tunnel-refresh" class="btn btn-default" type="button">
            <i class="fa fa-refresh fa-fw"></i> {{ lang._('Refresh') }}
        </button>
        <button id="btn-tunnel-connect" class="btn btn-default" type="button">
            <i class="fa fa-plus fa-fw"></i> {{ lang._('Connect to peer') }}
        </button>
    </div>
    <table id="grid-tunnels" class="table table-condensed table-hover table-striped">
        <thead>
            <tr>
                <th data-column-id="instance" data-type="string">{{ lang._('Instance') }}</th>
                <th data-column-id="vpn" data-type="string" data-identifier="true">{{ lang._('Nebula IP') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="groups" data-type="string" data-formatter="groups">{{ lang._('Groups') }}</th>
                <th data-column-id="relayed" data-formatter="via" data-sortable="false" data-searchable="false">{{ lang._('Via') }}</th>
                <th data-column-id="currentRemote" data-type="string">{{ lang._('Current Remote') }}</th>
                <th data-column-id="remotes" data-type="string" data-formatter="remotes" data-sortable="false">{{ lang._('Known Remotes') }}</th>
                <th data-column-id="messages" data-type="numeric">{{ lang._('Messages') }}</th>
                <th data-column-id="actions" data-formatter="actions" data-sortable="false" data-searchable="false">{{ lang._('Actions') }}</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
