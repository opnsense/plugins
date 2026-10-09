{#
 # Copyright (C) 2025 OPNsense Community
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
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 #}

<script>
    $(document).ready(function() {
        const data_get_map = {'frm_general_settings': "/api/xproxy/settings/get"};
        var gridId = "#{{formGridServer['table_id']}}";
        var generalDirty = true;

        var excludeInterfaces = ['wan', 'lo0', 'xproxytun'];
        var tunFields = [
            'route_interfaces', 'tun_device', 'tun_address', 'tun_gateway', 'bypass_ips'
        ];

        function filterTunnelInterfaces() {
            var sel = $('#xproxy\\.general\\.route_interfaces');
            sel.find('option').each(function() {
                var val = $(this).val();
                if (excludeInterfaces.indexOf(val) !== -1 || val.match(/^tun\d/)) {
                    $(this).remove();
                }
            });
            sel.attr('title', 'All interfaces (default)');
            sel.selectpicker('refresh');
        }

        function toggleTunFields() {
            var checked = $('#xproxy\\.general\\.policy_route_lan').is(':checked');
            $.each(tunFields, function(_, fld) {
                var row = $('#xproxy\\.general\\.' + fld).closest('tr');
                if (checked) {
                    row.show();
                } else {
                    row.hide();
                }
            });
        }

        function refreshGeneralForm() {
            generalDirty = false;
            return mapDataToFormUI(data_get_map).done(function() {
                formatTokenizersUI();
                filterTunnelInterfaces();
                $('.selectpicker').selectpicker('refresh');
                toggleTunFields();
                $('#xproxy\\.general\\.policy_route_lan').off('change.tun').on('change.tun', toggleTunFields);
            });
        }

        function markGeneralDirty() {
            generalDirty = true;
        }

        refreshGeneralForm();

        $(gridId).UIBootgrid({
            search: '/api/xproxy/servers/search_item',
            get: '/api/xproxy/servers/get_item/',
            set: '/api/xproxy/servers/set_item/',
            add: '/api/xproxy/servers/add_item/',
            del: '/api/xproxy/servers/del_item/',
        });

        $(gridId).on('loaded.rs.jquery.bootgrid', function() {
            markGeneralDirty();
        });

        function updateServerDialogFields() {
            var dlg = $('#' + "{{formGridServer['edit_dialog_id']}}");
            var proto = dlg.find('#server\\.protocol').val();
            var security = dlg.find('#server\\.security').val();
            var transport = dlg.find('#server\\.transport').val();

            var showUuid = (proto === 'vless' || proto === 'vmess');
            var showPassword = (proto === 'shadowsocks' || proto === 'trojan');
            var showFlow = (proto === 'vless');
            var showEncryption = (proto === 'vless' || proto === 'vmess' || proto === 'shadowsocks');
            var showReality = (security === 'reality');
            var showTlsFields = (security === 'tls' || security === 'reality');
            var showTransportDetail = (transport === 'ws' || transport === 'h2' || transport === 'grpc' || transport === 'httpupgrade');

            dlg.find('#server\\.user_id').closest('.form-group').toggle(showUuid);
            dlg.find('#server\\.password').closest('.form-group').toggle(showPassword);
            dlg.find('#server\\.flow').closest('.form-group').toggle(showFlow);
            dlg.find('#server\\.encryption').closest('.form-group').toggle(showEncryption);
            dlg.find('#server\\.reality_pubkey').closest('.form-group').toggle(showReality);
            dlg.find('#server\\.reality_short_id').closest('.form-group').toggle(showReality);
            dlg.find('#server\\.sni').closest('.form-group').toggle(showTlsFields);
            dlg.find('#server\\.fingerprint').closest('.form-group').toggle(showTlsFields);
            dlg.find('#server\\.alpn').closest('.form-group').toggle(showTlsFields && !showReality);
            dlg.find('#server\\.transport_host').closest('.form-group').toggle(showTransportDetail);
            dlg.find('#server\\.transport_path').closest('.form-group').toggle(showTransportDetail);
        }

        $(document).on('change', '#server\\.protocol, #server\\.security, #server\\.transport', updateServerDialogFields);
        $(document).on('shown.bs.modal', '#' + "{{formGridServer['edit_dialog_id']}}", function() {
            setTimeout(updateServerDialogFields, 50);
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/xproxy/settings/set", 'frm_general_settings', function() {
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        updateServiceControlUI('xproxy');

        // Import tab
        var importRunning = false;
        $("#importAct").click(function() {
            if (importRunning) {
                return;
            }
            var uris = $("#import_uris_text").val();
            if (!uris || uris.trim() === '') {
                BootstrapDialog.alert('{{ lang._("Please paste at least one proxy URI.") }}');
                return;
            }
            importRunning = true;
            $("#importAct").prop('disabled', true);
            $("#importAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall('/api/xproxy/import/uris', {uris: uris}, function(data, status) {
                $("#importAct_progress").removeClass("fa fa-spinner fa-pulse");
                $("#importAct").prop('disabled', false);
                importRunning = false;
                if (status !== 'success' || data === undefined || data === null) {
                    BootstrapDialog.alert('{{ lang._("Import request failed (network or server error).") }}');
                    return;
                }
                if (data.result === 'saved') {
                    var msg = '{{ lang._("Imported") }} ' + data.count + ' {{ lang._("server(s).") }}';
                    if (data.skipped) {
                        msg += ' (' + data.skipped + ' {{ lang._("duplicate(s) skipped") }})';
                    }
                    if (data.auto_selected) {
                        msg += '<br/>{{ lang._("Auto-selected:") }} <b>' + data.auto_selected + '</b>';
                    }
                    if (data.errors && data.errors.length > 0) {
                        msg += '<br/><br/><small class="text-warning">{{ lang._("Parse errors:") }}<br/>';
                        for (var i = 0; i < data.errors.length && i < 10; i++) {
                            msg += '&bull; ' + $('<span/>').text(data.errors[i]).html() + '<br/>';
                        }
                        if (data.errors.length > 10) {
                            msg += '&hellip; ' + (data.errors.length - 10) + ' {{ lang._("more") }}';
                        }
                        msg += '</small>';
                    }
                    BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: msg});
                    $("#import_uris_text").val('');
                    $(gridId).bootgrid('reload');
                    markGeneralDirty();
                } else {
                    var errMsg = data.message || 'unknown error';
                    if (data.errors && data.errors.length > 0) {
                        errMsg += '<br/><br/><small>';
                        for (var j = 0; j < data.errors.length && j < 10; j++) {
                            errMsg += '&bull; ' + $('<span/>').text(data.errors[j]).html() + '<br/>';
                        }
                        errMsg += '</small>';
                    }
                    BootstrapDialog.alert('{{ lang._("Import failed: ") }}' + errMsg);
                }
            });
        });

        // Log tab
        var logTimer = null;
        var allowedHashes = ['#servers', '#general', '#import', '#log'];
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            var tab = $(e.target).attr('href');
            if (tab === '#log') {
                refreshLog();
                if (logTimer) {
                    clearInterval(logTimer);
                }
                logTimer = setInterval(refreshLog, 5000);
            } else {
                if (logTimer) {
                    clearInterval(logTimer);
                    logTimer = null;
                }
            }
            if (tab === '#servers') {
                $(gridId).bootgrid('reload');
            }
            if (tab === '#general' && generalDirty) {
                refreshGeneralForm();
            }
            if (tab === '#servers' || tab === '#import' || tab === '#log') {
                $('#reconfigureAct').closest('.content-box').hide();
            } else {
                $('#reconfigureAct').closest('.content-box').show();
            }
        });

        function refreshLog() {
            ajaxGet('/api/xproxy/service/log', {}, function(data, status) {
                if (status !== 'success') {
                    return;
                }
                if (data && data.response) {
                    var el = document.getElementById('xproxy_log_output');
                    var atBottom = el && (el.scrollHeight - el.scrollTop - el.clientHeight < 30);
                    $("#xproxy_log_output").text(data.response);
                    if (el && atBottom) {
                        el.scrollTop = el.scrollHeight;
                    }
                }
            });
        }

        $(window).on('beforeunload', function() {
            if (logTimer) {
                clearInterval(logTimer);
            }
        });

        var h = window.location.hash;
        if (h && allowedHashes.indexOf(h) !== -1) {
            $('a[href="' + h + '"]').trigger('click');
        }
        if (!h || h !== '#general') {
            $('#reconfigureAct').closest('.content-box').hide();
        }
        $('.nav-tabs a').on('shown.bs.tab', function(e) {
            history.pushState(null, null, e.target.hash);
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="tab_servers" href="#servers">{{ lang._('Servers') }}</a></li>
    <li><a data-toggle="tab" id="tab_general" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" id="tab_import" href="#import">{{ lang._('Import') }}</a></li>
    <li><a data-toggle="tab" id="tab_log" href="#log">{{ lang._('Log') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="servers" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridServer)}}
    </div>
    <div id="general" class="tab-pane fade in">
        {{ partial("layout_partials/base_form",['fields':formGeneral,'id':'frm_general_settings'])}}
    </div>
    <div id="import" class="tab-pane fade in">
        <div class="col-md-12" style="padding-top: 15px;">
            <div class="form-group">
                <label for="import_uris_text">{{ lang._('Proxy URIs') }}</label>
                <textarea class="form-control" id="import_uris_text" rows="8" style="resize: vertical;"
                          placeholder="{{ lang._('Paste proxy URIs here, one per line (vless://, vmess://, ss://, trojan://)') }}"></textarea>
            </div>
            <button class="btn btn-primary" id="importAct" type="button" style="margin-bottom: 15px;">
                <b>{{ lang._('Import') }}</b> <i id="importAct_progress"></i>
            </button>
        </div>
    </div>
    <div id="log" class="tab-pane fade in">
        <div class="col-md-12" style="padding-top: 15px;">
            <pre id="xproxy_log_output" style="max-height: 500px; overflow-y: auto; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 10px;">{{ lang._('Loading...') }}</pre>
        </div>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/xproxy/service/reconfigure', 'data_service_widget': 'xproxy'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogServer,'id':formGridServer['edit_dialog_id'],'label':lang._('Edit Server')])}}
