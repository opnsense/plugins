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
     * CA network constraints indexed by UUID — populated when the Sign dialog
     * fetches the authority list.  Used to update the hint below the caref dropdown.
     * Each entry also carries key_encrypted ('1'/'0') so the passphrase field can be
     * shown + marked required only for encrypted CAs.
     */
    let nebula_ca_networks = {};

    /**
     * Show or hide the Sign dialog's passphrase field based on whether the
     * currently selected CA is encrypted.  An encrypted CA needs a passphrase to
     * decrypt its key for signing (supplied per-operation, never stored); an
     * unencrypted CA does not, so we hide the field and clear it to avoid sending a
     * stray value.  We toggle the surrounding form-group (the <tr>/row that
     * base_dialog renders) so the label hides with the input.
     */
    function nebula_update_passphrase_visibility() {
        let uuid = $("#DialogCertificateSign #certificate\\.caref").val();
        let $field = $("#DialogCertificateSign #certificate\\.passphrase");
        // base_dialog renders each field inside a <tr>; fall back to the closest
        // .form-group for non-table layouts.
        let $row = $field.closest('tr');
        if ($row.length === 0) {
            $row = $field.closest('.form-group');
        }
        let encrypted = !!(uuid && nebula_ca_networks[uuid] && nebula_ca_networks[uuid].key_encrypted === '1');
        if (encrypted) {
            $row.show();
            $field.prop('required', true);
        } else {
            $field.val('');
            $field.prop('required', false);
            $row.hide();
        }
    }

    /**
     * Update the CA-networks hint paragraph in the Sign dialog based on the
     * currently selected caref value.
     */
    function nebula_update_ca_networks_hint() {
        let uuid = $("#DialogCertificateSign #certificate\\.caref").val();
        let $hint = $("#nebula_ca_networks_hint");
        if (!uuid || !nebula_ca_networks[uuid]) {
            $hint.text('{{ lang._('Selected CA allows networks: (none selected)') }}');
            return;
        }
        let ca = nebula_ca_networks[uuid];
        let nets   = ca.networks        || '';
        let unsafe = ca.unsafe_networks || '';
        let netStr   = nets   !== '' ? nets   : '{{ lang._('any (unrestricted)') }}';
        let unsafeStr = unsafe !== '' ? unsafe : '{{ lang._('none') }}';
        $hint.text('{{ lang._('Selected CA allows networks: ') }}' + netStr + ' {{ lang._('(unsafe: ') }}' + unsafeStr + ')');
    }

    /**
     * Populate the CA <select> inside a specific dialog.
     *
     * Fetches /api/nebula/authority/search_item and fills the <select> whose id
     * ends with the field name "caref" within the given dialog container.
     *
     * We scope the lookup to dialog_id because both the Sign and Import dialogs
     * have a field with id="certificate.caref".  A bare $("#certificate\\.caref")
     * only matches the first one in the DOM.  Scoping avoids duplicate-ID
     * ambiguity and keeps each dialog independent.
     *
     * The selectpicker refresh is deferred until inside the AJAX callback so that
     * it runs AFTER the <option> elements have been appended — calling it before
     * the callback returns would leave the Bootstrap dropdown empty.
     *
     * When called for the Sign dialog, also caches each CA's networks/unsafe_networks
     * so that nebula_update_ca_networks_hint() can display the constraint hint.
     *
     * @param {string} dialog_id  — id of the Bootstrap modal div (e.g. 'DialogCertificateSign')
     * @param {string} field_id   — literal id attr of the <select> (e.g. 'certificate.caref')
     */
    function nebula_populate_caref(dialog_id, field_id) {
        ajaxGet('/api/nebula/authority/search_item', {}, function (data, status) {
            // Scope to the specific dialog to avoid duplicate-id ambiguity.
            let $sel = $("#" + dialog_id).find("#" + field_id.replace(/\./g, '\\.'));
            $sel.empty().append($("<option value=''/>").text('{{ lang._('— select CA —') }}'));
            if (data && data.rows) {
                $.each(data.rows, function (i, row) {
                    // Only offer CAs that can currently sign (is_ca + a key present).
                    // Encrypted CAs ARE signable now (can_sign='1') and appear here;
                    // the passphrase field is shown for them on selection.  Cert-only
                    // CAs have can_sign='0' and remain excluded.
                    if (row.can_sign !== '1') {
                        return;
                    }
                    // Disambiguate same-named CAs with the first 8 chars of the CA
                    // fingerprint (git-short-hash style), e.g. "demo-ca (06a57199…)".
                    let label = row.descr || row.cn || row.uuid;
                    let fp    = row.fingerprint || '';
                    if (fp !== '') {
                        label += ' (' + fp.substring(0, 8) + '…)';
                    }
                    $sel.append($("<option/>").val(row.uuid).text(label));
                    // Cache network constraints + encryption flag for the Sign dialog.
                    if (dialog_id === 'DialogCertificateSign') {
                        nebula_ca_networks[row.uuid] = {
                            networks:        row.networks        || '',
                            unsafe_networks: row.unsafe_networks || '',
                            key_encrypted:   row.key_encrypted   || '0'
                        };
                    }
                });
            }
            // Refresh AFTER options are added so Bootstrap-select renders them.
            $sel.selectpicker('refresh');
            // Update the networks hint for whichever CA is currently selected
            // (after a reset the selection will be blank, so this shows "none selected").
            if (dialog_id === 'DialogCertificateSign') {
                nebula_update_ca_networks_hint();
                nebula_update_passphrase_visibility();
            }
        });
    }

    /**
     * Generic custom-dialog POST helper.
     *
     * @param {string} frm_id        — id of the <form> element (frm_XXX)
     * @param {string} field_prefix  — field namespace used in form field ids (e.g. 'certificate')
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
                // Strip the "field." prefix that base_dialog generates (e.g. "certificate.descr" → "descr")
                let parts = name.split('.');
                let key   = parts.length > 1 ? parts.slice(1).join('.') : name;
                // Tokenize select_multiple fields return an array; join them to a
                // comma-separated string so the sign/import actions (which read
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
                // handleFormValidation matches against the full field id (e.g. "certificate.descr"),
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

    // Open the Sign Certificate dialog (reset + defaults + CA list).
    function nebula_open_sign_cert() {
        clearFormValidation('frm_DialogCertificateSign');
        $("#frm_DialogCertificateSign")[0].reset();
        // duration_days is intentionally left blank after reset — an empty value
        // causes the controller to omit -duration from nebula-cert sign, so the
        // cert expires just before the CA (the recommended default).
        // Hide the passphrase field up front (no CA selected yet); the populate
        // callback re-evaluates visibility once the dropdown is filled and again on
        // every caref change.
        nebula_update_passphrase_visibility();
        // Populate CA dropdown; selectpicker refresh and networks hint update
        // happen inside the callback once options are actually appended (async AJAX).
        nebula_populate_caref('DialogCertificateSign', 'certificate.caref');
        $("#DialogCertificateSign").modal('show');
        // Initialise the tokenize (chip) fields — this manually-shown dialog is
        // not initialised by the bootgrid edit path. Idempotent.
        formatTokenizersUI();
    }

    // Open the Import Certificate dialog (reset).  No CA dropdown — the signing CA
    // is derived from the cert's issuer fingerprint at render time.
    function nebula_open_import_cert() {
        clearFormValidation('frm_DialogCertificateImport');
        $("#frm_DialogCertificateImport")[0].reset();
        $("#DialogCertificateImport").modal('show');
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
                            $("#{{formGridCertificate['table_id']}}").bootgrid('reload');
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
     * Helper: download a PEM file for a certificate uuid and type ('crt' or 'key').
     */
    function nebula_download_cert_file(uuid, type) {
        ajaxCall('/api/nebula/certificate/generate_file/' + uuid + '/' + type, {}, function (data, status) {
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

    /**
     * Block a certificate by certref: create a GLOBAL blocklist entry (prefilled
     * with the cert's fingerprint + expiry) via the blocklist API, then navigate
     * to the Blocklist page. The endpoint is idempotent — re-blocking an already
     * globally-blocked cert is a no-op that still lands the user on the page.
     */
    function nebula_block_cert(uuid) {
        if (!uuid) {
            return;
        }
        ajaxCall('/api/nebula/blocklist/block_cert/' + uuid, {}, function (data, status) {
            if (data && (data.result === 'saved' || data.result === 'exists')) {
                // Navigate to the Blocklist page so the new global entry is shown.
                window.location.href = '/ui/nebula/blocklist';
            } else {
                let msg = (data && data.error) ? data.error : '{{ lang._('Could not block certificate.') }}';
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
        // Certificates grid
        //
        // Toolbar actions (Sign / Import) are registered as bootgrid *footer
        // commands* (footer:true, primary:true) instead of hand-rolled <tfoot>
        // buttons. UIBootgrid detaches custom tfoot buttons on init and only
        // re-appends them async on tableBuilt, so a direct $("#btn_x").click()
        // bound right after .UIBootgrid() lands on a detached node and never
        // fires. Registering via `commands` lets bootgrid bind them itself.
        // -----------------------------------------------------------------------
        $("#{{formGridCertificate['table_id']}}").UIBootgrid({
            search: '/api/nebula/certificate/search_item',
            get:    '/api/nebula/certificate/get_item/',
            set:    '/api/nebula/certificate/set_item/',
            del:    '/api/nebula/certificate/del_item/',
            options: {
                selection: false,
                formatters: {
                    // First 8 hex of the certificate fingerprint — disambiguates
                    // like-named certs without the full 64-char digest.
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
                        nebula_download_cert_file(uuid, 'crt');
                    },
                    classname: 'fa fa-fw fa-cloud-download',
                    title: '{{ lang._('Download certificate') }}',
                    sequence: 10
                },
                download_key: {
                    method: function (event) {
                        let uuid = $(this).data('row-id') !== undefined ? $(this).data('row-id') : '';
                        nebula_download_cert_file(uuid, 'key');
                    },
                    classname: 'fa fa-fw fa-key',
                    title: '{{ lang._('Download key') }}',
                    sequence: 20
                },
                view: {
                    method: function (event) {
                        let uuid = $(this).data('row-id') !== undefined ? $(this).data('row-id') : '';
                        ajaxGet('/api/nebula/certificate/info/' + uuid, {}, function (data, status) {
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
                                    title: '{{ lang._('Certificate Info') }}',
                                    type: BootstrapDialog.TYPE_INFO,
                                    message: $("<pre/>").text(lines.join("\n")).css({
                                        'max-height': '60vh',
                                        'overflow-y': 'auto',
                                        'font-size': '12px'
                                    }),
                                    cssClass: 'monospace-dialog'
                                });
                            } else {
                                let msg = (data && data.error) ? data.error : '{{ lang._('Could not retrieve certificate info.') }}';
                                BootstrapDialog.show({
                                    type: BootstrapDialog.TYPE_DANGER,
                                    title: '{{ lang._('Error') }}',
                                    message: $("<div/>").text(msg).html()
                                });
                            }
                        });
                    },
                    classname: 'fa fa-fw fa-search',
                    title: '{{ lang._('Inspect') }}',
                    sequence: 30
                },
                block_cert: {
                    method: function (event) {
                        let uuid = $(this).data('row-id') !== undefined ? $(this).data('row-id') : '';
                        nebula_block_cert(uuid);
                    },
                    classname: 'fa fa-fw fa-ban',
                    title: '{{ lang._('Block this certificate (global blocklist entry)') }}',
                    sequence: 40
                },
                sign_cert: {
                    method: nebula_open_sign_cert,
                    classname: 'fa fa-fw fa-certificate',
                    title: '{{ lang._('Sign a new host certificate under a CA') }}',
                    sequence: 10,
                    footer: true,
                    primary: true
                },
                import_cert: {
                    method: nebula_open_import_cert,
                    classname: 'fa fa-fw fa-cloud-upload',
                    title: '{{ lang._('Import a pre-signed host certificate') }}',
                    sequence: 20,
                    footer: true,
                    primary: true
                },
                purge_expired: {
                    method: function (event) {
                        nebula_purge_expired(
                            '/api/nebula/certificate/purge_expired',
                            '{{ lang._('certificates') }}'
                        );
                    },
                    classname: 'fa fa-fw fa-clock-o',
                    title: '{{ lang._('Purge expired certificates') }}',
                    sequence: 30,
                    footer: true,
                    primary: false
                }
            }
        });

        // Hide the download-key button on rows that have no private key stored.
        $("#{{formGridCertificate['table_id']}}").on("loaded.rs.jquery.bootgrid", function () {
            let $grid = $(this);
            $.each($grid.bootgrid('getCurrentRows'), function (i, row) {
                if (row.has_key !== '1') {
                    $grid.find('tr[data-row-id="' + row.uuid + '"] .command-download_key').hide();
                }
            });
        });

        // Update the CA-networks hint whenever the CA dropdown selection changes.
        // The selectpicker fires a 'changed.bs.select' event; fall back to 'change'
        // for non-selectpicker environments.
        $("#DialogCertificateSign").on('changed.bs.select change', '#certificate\\.caref', function () {
            nebula_update_ca_networks_hint();
            nebula_update_passphrase_visibility();
        });

        // Save buttons for certificate custom dialogs (static modal markup, never
        // detached, so direct binding is safe here).
        $("#btn_DialogCertificateSign_save").click(function () {
            nebula_custom_save(
                'frm_DialogCertificateSign',
                'certificate',
                '/api/nebula/certificate/sign',
                'DialogCertificateSign',
                '{{formGridCertificate["table_id"]}}'
            );
        });

        $("#btn_DialogCertificateImport_save").click(function () {
            nebula_custom_save(
                'frm_DialogCertificateImport',
                'certificate',
                '/api/nebula/certificate/import',
                'DialogCertificateImport',
                '{{formGridCertificate["table_id"]}}'
            );
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#certificates_tab">{{ lang._('Host Certificates') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="certificates_tab" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridCertificate + {
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
    'fields': formDialogCertificate,
    'id':     formGridCertificate['edit_dialog_id'],
    'label':  lang._('Edit Certificate')
]) }}

{# ============================================================================
   Custom-action dialogs (sign / import — manual POST via ajaxCall)
============================================================================ #}
{{ partial("layout_partials/base_dialog", [
    'fields': formCertificateSign,
    'id':     'DialogCertificateSign',
    'label':  lang._('Sign Host Certificate')
]) }}

{# Inject the CA-networks hint paragraph into the Sign dialog after it is rendered.
   base_dialog renders a <div id="DialogCertificateSign"> containing a .modal-body > form.
   We append a small help paragraph immediately after the caref field row via JS in
   document.ready (see nebula_update_ca_networks_hint above), and pre-place the <p>
   element here so the JS can find it by id. The element is hidden until the dialog
   is opened; the JS shows and updates it when the CA selection changes. #}
<script>
    $(document).ready(function () {
        // Insert the CA-networks hint paragraph into the Sign dialog's modal-body,
        // after the last field in the form (a natural place for contextual info).
        // We use append rather than a fixed field row so it does not interfere with
        // the base_dialog form layout or validation.
        let $hint = $('<p id="nebula_ca_networks_hint" class="text-muted" style="margin:8px 15px 0;font-size:12px;"></p>');
        $("#DialogCertificateSign .modal-body form").append($hint);
    });
</script>

{{ partial("layout_partials/base_dialog", [
    'fields': formCertificateImport,
    'id':     'DialogCertificateImport',
    'label':  lang._('Import Host Certificate')
]) }}
