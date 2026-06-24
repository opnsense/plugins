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

        // -----------------------------------------------------------------------
        // Deep-link filter: when arriving from the certificate "Block" button the
        // URL may carry ?instance=<uuid>. Read it and scope the grid's search
        // endpoint to that instance so only entries that apply to it are shown.
        // (Blank = "All instances" = global entries plus every instance's entries.)
        // -----------------------------------------------------------------------
        const deepLinkInstance = new URLSearchParams(window.location.search).get('instance') || '';

        // The instance the grid is currently scoped to. Read by the grid's
        // requestHandler (below) and updated by the dropdown without ever tearing
        // the grid down. Seeded from the deep-link, if present.
        let scopedInstance = deepLinkInstance;

        // Wire the bootgrid change-alert. UIBootgrid.showSaveAlert() (fired after
        // every add/edit/delete/toggle) slides down the element named by the
        // table's data-editAlert attribute — same mechanism core uses on
        // OPNsense/IDS/index.volt. base_bootgrid_table does not forward editAlert,
        // so set it on the table element BEFORE UIBootgrid() captures it.

        // The grid is created ONCE. Instance scoping is applied per-request via a
        // requestHandler that injects the selected uuid into the search POST
        // (mirrors firewall.volt). The search endpoint reads the `instance` param
        // via $request->get() and filters server-side (global OR this instance).
        const grid_block = $("#{{formGridBlocklistEntry['table_id']}}").UIBootgrid({
            search: '/api/nebula/blocklist/search_item',
            get:    '/api/nebula/blocklist/get_item/',
            set:    '/api/nebula/blocklist/set_item/',
            add:    '/api/nebula/blocklist/add_item/',
            del:    '/api/nebula/blocklist/del_item/',
            toggle: '/api/nebula/blocklist/toggle_item/',
            options: {
                selection: false,
                requestHandler: function (request) {
                    if (scopedInstance) {
                        request['instance'] = scopedInstance;
                    }
                    return request;
                }
            },
            commands: {
                import_block: {
                    method: nebula_open_import_block,
                    classname: 'fa fa-fw fa-cloud-upload',
                    title: '{{ lang._('Import a list of fingerprints') }}',
                    sequence: 10,
                    footer: true,
                    primary: true
                },
                purge_expired: {
                    method: nebula_purge_expired_block,
                    classname: 'fa fa-fw fa-clock-o',
                    title: '{{ lang._('Purge expired blocklist entries') }}',
                    sequence: 20,
                    footer: true,
                    primary: false
                }
            }
        });

        // -----------------------------------------------------------------------
        // Instance filter dropdown (above the grid). Populated from the instance
        // API plus an "All" option; changing it re-points the grid search and
        // reloads in place. Pre-selects the deep-linked instance when present.
        // -----------------------------------------------------------------------
        const $filter = $("#nebula_block_instance_filter");

        ajaxGet('/api/nebula/instance/search_item', {}, function (data, status) {
            $filter.append($("<option/>").val('').text('{{ lang._('All instances') }}'));
            // Explicit "Global" view: only the global blocklist (the list every
            // instance inherits), via the __global__ sentinel the search endpoint
            // recognises. Selecting an instance below shows global + that instance.
            $filter.append($("<option/>").val('__global__').text('{{ lang._('Global') }}'));
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    let label = row.description ? row.description : row.uuid;
                    $filter.append($("<option/>").val(row.uuid).text(label));
                });
            }
            // Pre-select the deep-linked instance, if any.
            $filter.val(deepLinkInstance);
            if ($filter.hasClass('selectpicker')) {
                $filter.selectpicker('refresh');
            }
        });

        // Changing the dropdown only updates the scope and reloads the existing
        // grid in place — no destroy/recreate, so no orphaned search widgets.
        $filter.on('changed.bs.select change', function () {
            scopedInstance = $(this).val() || '';
            $("#{{formGridBlocklistEntry['table_id']}}").bootgrid('reload');
        });

        // -----------------------------------------------------------------------
        // Auto-fill: when the edit dialog's certificate dropdown changes, copy the
        // chosen cert's fingerprint + expiry (valid_to) into those fields. The
        // certref is a convenience picker; the fingerprint is what is stored and
        // rendered. We fetch the cert list once and index by uuid.
        // -----------------------------------------------------------------------
        let nebula_cert_index = {};
        ajaxGet('/api/nebula/certificate/search_item', {}, function (data, status) {
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    nebula_cert_index[row.uuid] = row;
                });
            }
        });

        // base_dialog renders the certref field with id "entry.certref" (escape the
        // dot). Certificate and fingerprint are MUTUALLY EXCLUSIVE: choosing a
        // known certificate fills + locks the fingerprint (and expiry); clearing
        // it re-enables manual fingerprint entry. This handler also fires when the
        // dialog populates the selectpicker on open, so the locked/unlocked state
        // is correct for edits too.
        $(document).on('changed.bs.select change', '#entry\\.certref', function () {
            let uuid = $(this).val() || '';
            let cert = nebula_cert_index[uuid];
            let $fp = $("#entry\\.fingerprint");
            if (uuid && cert) {
                if (cert.fingerprint) {
                    $fp.val(cert.fingerprint);
                }
                if (cert.valid_to !== undefined) {
                    // Store the date portion only (the model compares on YYYY-MM-DD).
                    $("#entry\\.expiry").val(String(cert.valid_to).substring(0, 10));
                }
                // Cert chosen → fingerprint is derived; lock it.
                $fp.prop('readonly', true);
            } else {
                // No cert → allow manual fingerprint entry.
                $fp.prop('readonly', false);
            }
        });

        // Normalise a pasted fingerprint to bare lowercase hex (strip the colons,
        // spaces and uppercase that copy-from-a-cert-tool often carries) so it
        // matches the 64-hex the model requires.
        $(document).on('blur change', '#entry\\.fingerprint', function () {
            let v = ($(this).val() || '').toLowerCase().replace(/[^0-9a-f]/g, '');
            if (v !== $(this).val()) {
                $(this).val(v);
            }
        });

        // -----------------------------------------------------------------------
        // Expiry date picker. The field is free text and the renderer now parses
        // leniently (strtotime), but the picker keeps entries in the canonical
        // yyyy-mm-dd the model expects. clearBtn lets the user wipe it back to
        // "never expires".
        // -----------------------------------------------------------------------
        $("#entry\\.expiry").datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true,
            clearBtn: true,
            orientation: 'bottom auto'
        });

        // -----------------------------------------------------------------------
        // Purge expired blocklist entries — confirm, delete every entry past its
        // Expiry date, reload the grid and raise the apply notice. Unlike the CA
        // and certificate grids there is nothing referencing a blocklist entry,
        // so none are ever skipped (the renderer keeps blocking until purged).
        // -----------------------------------------------------------------------
        function nebula_purge_expired_block() {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_WARNING,
                title: '{{ lang._('Purge expired') }}',
                message: '{{ lang._('Remove all blocklist entries whose Expiry date has passed?') }}',
                buttons: [
                    {
                        label: '{{ lang._('Cancel') }}',
                        action: function (d) { d.close(); }
                    },
                    {
                        label: '{{ lang._('Purge expired') }}',
                        cssClass: 'btn-primary',
                        action: function (d) {
                            d.close();
                            ajaxCall('/api/nebula/blocklist/purge_expired', {}, function (data, status) {
                                if (!data || data.result !== 'saved') {
                                    return;
                                }
                                $("#{{formGridBlocklistEntry['table_id']}}").bootgrid('reload');
                                $(document).trigger('settings-changed');
                                let msg = '{{ lang._('Purged') }} ' + (data.removed || 0) +
                                    ' {{ lang._('expired blocklist entries.') }}';
                                BootstrapDialog.show({
                                    type: BootstrapDialog.TYPE_INFO,
                                    title: '{{ lang._('Purge expired') }}',
                                    message: $('<div/>').text(msg).html()
                                });
                            });
                        }
                    }
                ]
            });
        }

        // -----------------------------------------------------------------------
        // Bulk import dialog — populate scope + instance dropdowns, then show it.
        // Declared as a function so the grid's import_block command (above) can
        // reference it before this line executes (hoisting).
        // -----------------------------------------------------------------------
        function nebula_open_import_block() {
            clearFormValidation('frm_DialogBlocklistImport');
            $("#frm_DialogBlocklistImport")[0].reset();

            let $scope = $("#import\\.scope");
            if ($scope.find('option').length === 0) {
                $scope.append($("<option/>").val('global').text('{{ lang._('Global (all instances)') }}'));
                $scope.append($("<option/>").val('instance').text('{{ lang._('This instance') }}'));
            }
            $scope.val('global');

            let $inst = $("#import\\.instance");
            $inst.empty();
            ajaxGet('/api/nebula/instance/search_item', {}, function (data) {
                if (data && data.rows) {
                    $.each(data.rows, function (i, r) {
                        $inst.append($("<option/>").val(r.uuid).text(r.description ? r.description : r.uuid));
                    });
                }
                $("#DialogBlocklistImport .selectpicker").selectpicker('refresh');
            });
            $("#DialogBlocklistImport .selectpicker").selectpicker('refresh');
            $("#DialogBlocklistImport").modal('show');
        }

        $("#btn_DialogBlocklistImport_save").click(function () {
            clearFormValidation('frm_DialogBlocklistImport');
            let params = {
                scope:        $("#import\\.scope").val() || 'global',
                instance:     $("#import\\.instance").val() || '',
                descr:        $("#import\\.descr").val() || '',
                fingerprints: $("#import\\.fingerprints").val() || ''
            };
            ajaxCall('/api/nebula/blocklist/import', params, function (data, status) {
                if (data && data.result === 'saved') {
                    $("#DialogBlocklistImport").modal('hide');
                    $("#{{formGridBlocklistEntry['table_id']}}").bootgrid('reload');
                    $(document).trigger('settings-changed');
                    let msg = '{{ lang._('Imported') }} ' + (data.added || 0) +
                        ', {{ lang._('skipped (already blocked)') }} ' + (data.skipped || 0);
                    if (data.invalid && data.invalid.length) {
                        msg += ', {{ lang._('invalid') }} ' + data.invalid.length +
                            ' (' + data.invalid.slice(0, 5).join(', ') +
                            (data.invalid.length > 5 ? ', …' : '') + ')';
                    }
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_INFO,
                        title: '{{ lang._('Blocklist import') }}',
                        message: $('<div/>').text(msg).html()
                    });
                } else if (data && data.validations) {
                    handleFormValidation('frm_DialogBlocklistImport', data.validations);
                }
            });
        });

        // -----------------------------------------------------------------------
        // Apply / reconfigure button — re-render Nebula configs after edits.
        // -----------------------------------------------------------------------
        $("#reconfigureAct").SimpleActionButton();

        // Persistent "apply needed": if a prior change has not been reconfigured,
        // raise the change notice on load too (the marker is cleared on Apply).
        ajaxGet('/api/nebula/service/dirty', {}, function (data) {
            if (data && data.isDirty) {
                $(document).trigger('settings-changed');
            }
        });

        // Render the page-header service controls (start/restart/stop) on load —
        // same as core OPNsense/IDS/index.volt. Without it the controls only
        // appear after an Apply and vanish on reload.
        updateServiceControlUI('nebula');
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#blocklist_tab">{{ lang._('Blocklist') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="blocklist_tab" class="tab-pane fade in active">
        <div class="col-md-12" style="margin-top: 10px; margin-bottom: 10px;">
            <label for="nebula_block_instance_filter">{{ lang._('Instance') }}</label>
            <select id="nebula_block_instance_filter" class="selectpicker" data-width="300px"
                    data-live-search="true"></select>
        </div>
        {{ partial('layout_partials/base_bootgrid_table', formGridBlocklistEntry + {
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
    'fields': formDialogBlocklistEntry,
    'id':     formGridBlocklistEntry['edit_dialog_id'],
    'label':  lang._('Edit Blocklist Entry')
]) }}

{# ============================================================================
   Bulk-import dialog (custom action — manual POST via ajaxCall)
============================================================================ #}
{{ partial("layout_partials/base_dialog", [
    'fields': formBlocklistImport,
    'id':     'DialogBlocklistImport',
    'label':  lang._('Import Blocklist Fingerprints')
]) }}
