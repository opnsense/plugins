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

<style>
    .monospace-dialog {
        font-family: monospace;
        white-space: pre;
    }
    .monospace-dialog > .modal-dialog {
        width: 70% !important;
    }
    .modal-body {
        max-height: calc(100vh - 210px);
        overflow-y: auto;
    }
</style>

<script>
    'use strict';

    $(document).ready(function () {

        // -----------------------------------------------------------------------
        // Instances grid — lean summary columns (from the form's grid_view fields)
        // + a live Status indicator + Interfaces-Overview-style per-row buttons.
        // -----------------------------------------------------------------------

        // Wire the bootgrid change-alert. showSaveAlert() (fired after every
        // add/edit/delete/toggle) slides down the element named by the table's
        // data-editAlert attribute — the core mechanism from OPNsense/IDS/index.volt.
        // base_bootgrid_table (core-shared) does not forward editAlert, so set it
        // on the table element BEFORE UIBootgrid() captures it as $compatElement.

        const grid_instances = $("#{{formGridInstance['table_id']}}").UIBootgrid({
            search: '/api/nebula/instance/search_item',
            get:    '/api/nebula/instance/get_item/',
            set:    '/api/nebula/instance/set_item/',
            add:    '/api/nebula/instance/add_item/',
            del:    '/api/nebula/instance/del_item/',
            toggle: '/api/nebula/instance/toggle_item/',
            options: {
                selection: false,
                formatters: {
                    /*
                     * Status cell — DATA-DRIVEN. The search endpoint enriches each
                     * row with running/pid, so the formatter renders the plug + a
                     * tooltip synchronously. (Filling cells async on the 'loaded'
                     * event does not survive Tabulator re-renders; the bootgrid
                     * engine auto-activates tooltips on elements carrying the
                     * 'bootgrid-tooltip' class — see opnsense_bootgrid.js.)
                     */
                    "nebula_status": function (column, row) {
                        let running = (row.running === '1' || row.running === true);
                        let cls = running ? 'text-success' : 'text-muted';
                        let title = running
                            ? '{{ lang._('Running') }}' + (row.pid ? ' (pid ' + row.pid + ')' : '')
                            : '{{ lang._('Stopped') }}';
                        let safe = $('<div/>').text(title).html();
                        return '<i class="fa fa-plug ' + cls + ' bootgrid-tooltip"' +
                            ' role="img" aria-label="' + safe + '" title="' + safe + '"' +
                            ' data-toggle="tooltip"></i>';
                    },
                    /*
                     * Listen cell — combined "host:port" from the row's listen_host
                     * + listen_port (both come back in the search data). IPv6
                     * literals are bracketed so the address colons are not confused
                     * with the host:port separator: "::" -> "[::]:4242". This is the
                     * same rendering the Status page uses (nebula_format_listen).
                     */
                    "nebula_listen": function (column, row) {
                        let host = (row.listen_host || '').toString();
                        let port = (row.listen_port || '').toString();
                        if (host.indexOf(':') !== -1 && host.charAt(0) !== '[') {
                            host = '[' + host + ']';
                        }
                        return $('<div/>').text(host + ':' + port).html();
                    }
                }
            },
            commands: {
                reload: {
                    method: function (event) {
                        let uuid = $(this).data('row-id');
                        let $element = $(this).find('i');
                        $element.removeClass('fa-refresh').addClass('fa-spinner fa-spin');
                        ajaxCall('/api/nebula/instance/reload/' + uuid, {}, function (data, status) {
                            $element.removeClass('fa-spinner fa-spin').addClass('fa-refresh');
                            /* The reload endpoint returns the restart_instance
                             * JSON result {started, error}. If the (re)start
                             * failed, surface the real reason (e.g. "Failed to
                             * get a tun/tap device: device busy") instead of
                             * silently doing nothing. */
                            if (data && data.started === false) {
                                BootstrapDialog.show({
                                    type: BootstrapDialog.TYPE_DANGER,
                                    title: '{{ lang._('Nebula instance failed to start') }}',
                                    message: $('<div/>').text(
                                        data.error || '{{ lang._('The instance could not be started.') }}'
                                    ).html()
                                });
                            }
                            /* give the daemon a moment to come up, then reload the
                             * grid so the data-driven status column re-renders. */
                            setTimeout(function () {
                                $("#{{formGridInstance['table_id']}}").bootgrid('reload');
                            }, 1000);
                        });
                    },
                    classname: 'fa fa-fw fa-refresh',
                    title: '{{ lang._('Reload') }}',
                    sequence: 5
                },
                details: {
                    method: function (event) {
                        let uuid = $(this).data('row-id');
                        ajaxGet('/api/nebula/instance/status/' + uuid, {}, function (sdata, sstatus) {
                            let lines = [];
                            lines.push('{{ lang._('Status') }}');
                            if (sdata && sdata.running) {
                                lines.push('  ' + '{{ lang._('running') }}' +
                                    (sdata.pid ? ' (pid ' + sdata.pid + ')' : ''));
                            } else {
                                lines.push('  ' + '{{ lang._('stopped') }}');
                            }
                            lines.push('');
                            /* fetch the instance to learn its certref, then the cert info */
                            ajaxGet('/api/nebula/instance/get_item/' + uuid, {}, function (idata, istatus) {
                                let certref = (idata && idata.instance && idata.instance.certref)
                                    ? idata.instance.certref : '';
                                /* certref is a dropdown map {uuid: {value, selected}} */
                                if (typeof certref === 'object' && certref !== null) {
                                    let sel = '';
                                    $.each(certref, function (k, v) {
                                        if (v.selected == 1) {
                                            sel = k;
                                        }
                                    });
                                    certref = sel;
                                }
                                let show = function (extra) {
                                    BootstrapDialog.show({
                                        title: '{{ lang._('Instance Details') }}',
                                        type: BootstrapDialog.TYPE_INFO,
                                        message: $("<pre/>").text(lines.concat(extra).join("\n")).css({
                                            'max-height': '60vh',
                                            'overflow-y': 'auto',
                                            'font-size': '12px'
                                        }),
                                        cssClass: 'monospace-dialog'
                                    });
                                };
                                if (!certref) {
                                    show(['{{ lang._('No certificate assigned.') }}']);
                                    return;
                                }
                                ajaxGet('/api/nebula/certificate/info/' + certref, {}, function (cdata, cstatus) {
                                    let extra = ['{{ lang._('Certificate') }}'];
                                    if (cdata && cdata.info) {
                                        $.each(cdata.info, function (i, cert) {
                                            $.each(cert, function (k, v) {
                                                if (typeof v === 'object') {
                                                    extra.push('  ' + k + ':');
                                                    $.each(v, function (dk, dv) {
                                                        extra.push('    ' + dk + ': ' + dv);
                                                    });
                                                } else {
                                                    extra.push('  ' + k + ': ' + v);
                                                }
                                            });
                                        });
                                    } else {
                                        extra.push('  ' + '{{ lang._('Could not retrieve certificate info.') }}');
                                    }
                                    show(extra);
                                });
                            });
                        });
                    },
                    classname: 'fa fa-fw fa-search',
                    title: '{{ lang._('Details') }}',
                    sequence: 20
                }
            }
        });

        /* Populate / refresh the per-row status indicators after each grid load. */

        // -----------------------------------------------------------------------
        // Conditional field visibility in the instance dialog. A field is shown
        // only when its controlling checkbox is on AND (if it is an advanced
        // field) advanced mode is active — so we compose with, rather than fight,
        // the built-in advanced toggle (which also drives display on
        // [data-advanced="true"] rows). Re-evaluated on dialog populate, on every
        // controlling toggle, and after the advanced-mode switch reshuffles rows.
        // -----------------------------------------------------------------------
        function nebulaAdvancedOn() {
            // The advanced-mode toggle icon carries fa-toggle-on when active.
            return $('[id^="show_advanced_"]').hasClass('fa-toggle-on');
        }
        function nebulaChecked(id) {
            return $("#" + id.replace(/\./g, "\\.")).is(':checked');
        }
        function nebulaSetVis(id, cond) {
            const $row = $("#row_" + id.replace(/\./g, "\\."));
            const isAdvanced = $row.attr('data-advanced') === 'true';
            $row.toggle(cond && (!isAdvanced || nebulaAdvancedOn()));
        }
        function nebulaToggleInstanceFields() {
            const amLighthouse = nebulaChecked('instance.am_lighthouse');
            const serveDns     = nebulaChecked('instance.lighthouse_serve_dns');
            const useRelays    = nebulaChecked('instance.relay_use_relays');

            // Lighthouse: serve_dns is a lighthouse-only setting; the DNS host/port
            // only apply when this node is a lighthouse AND serving DNS. hosts,
            // interval and advertise_addrs stay visible — a regular node uses them.
            nebulaSetVis('instance.lighthouse_serve_dns', amLighthouse);
            nebulaSetVis('instance.lighthouse_dns_host', amLighthouse && serveDns);
            nebulaSetVis('instance.lighthouse_dns_port', amLighthouse && serveDns);

            // Relay: the relays list only matters when "use relays" is on.
            nebulaSetVis('instance.relay_relays', useRelays);
        }
        // Fire on dialog populate (add/edit/copy), on each controlling toggle, and
        // just after the advanced-mode switch re-shows [data-advanced] rows.
        $('#{{ formGridInstance["edit_dialog_id"] }}').on('opnsense_bootgrid_mapped', nebulaToggleInstanceFields);
        $(document).on('change',
            '#instance\\.am_lighthouse, #instance\\.lighthouse_serve_dns, ' +
            '#instance\\.relay_use_relays',
            nebulaToggleInstanceFields);        $(document).on('click', '[id^="show_advanced_"]', function () {
            setTimeout(nebulaToggleInstanceFields, 50);
        });

        // -----------------------------------------------------------------------
        // New-instance default: pre-select WAN for the auto firewall rule. The
        // model default is empty (so it never fails validation on boxes without a
        // "wan" interface); we default the picker to WAN on Add only, and only
        // when a "wan" interface actually exists. Editing an instance is untouched.
        // -----------------------------------------------------------------------
        let nebulaPendingAdd = false;
        $(document).on('click', '.command-add', function () { nebulaPendingAdd = true; });
        $('#{{ formGridInstance["edit_dialog_id"] }}').on('opnsense_bootgrid_mapped', function () {
            if (!nebulaPendingAdd) { return; }
            nebulaPendingAdd = false;
            const $sel = $("#instance\\.firewall_interfaces");
            if ($sel.length && ($sel.val() || []).length === 0 &&
                    $sel.find("option[value='wan']").length) {
                $sel.val(['wan']).trigger('change');
                if ($sel.hasClass('selectpicker')) { $sel.selectpicker('refresh'); }
            }
        });

        // -----------------------------------------------------------------------
        // Disambiguate like-named certs/CAs in the dialog selectors by appending
        // the first 8 hex of the fingerprint to each option ("name: 0123abcd").
        // Index uuid -> short fingerprint once; relabel on each dialog populate.
        // The original label is cached per-option so repeated opens never stack
        // multiple suffixes.
        // -----------------------------------------------------------------------
        let nebulaCertFp = {};
        let nebulaCaFp = {};
        ajaxGet('/api/nebula/certificate/search_item', {}, function (d) {
            if (d && d.rows) {
                d.rows.forEach(function (r) {
                    if (r.fingerprint) { nebulaCertFp[r.uuid] = String(r.fingerprint).substring(0, 8); }
                });
            }
        });
        ajaxGet('/api/nebula/authority/search_item', {}, function (d) {
            if (d && d.rows) {
                d.rows.forEach(function (r) {
                    if (r.fingerprint) { nebulaCaFp[r.uuid] = String(r.fingerprint).substring(0, 8); }
                });
            }
        });
        function nebulaLabelOptions(selectId, fpMap) {
            const $sel = $("#" + selectId.replace(/\./g, "\\."));
            if (!$sel.length) { return; }
            $sel.find('option').each(function () {
                const fp = fpMap[$(this).val()];
                if (!fp) { return; }
                let base = $(this).data('nebula-base');
                if (base === undefined) {
                    base = $(this).text();
                    $(this).data('nebula-base', base);
                }
                $(this).text(base + ': ' + fp);
            });
            if ($sel.hasClass('selectpicker')) { $sel.selectpicker('refresh'); }
        }
        $('#{{ formGridInstance["edit_dialog_id"] }}').on('opnsense_bootgrid_mapped', function () {
            nebulaLabelOptions('instance.certref', nebulaCertFp);
            nebulaLabelOptions('instance.trusted_cas', nebulaCaFp);
        });

        // -----------------------------------------------------------------------
        // Apply / reconfigure button
        // -----------------------------------------------------------------------
        $("#reconfigureAct").SimpleActionButton();

        // Persistent "apply needed": if a prior change has not been reconfigured,
        // raise the change notice on load too (the marker is cleared on Apply).
        ajaxGet('/api/nebula/service/dirty', {}, function (data) {
            if (data && data.isDirty) {
                $(document).trigger('settings-changed');
            }
        });

        // Render the page-header service controls (start/restart/stop) on load.
        // Core service pages (OPNsense/IPsec/tunnels.volt, OPNsense/IDS/index.volt)
        // call this in $(document).ready; without it our controls only appear
        // after an Apply (which calls it via data-service-widget) and vanish on
        // reload. /api/nebula/service/status returns {status, widget} as expected.
        updateServiceControlUI('nebula');
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#instances_tab">{{ lang._('Instances') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="instances_tab" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridInstance + {
            'command_width': '160'
        }) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {
    'data_endpoint': '/api/nebula/service/reconfigure',
    'data_service_widget': 'nebula',
    'data_error_title': 'Error reconfiguring Nebula'
}) }}

{# ============================================================================
   Edit dialog (standard CRUD — base_dialog handles save via UIBootgrid)
============================================================================ #}
{{ partial("layout_partials/base_dialog", [
    'fields': formDialogInstance,
    'id':     formGridInstance['edit_dialog_id'],
    'label':  lang._('Edit Instance')
]) }}
