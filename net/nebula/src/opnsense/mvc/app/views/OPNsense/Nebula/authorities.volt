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

    /**
     * Generic custom-dialog POST helper.
     *
     * @param {string} frm_id        — id of the <form> element (frm_XXX)
     * @param {string} field_prefix  — field namespace used in form field ids (e.g. 'authority')
     * @param {string} endpoint      — API endpoint to POST to
     * @param {string} dialog_id     — Bootstrap modal id to hide on success
     * @param {string} grid_id       — bootgrid table id to reload on success
     */
    function nebula_custom_save(frm_id, field_prefix, endpoint, dialog_id, grid_id) {
        let params = {};
        $("#" + frm_id + " input, #" + frm_id + " select, #" + frm_id + " textarea").each(function () {
            let $el  = $(this);
            let name = $el.attr('id');
            if (name) {
                // Strip the "field." prefix that base_dialog generates (e.g. "authority.descr" → "descr")
                let parts = name.split('.');
                let key   = parts.length > 1 ? parts.slice(1).join('.') : name;
                // Tokenize select_multiple fields return an array; join them to a
                // comma-separated string so the generate/import actions (which read
                // these as strings) get the expected shape.
                let val = $el.val();
                params[key] = Array.isArray(val) ? val.join(',') : val;
            }
        });

        clearFormValidation(frm_id);

        let $save_btn  = $("#btn_" + dialog_id + "_save");
        let $save_icon = $("#btn_" + dialog_id + "_save_progress");
        $save_btn.prop('disabled', true);
        $save_icon.addClass("fa fa-spinner fa-pulse");

        ajaxCall(endpoint, params, function (data, status) {
            $save_btn.prop('disabled', false);
            $save_icon.removeClass("fa fa-spinner fa-pulse");

            if (data && data.validations) {
                // handleFormValidation matches against the full field id (e.g. "authority.descr"),
                // but the API returns bare keys (e.g. "descr"). Re-prefix them.
                let prefixed = {};
                $.each(data.validations, function (k, v) {
                    prefixed[field_prefix + '.' + k] = v;
                });
                handleFormValidation(frm_id, prefixed);
            } else if (data && data.result === 'saved') {
                $("#" + dialog_id).modal('hide');
                $("#" + grid_id).bootgrid('reload');
            } else {
                let msg = (data && data.error) ? data.error : '{{ lang._('An unexpected error occurred.') }}';
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Error') }}',
                    message: $("<div/>").text(msg).html()
                });
            }
        });
    }

    // Open the Generate CA dialog (reset + defaults).
    function nebula_open_generate_ca() {
        clearFormValidation('frm_DialogAuthorityGenerate');
        $("#frm_DialogAuthorityGenerate")[0].reset();
        // Populate curve dropdown if not already done (idempotent).
        let $curve = $("#authority\\.curve");
        if ($curve.find("option").length === 0) {
            $curve.append($("<option/>").val("25519").text("25519 (Curve25519, default)"));
            $curve.append($("<option/>").val("P256").text("P256 (NIST P-256)"));
        }
        // Set defaults
        $curve.val('25519');
        $("#authority\\.duration_days").val('365');
        $(".selectpicker").selectpicker('refresh');
        $("#DialogAuthorityGenerate").modal('show');
        // Initialise the tokenize (chip) fields — this manually-shown dialog is
        // not initialised by the bootgrid edit path. Idempotent.
        formatTokenizersUI();
    }

    // Open the Import CA dialog (reset).
    function nebula_open_import_ca() {
        clearFormValidation('frm_DialogAuthorityImport');
        $("#frm_DialogAuthorityImport")[0].reset();
        $("#DialogAuthorityImport").modal('show');
    }

    /**
     * Confirm-then-purge expired entries via the given API endpoint, reload the
     * grid, raise the apply notice, and report the result. Shared shape across
     * the Authorities / Certificates / Blocklist pages; `label` names the entries
     * in the prompts. Endpoints that guard references return skipped/skippedNames,
     * surfaced in the result; endpoints with nothing to guard simply omit them.
     */
    function nebula_purge_expired(url, label) {
        BootstrapDialog.show({
            type: BootstrapDialog.TYPE_WARNING,
            title: '{{ lang._('Purge expired') }}',
            message: '{{ lang._('Remove all expired') }} ' + label +
                '? {{ lang._('Entries still in use are kept.') }}',
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
                        ajaxCall(url, {}, function (data, status) {
                            if (!data || data.result !== 'saved') {
                                return;
                            }
                            $("#{{formGridAuthority['table_id']}}").bootgrid('reload');
                            $(document).trigger('settings-changed');
                            let msg = '{{ lang._('Purged') }} ' + (data.removed || 0) +
                                ' ' + label + '.';
                            if (data.skipped) {
                                msg += ' {{ lang._('Skipped') }} ' + data.skipped +
                                    ' {{ lang._('still in use') }} (' +
                                    (data.skippedNames || []).join(', ') + ').';
                            }
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

    /**
     * Helper: show a parsed cert-info dialog for an authority uuid.
     */
    function nebula_show_ca_info(uuid) {
        ajaxGet('/api/nebula/authority/info/' + uuid, {}, function (data, status) {
            if (data && data.info) {
                let info  = data.info;
                let lines = [];
                $.each(info, function (i, cert) {
                    $.each(cert, function (k, v) {
                        if (typeof v === 'object') {
                            lines.push(k + ':');
                            $.each(v, function (dk, dv) {
                                lines.push('  ' + dk + ': ' + dv);
                            });
                        } else {
                            lines.push(k + ': ' + v);
                        }
                    });
                    lines.push('');
                });
                BootstrapDialog.show({
                    title: '{{ lang._('Authority Info') }}',
                    type: BootstrapDialog.TYPE_INFO,
                    message: $("<pre/>").text(lines.join("\n")).css({
                        'max-height': '60vh',
                        'overflow-y': 'auto',
                        'font-size': '12px'
                    }),
                    cssClass: 'monospace-dialog'
                });
            } else {
                let msg = (data && data.error) ? data.error : '{{ lang._('Could not retrieve authority info.') }}';
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Error') }}',
                    message: $("<div/>").text(msg).html()
                });
            }
        });
    }

    /**
     * Helper: download a PEM file for an authority uuid and type ('crt' or 'key').
     */
    function nebula_download_ca_file(uuid, type) {
        ajaxCall('/api/nebula/authority/generate_file/' + uuid + '/' + type, {}, function (data, status) {
            if (data && data.status === 'ok') {
                let ext      = type === 'key' ? 'key' : 'crt';
                let filename = (data.descr || uuid) + '.' + ext;
                download_content(data.payload, filename, 'application/octet-stream');
            } else {
                let msg = (data && data.error) ? data.error : '{{ lang._('Download failed.') }}';
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Error') }}',
                    message: $("<div/>").text(msg).html()
                });
            }
        });
    }

    $(document).ready(function () {

        // -----------------------------------------------------------------------
        // Authorities grid
        //
        // The toolbar actions (Generate CA / Import CA) are registered as bootgrid
        // *footer commands* (footer:true, primary:true) rather than hand-rolled
        // <tfoot> buttons. The new Tabulator-based UIBootgrid detaches any custom
        // tfoot button on init and only re-appends it asynchronously on tableBuilt,
        // so a direct $("#btn_x").click() bound right after .UIBootgrid() binds to a
        // detached (empty) node and never fires. Registering via `commands` lets
        // bootgrid render and bind the buttons itself, the way Trust/OpenVPN do.
        // -----------------------------------------------------------------------
        $("#{{formGridAuthority['table_id']}}").UIBootgrid({
            search: '/api/nebula/authority/search_item',
            get:    '/api/nebula/authority/get_item/',
            set:    '/api/nebula/authority/set_item/',
            del:    '/api/nebula/authority/del_item/',
            options: {
                selection: false,
                formatters: {
                    // First 8 hex of the fingerprint — enough to disambiguate
                    // like-named CAs without showing the full 64-char digest.
                    "nebula_fp8": function (column, row) {
                        let fp = (row.fingerprint || '').toString();
                        return fp ? $('<code/>').text(fp.substring(0, 8)).prop('outerHTML') : '';
                    }
                }
            },
            commands: {
                download_crt: {
                    method: function (event) {
                        let uuid = $(this).data('row-id') !== undefined ? $(this).data('row-id') : '';
                        nebula_download_ca_file(uuid, 'crt');
                    },
                    classname: 'fa fa-fw fa-cloud-download',
                    title: '{{ lang._('Download certificate') }}',
                    sequence: 10
                },
                download_key: {
                    method: function (event) {
                        let uuid = $(this).data('row-id') !== undefined ? $(this).data('row-id') : '';
                        nebula_download_ca_file(uuid, 'key');
                    },
                    classname: 'fa fa-fw fa-key',
                    title: '{{ lang._('Download key') }}',
                    sequence: 20
                },
                view: {
                    method: function (event) {
                        let uuid = $(this).data('row-id') !== undefined ? $(this).data('row-id') : '';
                        nebula_show_ca_info(uuid);
                    },
                    classname: 'fa fa-fw fa-search',
                    title: '{{ lang._('Inspect') }}',
                    sequence: 30
                },
                generate_ca: {
                    method: nebula_open_generate_ca,
                    classname: 'fa fa-fw fa-plus',
                    title: '{{ lang._('Generate a new Nebula CA') }}',
                    sequence: 10,
                    footer: true,
                    primary: true
                },
                import_ca: {
                    method: nebula_open_import_ca,
                    classname: 'fa fa-fw fa-cloud-upload',
                    title: '{{ lang._('Import an existing Nebula CA') }}',
                    sequence: 20,
                    footer: true,
                    primary: true
                },
                purge_expired: {
                    method: function (event) {
                        nebula_purge_expired(
                            '/api/nebula/authority/purge_expired',
                            '{{ lang._('certificate authorities') }}'
                        );
                    },
                    classname: 'fa fa-fw fa-clock-o',
                    title: '{{ lang._('Purge expired certificate authorities') }}',
                    sequence: 30,
                    footer: true,
                    primary: false
                }
            }
        });

        // Hide the download-key button on rows that have no private key stored, and
        // mark encrypted CAs with a small lock icon in the Can-sign cell so the user
        // knows a passphrase is needed to sign with them.
        $("#{{formGridAuthority['table_id']}}").on("loaded.rs.jquery.bootgrid", function () {
            let $grid = $(this);
            $.each($grid.bootgrid('getCurrentRows'), function (i, row) {
                let $tr = $grid.find('tr[data-row-id="' + row.uuid + '"]');
                if (row.has_key !== '1') {
                    $tr.find('.command-download_key').hide();
                }
                if (row.key_encrypted === '1') {
                    let $cell = $tr.find('td[data-column-id="can_sign"]');
                    if ($cell.length && $cell.find('.fa-lock').length === 0) {
                        $cell.append(
                            $('<i class="fa fa-lock" style="margin-left:6px;"></i>')
                                .attr('title', '{{ lang._('Encrypted CA — a passphrase is required to sign') }}')
                        );
                    }
                }
            });
        });

        // Save buttons for authority custom dialogs (live in static modal markup,
        // never detached, so direct binding is safe here).
        $("#btn_DialogAuthorityGenerate_save").click(function () {
            nebula_custom_save(
                'frm_DialogAuthorityGenerate',
                'authority',
                '/api/nebula/authority/generate',
                'DialogAuthorityGenerate',
                '{{formGridAuthority["table_id"]}}'
            );
        });

        $("#btn_DialogAuthorityImport_save").click(function () {
            nebula_custom_save(
                'frm_DialogAuthorityImport',
                'authority',
                '/api/nebula/authority/import',
                'DialogAuthorityImport',
                '{{formGridAuthority["table_id"]}}'
            );
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#authorities_tab">{{ lang._('Authorities') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="authorities_tab" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridAuthority + {
            'command_width': '220',
            'hide_add': true,
            'hide_delete': true
        }) }}
    </div>
</div>

{# ============================================================================
   Edit dialog (standard CRUD — base_dialog handles save via UIBootgrid)
============================================================================ #}
{{ partial("layout_partials/base_dialog", [
    'fields': formDialogAuthority,
    'id':     formGridAuthority['edit_dialog_id'],
    'label':  lang._('Edit Authority')
]) }}

{# ============================================================================
   Custom-action dialogs (generate / import — manual POST via ajaxCall)
============================================================================ #}
{{ partial("layout_partials/base_dialog", [
    'fields': formAuthorityGenerate,
    'id':     'DialogAuthorityGenerate',
    'label':  lang._('Generate Certificate Authority')
]) }}

{{ partial("layout_partials/base_dialog", [
    'fields': formAuthorityImport,
    'id':     'DialogAuthorityImport',
    'label':  lang._('Import Certificate Authority')
]) }}
