{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.
#}

<style>
    .zone-accordion .panel-heading {
        cursor: pointer;
        padding: 10px 15px;
    }
    .zone-accordion .panel-heading:hover {
        background-color: #f5f5f5;
    }
    .zone-accordion .record-count {
        float: right;
        color: #666;
    }
    .zone-accordion .zone-icon {
        margin-right: 8px;
    }
    .record-table {
        margin-bottom: 0;
    }
    .record-table th,
    .record-table td {
        padding: 6px 10px !important;
    }
    .record-checkbox {
        width: 20px;
    }
    .selected-records-info {
        background-color: #f0f8ff;
        border: 1px solid #b8daff;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 15px;
    }
    .gateway-select-row {
        background-color: #fafafa;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
</style>

<script>
    $(document).ready(function() {
        var currentToken = '';
        var zonesData = [];
        var selectedRecords = [];

        // Load settings to get API token
        function loadApiToken() {
            ajaxCall('/api/hclouddns/settings/get', {}, function(data, status) {
                if (data && data.hclouddns && data.hclouddns.general && data.hclouddns.general.apiToken) {
                    currentToken = data.hclouddns.general.apiToken;
                    $('#apiTokenInput').val(currentToken);
                    $('#apiTokenStatus').html('<span class="label label-success">{{ lang._("Token configured") }}</span>');
                }
            });
        }

        loadApiToken();

        // Validate and load zones
        $('#loadZonesBtn').click(function() {
            var token = $('#apiTokenInput').val();
            if (!token) {
                BootstrapDialog.alert({
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('Please enter an API token.') }}",
                    type: BootstrapDialog.TYPE_WARNING
                });
                return;
            }

            currentToken = token;
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Loading...") }}');

            ajaxCall('/api/hclouddns/hetzner/listZones', {token: token}, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-cloud-download"></i> {{ lang._("Load Zones") }}');

                if (data && data.status === 'ok' && data.zones) {
                    zonesData = data.zones;
                    renderZoneAccordion(data.zones);
                    $('#apiTokenStatus').html('<span class="label label-success">{{ lang._("Valid") }} - ' + data.zones.length + ' {{ lang._("Zones") }}</span>');
                } else {
                    $('#apiTokenStatus').html('<span class="label label-danger">{{ lang._("Invalid") }}</span>');
                    BootstrapDialog.alert({
                        title: "{{ lang._('Error') }}",
                        message: data && data.message ? data.message : "{{ lang._('Failed to load zones.') }}",
                        type: BootstrapDialog.TYPE_DANGER
                    });
                }
            });
        });

        // Save API token to settings
        $('#saveTokenBtn').click(function() {
            var token = $('#apiTokenInput').val();
            ajaxCall('/api/hclouddns/settings/set', {hclouddns: {general: {apiToken: token}}}, function(data, status) {
                if (data && data.status === 'ok') {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Success') }}",
                        message: "{{ lang._('API token saved.') }}",
                        type: BootstrapDialog.TYPE_SUCCESS
                    });
                }
            });
        });

        function renderZoneAccordion(zones) {
            var $container = $('#zoneAccordion');
            $container.empty();

            if (zones.length === 0) {
                $container.html('<div class="alert alert-info">{{ lang._("No zones found.") }}</div>');
                return;
            }

            $.each(zones, function(i, zone) {
                var panelId = 'zone-' + zone.id;
                var panelHtml = '<div class="panel panel-default">' +
                    '<div class="panel-heading" data-toggle="collapse" data-target="#' + panelId + '" data-zone-id="' + zone.id + '" data-zone-name="' + zone.name + '">' +
                    '<span class="zone-icon fa fa-chevron-right"></span>' +
                    '<strong>' + zone.name + '</strong>' +
                    '<span class="record-count">' + zone.records_count + ' {{ lang._("Records") }}</span>' +
                    '</div>' +
                    '<div id="' + panelId + '" class="panel-collapse collapse">' +
                    '<div class="panel-body">' +
                    '<div class="text-center"><i class="fa fa-spinner fa-spin"></i> {{ lang._("Loading records...") }}</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
                $container.append(panelHtml);
            });

            // Handle accordion expand
            $container.find('.panel-heading').on('click', function() {
                var $icon = $(this).find('.zone-icon');
                var $collapse = $(this).next('.panel-collapse');
                var zoneId = $(this).data('zone-id');
                var zoneName = $(this).data('zone-name');

                if ($collapse.hasClass('in')) {
                    $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                } else {
                    $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');

                    // Load records if not already loaded
                    if ($collapse.find('.record-table').length === 0) {
                        loadZoneRecords(zoneId, zoneName, $collapse.find('.panel-body'));
                    }
                }
            });
        }

        function loadZoneRecords(zoneId, zoneName, $container) {
            ajaxCall('/api/hclouddns/hetzner/listRecords', {token: currentToken, zone_id: zoneId}, function(data, status) {
                if (data && data.status === 'ok' && data.records) {
                    var records = data.records.filter(function(r) {
                        return r.type === 'A' || r.type === 'AAAA';
                    });

                    if (records.length === 0) {
                        $container.html('<div class="alert alert-info">{{ lang._("No A/AAAA records found in this zone.") }}</div>');
                        return;
                    }

                    var tableHtml = '<table class="table table-condensed table-hover record-table">' +
                        '<thead><tr>' +
                        '<th class="record-checkbox"><input type="checkbox" class="select-all-zone" data-zone-id="' + zoneId + '"></th>' +
                        '<th>{{ lang._("Name") }}</th>' +
                        '<th>{{ lang._("Type") }}</th>' +
                        '<th>{{ lang._("Value") }}</th>' +
                        '<th>{{ lang._("TTL") }}</th>' +
                        '</tr></thead><tbody>';

                    $.each(records, function(i, record) {
                        var recordData = JSON.stringify({
                            zoneId: zoneId,
                            zoneName: zoneName,
                            recordId: record.id,
                            recordName: record.name,
                            recordType: record.type,
                            value: record.value,
                            ttl: record.ttl
                        }).replace(/"/g, '&quot;');

                        tableHtml += '<tr>' +
                            '<td class="record-checkbox"><input type="checkbox" class="record-select" data-record="' + recordData + '"></td>' +
                            '<td><code>' + record.name + '</code></td>' +
                            '<td><span class="label label-' + (record.type === 'A' ? 'primary' : 'info') + '">' + record.type + '</span></td>' +
                            '<td>' + record.value + '</td>' +
                            '<td>' + record.ttl + 's</td>' +
                            '</tr>';
                    });

                    tableHtml += '</tbody></table>';
                    $container.html(tableHtml);
                } else {
                    $container.html('<div class="alert alert-danger">{{ lang._("Failed to load records.") }}</div>');
                }
            });
        }

        // Select all in zone
        $(document).on('change', '.select-all-zone', function() {
            var zoneId = $(this).data('zone-id');
            var isChecked = $(this).is(':checked');
            $(this).closest('table').find('.record-select').prop('checked', isChecked);
            updateSelectedCount();
        });

        // Individual record selection
        $(document).on('change', '.record-select', function() {
            updateSelectedCount();
        });

        function updateSelectedCount() {
            selectedRecords = [];
            $('.record-select:checked').each(function() {
                var recordData = $(this).data('record');
                if (typeof recordData === 'string') {
                    recordData = JSON.parse(recordData.replace(/&quot;/g, '"'));
                }
                selectedRecords.push(recordData);
            });

            if (selectedRecords.length > 0) {
                $('#selectedInfo').html('<strong>' + selectedRecords.length + '</strong> {{ lang._("record(s) selected") }}');
                $('#addSelectedBtn').prop('disabled', false);
            } else {
                $('#selectedInfo').html('{{ lang._("No records selected") }}');
                $('#addSelectedBtn').prop('disabled', true);
            }
        }

        // Load gateways for selection
        function loadGateways() {
            ajaxCall('/api/hclouddns/gateways/searchItem', {}, function(data, status) {
                if (data && data.rows) {
                    var $primary = $('#primaryGatewaySelect');
                    var $failover = $('#failoverGatewaySelect');

                    $primary.empty().append('<option value="">{{ lang._("-- Select Gateway --") }}</option>');
                    $failover.empty().append('<option value="">{{ lang._("None (no failover)") }}</option>');

                    $.each(data.rows, function(i, gw) {
                        if (gw.enabled === '1') {
                            $primary.append('<option value="' + gw.uuid + '">' + gw.name + ' (' + gw.interface + ')</option>');
                            $failover.append('<option value="' + gw.uuid + '">' + gw.name + ' (' + gw.interface + ')</option>');
                        }
                    });

                    $primary.selectpicker('refresh');
                    $failover.selectpicker('refresh');
                }
            });
        }

        loadGateways();

        // Add selected records
        $('#addSelectedBtn').click(function() {
            var primaryGateway = $('#primaryGatewaySelect').val();
            var failoverGateway = $('#failoverGatewaySelect').val();
            var ttl = parseInt($('#ttlInput').val()) || 300;

            if (!primaryGateway) {
                BootstrapDialog.alert({
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('Please select a primary gateway.') }}",
                    type: BootstrapDialog.TYPE_WARNING
                });
                return;
            }

            if (selectedRecords.length === 0) {
                BootstrapDialog.alert({
                    title: "{{ lang._('Error') }}",
                    message: "{{ lang._('Please select at least one record.') }}",
                    type: BootstrapDialog.TYPE_WARNING
                });
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Adding...") }}');

            ajaxCall('/api/hclouddns/entries/batchAdd', {
                entries: selectedRecords,
                primaryGateway: primaryGateway,
                failoverGateway: failoverGateway,
                ttl: ttl
            }, function(data, status) {
                $btn.prop('disabled', false).html('<i class="fa fa-plus"></i> {{ lang._("Add Selected Records") }}');

                if (data && data.status === 'ok') {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Success') }}",
                        message: data.added + ' {{ lang._("record(s) added successfully.") }}',
                        type: BootstrapDialog.TYPE_SUCCESS
                    });

                    // Clear selections
                    $('.record-select').prop('checked', false);
                    $('.select-all-zone').prop('checked', false);
                    updateSelectedCount();
                } else {
                    BootstrapDialog.alert({
                        title: "{{ lang._('Error') }}",
                        message: data && data.message ? data.message : "{{ lang._('Failed to add records.') }}",
                        type: BootstrapDialog.TYPE_DANGER
                    });
                }
            });
        });
    });
