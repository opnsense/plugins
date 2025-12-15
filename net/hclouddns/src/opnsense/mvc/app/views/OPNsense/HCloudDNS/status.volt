{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.
#}

<style>
    .status-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
        background: #fff;
    }
    .status-card h4 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .gateway-item {
        padding: 8px 12px;
        margin: 5px 0;
        border-radius: 4px;
        background: #f8f8f8;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .gateway-item.up { border-left: 4px solid #5cb85c; }
    .gateway-item.down { border-left: 4px solid #d9534f; }
    .gateway-item.simulated { border-left: 4px solid #f0ad4e; }
    .simulation-controls {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .simulation-controls.active {
        background: #f8d7da;
        border-color: #f5c6cb;
    }
</style>

<script>
    $(document).ready(function() {
        var gatewaysCache = {};
        var simulationActive = false;

        // Load service settings
        function loadServiceStatus() {
            ajaxCall('/api/hclouddns/settings/get', {}, function(data, status) {
                if (data && data.hclouddns && data.hclouddns.general) {
                    var general = data.hclouddns.general;
                    if (general.enabled === '1') {
                        $('#serviceStatus').html('<span class="label label-success">{{ lang._("Enabled") }}</span>');
                    } else {
                        $('#serviceStatus').html('<span class="label label-default">{{ lang._("Disabled") }}</span>');
                    }
                    if (general.failoverEnabled === '1') {
                        $('#failoverStatus').html('<span class="label label-info">{{ lang._("Enabled") }}</span>');
                    } else {
                        $('#failoverStatus').html('<span class="label label-default">{{ lang._("Disabled") }}</span>');
                    }
                }
            });
        }

        // Load gateways
        function loadGateways() {
            ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(data, status) {
                if (data && data.rows) {
                    gatewaysCache = {};
                    var $container = $('#gatewaysContainer');
                    $container.empty();

                    if (data.rows.length === 0) {
                        $container.html('<p class="text-muted">{{ lang._("No gateways configured") }}</p>');
                        return;
                    }

                    $.each(data.rows, function(i, gw) {
                        gatewaysCache[gw.uuid] = gw.name;
                        var enabledBadge = gw.enabled === '1' ?
                            '<span class="label label-success">{{ lang._("On") }}</span>' :
                            '<span class="label label-default">{{ lang._("Off") }}</span>';

                        var html = '<div class="gateway-item" id="gw-' + gw.uuid + '" data-uuid="' + gw.uuid + '">' +
                            '<div>' +
                            '<strong>' + gw.name + '</strong> ' + enabledBadge +
                            '<br><small class="text-muted">' + gw.interface + ' (Priority: ' + gw.priority + ')</small>' +
                            '</div>' +
                            '<div>' +
                            '<span class="gw-status"><i class="fa fa-spinner fa-spin"></i></span>' +
                            '<button class="btn btn-xs btn-default check-health-btn" data-uuid="' + gw.uuid + '" style="margin-left: 10px;">' +
                            '<i class="fa fa-heartbeat"></i>' +
                            '</button>' +
                            '</div>' +
                            '</div>';
                        $container.append(html);
                    });

                    // Load gateway status
                    loadGatewayStatus();
                }
            });
        }

        // Load gateway status
        function loadGatewayStatus() {
            ajaxCall('/api/hclouddns/gateways/status', {}, function(data, status) {
                if (data && data.gateways) {
                    $.each(data.gateways, function(uuid, info) {
                        var $item = $('#gw-' + uuid);
                        var statusHtml = '';

                        if (info.simulated) {
                            $item.removeClass('up down').addClass('simulated');
                            statusHtml = '<span class="label label-warning">{{ lang._("Simulated Down") }}</span>';
                        } else if (info.status === 'up') {
                            $item.removeClass('down simulated').addClass('up');
                            statusHtml = '<span class="label label-success">{{ lang._("Up") }}</span>';
                            if (info.ipv4) statusHtml += ' <small>' + info.ipv4 + '</small>';
                        } else {
                            $item.removeClass('up simulated').addClass('down');
                            statusHtml = '<span class="label label-danger">{{ lang._("Down") }}</span>';
                        }

                        $item.find('.gw-status').html(statusHtml);
                    });
                }
            });
        }

        // Load entries
        function loadEntries() {
            ajaxCall('/api/hclouddns/entries/searchItem', {}, function(data, status) {
                var $tbody = $('#entriesTable tbody');
                $tbody.empty();

                if (data && data.rows && data.rows.length > 0) {
                    $.each(data.rows, function(i, entry) {
                        var statusBadge = '';
                        switch(entry.status) {
                            case 'active':
                                statusBadge = '<span class="label label-success">{{ lang._("Active") }}</span>';
                                break;
                            case 'failover':
                                statusBadge = '<span class="label label-warning">{{ lang._("Failover") }}</span>';
                                break;
                            case 'paused':
                                statusBadge = '<span class="label label-default">{{ lang._("Paused") }}</span>';
                                break;
                            case 'error':
                                statusBadge = '<span class="label label-danger">{{ lang._("Error") }}</span>';
                                break;
                            default:
                                statusBadge = '<span class="label label-info">{{ lang._("Pending") }}</span>';
                        }

                        var gwName = gatewaysCache[entry.primaryGateway] || entry.primaryGateway || '-';
                        var failoverName = entry.failoverGateway ? (gatewaysCache[entry.failoverGateway] || entry.failoverGateway) : '-';

                        var row = '<tr>' +
                            '<td>' + (entry.enabled === '1' ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-muted"></i>') + '</td>' +
                            '<td><code>' + entry.recordName + '.' + entry.zoneName + '</code></td>' +
                            '<td><span class="label label-' + (entry.recordType === 'A' ? 'primary' : 'info') + '">' + entry.recordType + '</span></td>' +
                            '<td>' + (entry.currentIp || '-') + '</td>' +
                            '<td>' + gwName + '</td>' +
                            '<td>' + failoverName + '</td>' +
                            '<td>' + statusBadge + '</td>' +
                            '</tr>';
                        $tbody.append(row);
                    });
                } else {
                    $tbody.html('<tr><td colspan="7" class="text-center text-muted">{{ lang._("No entries configured") }}</td></tr>');
                }
            });
        }

        // Load simulation status
        function loadSimulationStatus() {
            ajaxCall('/api/hclouddns/service/simulateStatus', {}, function(data, status) {
                if (data && data.simulation) {
                    simulationActive = data.simulation.active;
                    var $container = $('#simulationContainer');

                    if (simulationActive) {
                        $container.addClass('active');
                        var downList = data.simulation.simulatedDown || [];
                        var downNames = downList.map(function(uuid) {
                            return gatewaysCache[uuid] || uuid;
                        });
                        $('#simulationStatus').html(
                            '<span class="label label-danger">{{ lang._("Active") }}</span> ' +
                            '{{ lang._("Simulated down:") }} ' + (downNames.join(', ') || 'None')
                        );
                        $('#clearSimBtn').show();
                    } else {
                        $container.removeClass('active');
                        $('#simulationStatus').html('<span class="label label-default">{{ lang._("Inactive") }}</span>');
                        $('#clearSimBtn').hide();
                    }

                    // Update simulate buttons
                    updateSimulateButtons(data.simulation.simulatedDown || []);
                }
            });
        }

        // Update simulate buttons in gateway list
        function updateSimulateButtons(simulatedDown) {
            $('.gateway-item').each(function() {
                var uuid = $(this).data('uuid');
                var $btnGroup = $(this).find('.sim-btn-group');

                if ($btnGroup.length === 0) {
                    var btns = '<span class="sim-btn-group" style="margin-left: 5px;">' +
                        '<button class="btn btn-xs btn-warning sim-down-btn" data-uuid="' + uuid + '" title="{{ lang._("Simulate Down") }}">' +
                        '<i class="fa fa-power-off"></i></button> ' +
                        '<button class="btn btn-xs btn-success sim-up-btn" data-uuid="' + uuid + '" title="{{ lang._("Simulate Up") }}" style="display:none;">' +
                        '<i class="fa fa-plug"></i></button>' +
                        '</span>';
                    $(this).find('.check-health-btn').after(btns);
                    $btnGroup = $(this).find('.sim-btn-group');
                }

                if (simulatedDown.indexOf(uuid) >= 0) {
                    $btnGroup.find('.sim-down-btn').hide();
                    $btnGroup.find('.sim-up-btn').show();
                } else {
                    $btnGroup.find('.sim-down-btn').show();
                    $btnGroup.find('.sim-up-btn').hide();
                }
            });
        }

        // Health check button
        $(document).on('click', '.check-health-btn', function() {
            var uuid = $(this).data('uuid');
            var $btn = $(this);
            $btn.prop('disabled', true);

            ajaxCall('/api/hclouddns/gateways/checkHealth/' + uuid, {}, function(data, status) {
                $btn.prop('disabled', false);
                loadGatewayStatus();
            });
        });

        // Simulate down button
        $(document).on('click', '.sim-down-btn', function() {
            var uuid = $(this).data('uuid');
            ajaxCall('/api/hclouddns/service/simulateDown/' + uuid, {}, function(data, status) {
                loadSimulationStatus();
                loadGatewayStatus();
            });
        });

        // Simulate up button
        $(document).on('click', '.sim-up-btn', function() {
            var uuid = $(this).data('uuid');
            ajaxCall('/api/hclouddns/service/simulateUp/' + uuid, {}, function(data, status) {
                loadSimulationStatus();
                loadGatewayStatus();
            });
        });

        // Clear simulation button
        $('#clearSimBtn').click(function() {
            ajaxCall('/api/hclouddns/service/simulateClear', {}, function(data, status) {
                loadSimulationStatus();
                loadGatewayStatus();
            });
        });

        // Force update button
        $('#forceUpdateBtn').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Updating...") }}');

            ajaxCall('/api/hclouddns/service/updateV2', {}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> {{ lang._("Force Update") }}');

                if (data && data.status === 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_SUCCESS,
                        title: "{{ lang._('Update Complete') }}",
                        message: "{{ lang._('DNS records have been updated.') }}" +
                            (data.updated ? '<br>{{ lang._("Updated:") }} ' + data.updated : ''),
                        buttons: [{
                            label: "{{ lang._('Close') }}",
                            action: function(dialog) { dialog.close(); }
                        }]
                    });
                }

                loadEntries();
                loadGatewayStatus();
            });
        });

        // Refresh button
        $('#refreshBtn').click(function() {
            loadAll();
        });

        // Load all data
        function loadAll() {
            loadServiceStatus();
            loadGateways();
            setTimeout(function() {
                loadEntries();
                loadSimulationStatus();
            }, 300);
        }

        // Initial load
        loadAll();

        // Auto-refresh every 30 seconds
        setInterval(loadAll, 30000);
    });
