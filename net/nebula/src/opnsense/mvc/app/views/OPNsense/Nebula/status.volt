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

        const $panels = $("#nebula_status_panels");
        const $empty  = $("#nebula_status_empty");

        function txt(s) { return $("<span/>").text(s === undefined || s === null ? '' : String(s)); }
        function esc(s) { return $('<div/>').text(s === undefined || s === null ? '' : String(s)).html(); }

        function row(label, $value) {
            return $("<tr/>")
                .append($("<td/>").css({width: '150px', 'font-weight': '600'}).text(label))
                .append($("<td/>").append($value));
        }

        function certLine(cert) {
            if (!cert || !cert.name) {
                return $("<span class='text-danger'/>").text('{{ lang._('no certificate assigned') }}');
            }
            let parts = [cert.name];
            if (cert.groups) { parts.push('[' + cert.groups + ']'); }
            if (cert.networks) { parts.push(cert.networks); }
            let $s = $("<span/>").text(parts.join('  '));
            if (cert.valid_to) {
                $s.append($("<span/>").text('  ' + '{{ lang._('expires') }} ' + cert.valid_to));
            }
            return $s;
        }

        // POST a whitelisted debug sub-action to the always-on debug server.
        function debugCall(uuid, action, params, cb) {
            ajaxCall('/api/nebula/instance/debug/' + encodeURIComponent(uuid) + '/' + action,
                params || {}, function (data) { cb(data || {}); });
        }

        // --- per-peer verbs ---------------------------------------------------
        function afterAction(uuid, data, okMsg) {
            if (data && data.ok) {
                if (okMsg && typeof $.notify === 'function') {
                    $.notify(okMsg, {className: 'success', position: 'top right'});
                }
                refresh();
            } else {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Nebula debug server') }}',
                    message: esc((data && (data.error || data.raw)) || '{{ lang._('command failed') }}')
                });
            }
        }

        function connectPeer(uuid) {
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
                            debugCall(uuid, 'createtunnel', {vpn: ip}, function (r) {
                                afterAction(uuid, r, '{{ lang._('Tunnel requested') }}');
                            });
                            d.close();
                        }
                    }}
                ]
            });
        }

        // Per-cell signature cache (device-info reuses it).
        const lastSig = {};

        function setIface(uuid, device) {
            const $cell = $('#nebula_iface_' + uuid);
            if (!$cell.length || !device) { return; }
            const dsig = (device.name || '') + '|' + (device.cidr || '');
            if (lastSig['dev_' + uuid] === dsig) { return; }
            lastSig['dev_' + uuid] = dsig;
            $cell.text((device.name || '') + (device.cidr ? '  ·  ' + device.cidr : ''));
        }

        // Forget cached signatures for an instance whose panel was removed, so a
        // re-added instance renders fresh into its new (empty) cells.
        function forgetSigs(uuid) {
            ['dev_'].forEach(function (k) { delete lastSig[k + uuid]; });
        }

        // Running/pid status, rendered into the panel body (updated in place on
        // refresh). Plain text — no status colour.
        function setState(d) {
            const $cell = $('#nebula_state_' + d.uuid);
            if (!$cell.length) { return; }
            $cell.text(d.running === true
                ? '{{ lang._('running') }}' + (d.pid ? ' (pid ' + d.pid + ')' : '')
                : '{{ lang._('stopped') }}');
        }

        // Heading content (status icon, name, lighthouse badge, Connect button).
        // Rebuilt on every refresh — cheap and carries no placeholder, so
        // refreshing it never flashes "loading…".
        function buildHeading(d) {
            const running = (d.running === true);
            const $head = $("<div/>");
            $head.append($("<i/>").addClass('fa fa-plug ' + (running ? 'text-success' : 'text-muted'))
                .css('margin-right', '8px'));
            $head.append($("<strong/>").text(d.description || d.uuid));
            if (d.am_lighthouse) {
                $head.append($("<span class='label label-info'/>").css('margin-left', '8px').text('{{ lang._('lighthouse') }}'));
            }
            if (running && d.diag_available) {
                $head.append($("<button class='btn btn-xs btn-default' type='button'/>")
                    .css('float', 'right')
                    .append("<i class='fa fa-plus fa-fw'></i> ").append(document.createTextNode('{{ lang._('Connect to peer') }}'))
                    .on('click', function () { connectPeer(d.uuid); }));
            }
            return $head;
        }

        // Build one instance panel from a diag payload (first render only).
        function panel(d) {
            const $head = $("<div class='panel-heading'/>").attr('id', 'nebula_head_' + d.uuid)
                .append(buildHeading(d));

            const $tbody = $("<tbody/>");

            // Status (running/pid) — updated in place on refresh by setState().
            const $state = $("<span/>").attr('id', 'nebula_state_' + d.uuid);
            $tbody.append(row('{{ lang._('Status') }}', $state));

            // Interface — filled async from device-info when running.
            const $iface = $("<span/>").text(d.tun_dev || '{{ lang._('—') }}');
            $iface.attr('id', 'nebula_iface_' + d.uuid);
            $tbody.append(row('{{ lang._('Interface') }}', $iface));
            $tbody.append(row('{{ lang._('Listen') }}', txt(d.listen)));
            $tbody.append(row('{{ lang._('Certificate') }}', certLine(d.cert)));

            let $cfg;
            if (d.config_valid === true) {
                $cfg = $("<span/>").text('{{ lang._('valid') }} (nebula -test)');
            } else if (d.config_valid === false) {
                $cfg = $("<span class='text-danger'/>").text(d.config_error || '{{ lang._('invalid') }}');
            } else {
                $cfg = $("<span class='text-muted'/>").text(d.config_error || '{{ lang._('unknown') }}');
            }
            $tbody.append(row('{{ lang._('Config') }}', $cfg));

            return $("<div class='panel panel-default'/>").attr('id', 'nebula_panel_' + d.uuid)
                .append($head)
                .append($("<table class='table table-condensed'/>").append($tbody));
        }

        // uuids that already have a rendered panel — so a refresh updates panels
        // in place instead of tearing them down (which flashed "loading…" and
        // collapsed expanded detail rows every cycle).
        const built = {};

        // ONE request: the snapshot returns every instance's status,
        // diagnostics and device-info together.
        function refresh() {
            ajaxGet('/api/nebula/instance/snapshot', {}, function (data, status) {
                const list = (data && data.instances) || [];
                if (list.length === 0) {
                    $panels.empty();
                    Object.keys(built).forEach(function (u) { delete built[u]; forgetSigs(u); });
                    $empty.show();
                    return;
                }
                $empty.hide();
                const uuids = list.map(function (d) { return d.uuid; });
                // Remove panels for instances that no longer exist.
                Object.keys(built).forEach(function (u) {
                    if (uuids.indexOf(u) === -1) {
                        $('#nebula_panel_' + u).remove();
                        delete built[u];
                        forgetSigs(u);
                    }
                });
                list.forEach(function (d) {
                    if (!built[d.uuid]) {
                        $panels.append(panel(d));
                        built[d.uuid] = true;
                    } else {
                        // Rebuild the heading (running plug + Connect) in place; the
                        // body's status/iface cells are updated below.
                        $('#nebula_head_' + d.uuid).empty().append(buildHeading(d));
                    }
                    setState(d);
                    if (d.diag_available) {
                        setIface(d.uuid, d.device);
                    }
                });
            });
        }

        $("#nebula_status_refresh").on('click', function () { refresh(); });
        refresh();
        updateServiceControlUI('nebula');
    });
</script>

<div class="content-box" style="padding: 15px;">
    <button class="btn btn-default" id="nebula_status_refresh" type="button">
        <i class="fa fa-refresh"></i> {{ lang._('Refresh') }}
    </button>
    <div id="nebula_status_empty" style="display: none; margin-top: 15px;" class="text-muted">
        {{ lang._('No Nebula instances configured.') }}
    </div>
    <div id="nebula_status_panels" style="margin-top: 15px;"></div>
</div>
