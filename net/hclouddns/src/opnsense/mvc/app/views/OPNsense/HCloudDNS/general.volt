{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.
#}

<style>
    .status-overview {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .status-item {
        display: inline-block;
        margin-right: 30px;
        padding: 10px 0;
    }
    .status-item .label {
        font-size: 14px;
        padding: 6px 12px;
    }
    .status-item strong {
        display: block;
        margin-bottom: 5px;
        color: #666;
        font-size: 12px;
        text-transform: uppercase;
    }
    .token-actions {
        margin-top: 5px;
    }
    .token-status {
        margin-left: 10px;
    }
    .config-summary {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .summary-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .summary-card {
        flex: 1;
        min-width: 200px;
        background: #fafafa;
        border: 1px solid #eee;
        border-radius: 4px;
        padding: 15px;
    }
    .summary-card h5 {
        margin: 0 0 10px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }
    .summary-card .count {
        font-size: 24px;
        font-weight: bold;
        color: #337ab7;
    }
    .summary-card ul {
        margin: 0;
        padding-left: 20px;
    }
    .form-section {
        margin-bottom: 25px;
    }
    .form-section h4 {
        border-bottom: 2px solid #337ab7;
        padding-bottom: 8px;
        margin-bottom: 15px;
    }
</style>

<script>
    $(document).ready(function() {
        // Load form data
        var data_get_map = {'frm_general_settings': '/api/hclouddns/settings/get'};
        mapDataToFormUI(data_get_map).done(function(data) {
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            loadStatusOverview();
        });

        // Load status overview
        function loadStatusOverview() {
            ajaxCall('/api/hclouddns/settings/get', {}, function(data, status) {
                if (data && data.hclouddns) {
                    var cfg = data.hclouddns;
                    var general = cfg.general || {};

                    // Service status
                    if (general.enabled === '1') {
                        $('#svcStatus').html('<span class="label label-success">Aktiviert</span>');
                    } else {
                        $('#svcStatus').html('<span class="label label-default">Deaktiviert</span>');
                    }

                    // API Type
                    var apiType = 'Cloud API';
                    if (general.apiType) {
                        for (var key in general.apiType) {
                            if (general.apiType[key].selected === 1) {
                                apiType = key === 'dns' ? 'Legacy API' : 'Cloud API';
                                break;
                            }
                        }
                    }
                    $('#apiTypeStatus').html('<span class="label label-info">' + apiType + '</span>');

                    // Token status
                    var hasToken = general.apiToken && general.apiToken.length > 0;
                    if (hasToken) {
                        $('#tokenStatus').html('<span class="label label-success">Konfiguriert</span>');
                    } else {
                        $('#tokenStatus').html('<span class="label label-warning">Nicht gesetzt</span>');
                    }

                    // Failover status
                    if (general.failoverEnabled === '1') {
                        $('#failoverStatus').html('<span class="label label-info">Aktiviert</span>');
                    } else {
                        $('#failoverStatus').html('<span class="label label-default">Deaktiviert</span>');
                    }

                    // Count gateways
                    var gwCount = 0;
                    var gwList = [];
                    if (cfg.gateways && cfg.gateways.gateway) {
                        for (var uuid in cfg.gateways.gateway) {
                            gwCount++;
                            var gw = cfg.gateways.gateway[uuid];
                            gwList.push(gw.name + (gw.enabled === '1' ? '' : ' (deaktiviert)'));
                        }
                    }
                    $('#gatewayCount').text(gwCount);
                    if (gwList.length > 0) {
                        $('#gatewayList').html('<ul><li>' + gwList.join('</li><li>') + '</li></ul>');
                    } else {
                        $('#gatewayList').html('<em class="text-muted">Keine konfiguriert</em>');
                    }

                    // Count entries
                    var entryCount = 0;
                    var zoneSet = {};
                    if (cfg.entries && cfg.entries.entry) {
                        for (var uuid in cfg.entries.entry) {
                            entryCount++;
                            var entry = cfg.entries.entry[uuid];
                            zoneSet[entry.zoneName] = true;
                        }
                    }
                    $('#entryCount').text(entryCount);
                    var zones = Object.keys(zoneSet);
                    if (zones.length > 0) {
                        $('#zoneList').html('<ul><li>' + zones.join('</li><li>') + '</li></ul>');
                    } else {
                        $('#zoneList').html('<em class="text-muted">Keine konfiguriert</em>');
                    }
                }
            });
        }

        // Validate token button
        $('#validateTokenBtn').click(function() {
            var token = $('input[id="general\\.apiToken"]').val();
            if (!token) {
                $('#tokenValidation').html('<span class="label label-warning">Bitte Token eingeben</span>');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $('#tokenValidation').html('<i class="fa fa-spinner fa-spin"></i> Prüfe...');

            ajaxCall('/api/hclouddns/hetzner/listZones', {token: token}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Prüfen');

                if (data && data.status === 'ok' && data.zones) {
                    $('#tokenValidation').html(
                        '<span class="label label-success">Gültig</span> ' +
                        '<small>' + data.zones.length + ' Zone(n) gefunden</small>'
                    );
                } else {
                    $('#tokenValidation').html(
                        '<span class="label label-danger">Ungültig</span> ' +
                        '<small>' + (data && data.message ? data.message : 'Token nicht erkannt') + '</small>'
                    );
                }
            });
        });

        // Toggle token visibility
        $('#toggleTokenBtn').click(function() {
            var $input = $('input[id="general\\.apiToken"]');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $(this).html('<i class="fa fa-eye-slash"></i>');
            } else {
                $input.attr('type', 'password');
                $(this).html('<i class="fa fa-eye"></i>');
            }
        });

        // Save settings
        $("#saveAct").click(function() {
            saveFormToEndpoint('/api/hclouddns/settings/set', 'frm_general_settings', function() {
                ajaxCall('/api/hclouddns/service/reconfigure', {}, function(data, status) {
                    loadStatusOverview();
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_SUCCESS,
                        title: "{{ lang._('Saved') }}",
                        message: "{{ lang._('Settings have been saved and applied.') }}",
                        buttons: [{
                            label: "{{ lang._('OK') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                });
            }, true);
        });

        // Update button
        $("#updateAct").click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Updating...") }}');

            ajaxCall('/api/hclouddns/service/updateV2', {}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> {{ lang._("Update Now") }}');

                if (data && data.status === 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_SUCCESS,
                        title: "{{ lang._('Update Complete') }}",
                        message: "{{ lang._('DNS records have been updated.') }}",
                        buttons: [{
                            label: "{{ lang._('Close') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                } else {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Update') }}",
                        message: data && data.message ? data.message : "{{ lang._('Update completed with warnings.') }}",
                        buttons: [{
                            label: "{{ lang._('Close') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                }
            });
        });
    });
</script>

<div class="tab-content content-box">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box-main">
            <!-- Status Overview -->
            <div class="col-md-12">
                <h2><i class="fa fa-cloud"></i> {{ lang._('Hetzner Cloud Dynamic DNS') }}</h2>
            </div>

            <div class="col-md-12">
                <div class="status-overview">
                    <div class="status-item">
                        <strong>{{ lang._('Service') }}</strong>
                        <span id="svcStatus"><i class="fa fa-spinner fa-spin"></i></span>
                    </div>
                    <div class="status-item">
                        <strong>{{ lang._('API') }}</strong>
                        <span id="apiTypeStatus"><i class="fa fa-spinner fa-spin"></i></span>
                    </div>
                    <div class="status-item">
                        <strong>{{ lang._('Token') }}</strong>
                        <span id="tokenStatus"><i class="fa fa-spinner fa-spin"></i></span>
                    </div>
                    <div class="status-item">
                        <strong>{{ lang._('Failover') }}</strong>
                        <span id="failoverStatus"><i class="fa fa-spinner fa-spin"></i></span>
                    </div>
                </div>
            </div>

            <!-- Configuration Summary -->
            <div class="col-md-12">
                <div class="config-summary">
                    <h4>{{ lang._('Configuration Summary') }}</h4>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <h5><i class="fa fa-server"></i> {{ lang._('Gateways') }}</h5>
                            <div class="count" id="gatewayCount">-</div>
                            <div id="gatewayList"><i class="fa fa-spinner fa-spin"></i></div>
                            <a href="/ui/hclouddns/gateways" class="btn btn-xs btn-default" style="margin-top:10px;">
                                <i class="fa fa-cog"></i> {{ lang._('Manage') }}
                            </a>
                        </div>
                        <div class="summary-card">
                            <h5><i class="fa fa-list"></i> {{ lang._('DNS Entries') }}</h5>
                            <div class="count" id="entryCount">-</div>
                            <div id="zoneList"><i class="fa fa-spinner fa-spin"></i></div>
                            <a href="/ui/hclouddns/entries" class="btn btn-xs btn-default" style="margin-top:10px;">
                                <i class="fa fa-cog"></i> {{ lang._('Manage') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="col-md-12">
                <div class="form-section">
                    <h4><i class="fa fa-cogs"></i> {{ lang._('Settings') }}</h4>
                    {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_general_settings']) }}
                </div>
            </div>

            <!-- Token Actions (injected after form loads) -->
            <div class="col-md-12" id="tokenActionsContainer" style="display:none;">
                <div class="token-actions" style="margin-top:-15px; margin-bottom:20px;">
                    <button type="button" class="btn btn-xs btn-default" id="toggleTokenBtn" title="{{ lang._('Show/Hide Token') }}">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-info" id="validateTokenBtn">
                        <i class="fa fa-check-circle"></i> {{ lang._('Validate') }}
                    </button>
                    <span id="tokenValidation" class="token-status"></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="col-md-12">
                <hr/>
                <button class="btn btn-primary" id="saveAct" type="button">
                    <i class="fa fa-save"></i> <b>{{ lang._('Save') }}</b>
                    <i id="saveAct_progress" class=""></i>
                </button>
                <button class="btn btn-default" id="updateAct" type="button">
                    <i class="fa fa-refresh"></i> <b>{{ lang._('Update Now') }}</b>
                    <i id="updateAct_progress" class=""></i>
                </button>
                <a href="/ui/hclouddns/status" class="btn btn-default">
                    <i class="fa fa-dashboard"></i> {{ lang._('Status Dashboard') }}
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Move token actions after the token field once form is loaded
    $(document).ready(function() {
        setTimeout(function() {
            var $tokenRow = $('input[id="general\\.apiToken"]').closest('tr');
            if ($tokenRow.length) {
                $('#tokenActionsContainer').show().insertAfter($tokenRow.closest('table'));
            }
        }, 500);
    });
</script>