</script>

<div class="tab-content content-box">
    <div id="status" class="tab-pane fade in active">
        <div class="content-box-main">
            <div class="row">
                <div class="col-md-12">
                    <h2>{{ lang._('Hetzner Cloud DDNS Status') }}</h2>
                </div>
            </div>

            <div class="row" style="margin-bottom: 20px;">
                <div class="col-md-12">
                    <strong>{{ lang._('Service') }}:</strong> <span id="serviceStatus"><i class="fa fa-spinner fa-spin"></i></span>
                    &nbsp;&nbsp;
                    <strong>{{ lang._('Failover') }}:</strong> <span id="failoverStatus"><i class="fa fa-spinner fa-spin"></i></span>
                    <button class="btn btn-default btn-xs pull-right" id="refreshBtn" style="margin-left: 10px;">
                        <i class="fa fa-refresh"></i> {{ lang._('Refresh') }}
                    </button>
                    <button class="btn btn-primary btn-xs pull-right" id="forceUpdateBtn">
                        <i class="fa fa-refresh"></i> {{ lang._('Force Update') }}
                    </button>
                </div>
            </div>

            <div class="row">
                <!-- Gateways -->
                <div class="col-md-4">
                    <div class="status-card">
                        <h4><i class="fa fa-server"></i> {{ lang._('Gateways') }}</h4>
                        <div id="gatewaysContainer">
                            <p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>
                        </div>
                    </div>

                    <!-- Simulation Controls -->
                    <div class="simulation-controls" id="simulationContainer">
                        <h5><i class="fa fa-flask"></i> {{ lang._('Failover Simulation') }}</h5>
                        <p class="small text-muted">{{ lang._('Test failover by simulating gateway failures.') }}</p>
                        <div>
                            {{ lang._('Status') }}: <span id="simulationStatus"><i class="fa fa-spinner fa-spin"></i></span>
                        </div>
                        <button class="btn btn-sm btn-danger" id="clearSimBtn" style="margin-top: 10px; display: none;">
                            <i class="fa fa-times"></i> {{ lang._('Clear Simulation') }}
                        </button>
                    </div>
                </div>

                <!-- Entries -->
                <div class="col-md-8">
                    <div class="status-card">
                        <h4><i class="fa fa-list"></i> {{ lang._('DNS Entries') }}</h4>
                        <table id="entriesTable" class="table table-condensed table-hover table-striped">
                            <thead>
                                <tr>
                                    <th style="width:30px;"></th>
                                    <th>{{ lang._('Record') }}</th>
                                    <th>{{ lang._('Type') }}</th>
                                    <th>{{ lang._('Current IP') }}</th>
                                    <th>{{ lang._('Primary') }}</th>
                                    <th>{{ lang._('Failover') }}</th>
                                    <th>{{ lang._('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center"><i class="fa fa-spinner fa-spin"></i> {{ lang._('Loading...') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