</script>

<div class="tab-content content-box">
    <div id="zones" class="tab-pane fade in active">
        <div class="content-box-main">
            <div class="col-md-12">
                <h2>{{ lang._('Zone Selection') }}</h2>
                <p class="text-muted">{{ lang._('Select DNS records from your Hetzner zones to manage with dynamic DNS.') }}</p>
            </div>

            <!-- API Token Section -->
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ lang._('Hetzner DNS API Token') }}</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="apiTokenInput" placeholder="{{ lang._('Enter your Hetzner DNS API token') }}">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="saveTokenBtn"><i class="fa fa-save"></i></button>
                            <button type="button" class="btn btn-primary" id="loadZonesBtn"><i class="fa fa-cloud-download"></i> {{ lang._('Load Zones') }}</button>
                        </span>
                    </div>
                    <span id="apiTokenStatus" class="help-block"></span>
                </div>
            </div>

            <!-- Gateway Selection -->
            <div class="col-md-12">
                <div class="gateway-select-row">
                    <div class="row">
                        <div class="col-md-4">
                            <label>{{ lang._('Primary Gateway') }}</label>
                            <select id="primaryGatewaySelect" class="selectpicker" data-live-search="true" data-width="100%">
                                <option value="">{{ lang._('-- Select Gateway --') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>{{ lang._('Failover Gateway') }}</label>
                            <select id="failoverGatewaySelect" class="selectpicker" data-live-search="true" data-width="100%">
                                <option value="">{{ lang._('None (no failover)') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>{{ lang._('TTL') }}</label>
                            <input type="number" class="form-control" id="ttlInput" value="300" min="60" max="86400">
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-success btn-block" id="addSelectedBtn" disabled>
                                <i class="fa fa-plus"></i> {{ lang._('Add Selected') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected Records Info -->
            <div class="col-md-12">
                <div class="selected-records-info">
                    <span id="selectedInfo">{{ lang._('No records selected') }}</span>
                </div>
            </div>

            <!-- Zone Accordion -->
            <div class="col-md-12">
                <div id="zoneAccordion" class="zone-accordion panel-group">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> {{ lang._('Enter your API token and click "Load Zones" to see available zones.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
