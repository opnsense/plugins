{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Hetzner Cloud DNS - Audit Dashboard & Change History
#}

<style>
    .content-box-header { padding: 15px 30px; }
    .content-box-main { padding: 20px 30px; }
    /* Dashboard Filters */
    .dashboard-filters { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; }
    .dashboard-filters .form-group { margin-bottom: 0; }
    .dashboard-filters label { font-size: 12px; color: #666; display: block; margin-bottom: 3px; }
    /* Stats Tiles */
    .history-stats { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
    .history-stat { background: #f8f9fa; padding: 15px 20px; border-radius: 8px; text-align: center; min-width: 100px; flex: 1; }
    .history-stat .number { font-size: 28px; font-weight: 700; }
    .history-stat .label { font-size: 11px; color: #666; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.5px; }
    .history-stat.creates .number { color: #28a745; }
    .history-stat.updates .number { color: #17a2b8; }
    .history-stat.deletes .number { color: #dc3545; }
    .history-stat.reverted .number { color: #6c757d; }
    .history-stat.avg .number { color: #6610f2; }
    /* Activity Timeline */
    .activity-timeline { margin-bottom: 25px; }
    .activity-timeline h5 { margin-bottom: 10px; font-size: 14px; font-weight: 600; }
    .timeline-chart { display: flex; align-items: stretch; gap: 2px; height: 120px; padding: 10px 0; border-bottom: 1px solid #ddd; }
    .timeline-bar { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; min-width: 8px; max-width: 30px; position: relative; cursor: pointer; }
    .timeline-bar:hover .timeline-tooltip { display: block; }
    .timeline-bar-segment { width: 100%; transition: height 0.3s ease; }
    .timeline-bar-segment.create { background: #28a745; }
    .timeline-bar-segment.update { background: #17a2b8; }
    .timeline-bar-segment.delete { background: #dc3545; }
    .timeline-tooltip { display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 11px; white-space: nowrap; z-index: 10; margin-bottom: 5px; }
    .timeline-labels { display: flex; gap: 2px; justify-content: space-between; padding-top: 5px; }
    .timeline-labels span { font-size: 10px; color: #999; flex: 1; text-align: center; }
    .timeline-legend { display: flex; gap: 15px; margin-top: 8px; font-size: 11px; color: #666; }
    .timeline-legend-item { display: flex; align-items: center; gap: 4px; }
    .timeline-legend-dot { width: 10px; height: 10px; border-radius: 2px; }
    /* Action Breakdown */
    .dashboard-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
    .dashboard-panel { flex: 1; min-width: 280px; background: #f8f9fa; border-radius: 8px; padding: 15px 20px; }
    .dashboard-panel h5 { margin-top: 0; margin-bottom: 15px; font-size: 14px; font-weight: 600; }
    .breakdown-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .breakdown-label { min-width: 70px; font-size: 12px; color: #555; }
    .breakdown-track { flex: 1; background: #e9ecef; height: 18px; border-radius: 3px; overflow: hidden; }
    .breakdown-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; display: flex; align-items: center; padding: 0 6px; font-size: 10px; color: #fff; font-weight: 600; }
    .breakdown-fill.create { background: #28a745; }
    .breakdown-fill.update { background: #17a2b8; }
    .breakdown-fill.delete { background: #dc3545; }
    .breakdown-value { min-width: 40px; text-align: right; font-size: 12px; font-weight: 600; }
    /* Top Zones */
    .top-zone-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .top-zone-name { min-width: 140px; font-size: 12px; font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .top-zone-track { flex: 1; background: #e9ecef; height: 14px; border-radius: 3px; overflow: hidden; }
    .top-zone-fill { height: 100%; background: #337ab7; border-radius: 3px; transition: width 0.5s ease; }
    .top-zone-count { min-width: 30px; text-align: right; font-size: 12px; font-weight: 600; color: #555; }
    /* History Table */
    .record-code { font-family: monospace; background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    .value-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .value-cell:hover { white-space: normal; word-break: break-all; }
    /* Empty State */
    .empty-state { text-align: center; padding: 50px 20px; color: #999; }
    .empty-state i.fa { font-size: 48px; margin-bottom: 15px; display: block; color: #ccc; }
    .empty-state p { font-size: 14px; max-width: 500px; margin: 0 auto 10px; }
    /* Button spacing */
    .history-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .history-actions .danger-zone { margin-left: auto; display: flex; gap: 10px; }
    .history-table-info { font-size: 12px; color: #999; margin-bottom: 10px; }
</style>

<div class="content-box">
    <div class="content-box-header">
        <h3><i class="fa fa-bar-chart"></i> {{ lang._('DNS Audit Dashboard') }}</h3>
    </div>
    <div class="content-box-main">
        <p class="text-muted">
            {{ lang._('Complete audit trail and analytics of all DNS changes - automatic updates (DynDNS, failover) and manual changes.') }}
        </p>

        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            {{ lang._('History retention is configured in Settings (current:') }} <span id="retentionDays">...</span> {{ lang._('days).') }}
        </div>

        <!-- Dashboard Filters -->
        <div class="dashboard-filters">
            <div class="form-group">
                <label>{{ lang._('Time Range') }}</label>
                <select class="form-control input-sm" id="dashboardRange" style="width: 140px;">
                    <option value="1">{{ lang._('Today') }}</option>
                    <option value="7" selected>{{ lang._('Last 7 Days') }}</option>
                    <option value="30">{{ lang._('Last 30 Days') }}</option>
                    <option value="90">{{ lang._('Last 90 Days') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label>{{ lang._('Account') }}</label>
                <select class="form-control input-sm" id="dashboardAccount" style="width: 180px;">
                    <option value="">{{ lang._('All Accounts') }}</option>
                </select>
            </div>
            <div class="form-group">
                <button class="btn btn-sm btn-default" id="refreshDashboardBtn"><i class="fa fa-refresh"></i> {{ lang._('Refresh') }}</button>
            </div>
        </div>

        <!-- Statistics Tiles -->
        <div class="history-stats" id="historyStats">
            <div class="history-stat">
                <div class="number" id="statTotal">-</div>
                <div class="label">{{ lang._('Total') }}</div>
            </div>
            <div class="history-stat creates">
                <div class="number" id="statCreates">-</div>
                <div class="label">{{ lang._('Creates') }}</div>
            </div>
            <div class="history-stat updates">
                <div class="number" id="statUpdates">-</div>
                <div class="label">{{ lang._('Updates') }}</div>
            </div>
            <div class="history-stat deletes">
                <div class="number" id="statDeletes">-</div>
                <div class="label">{{ lang._('Deletes') }}</div>
            </div>
            <div class="history-stat reverted">
                <div class="number" id="statReverted">-</div>
                <div class="label">{{ lang._('Reverted') }}</div>
            </div>
            <div class="history-stat avg">
                <div class="number" id="statAvg">-</div>
                <div class="label">{{ lang._('Avg/Day') }}</div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="activity-timeline">
            <h5><i class="fa fa-line-chart"></i> {{ lang._('Activity Timeline') }}</h5>
            <div class="timeline-chart" id="timelineChart">
                <div class="text-center text-muted" style="width:100%;padding:40px;">
                    <i class="fa fa-spinner fa-spin"></i>
                </div>
            </div>
            <div class="timeline-labels" id="timelineLabels"></div>
            <div class="timeline-legend">
                <div class="timeline-legend-item"><div class="timeline-legend-dot" style="background:#28a745;"></div> {{ lang._('Create') }}</div>
                <div class="timeline-legend-item"><div class="timeline-legend-dot" style="background:#17a2b8;"></div> {{ lang._('Update') }}</div>
                <div class="timeline-legend-item"><div class="timeline-legend-dot" style="background:#dc3545;"></div> {{ lang._('Delete') }}</div>
            </div>
        </div>

        <!-- Breakdown + Top Zones Row -->
        <div class="dashboard-row">
            <div class="dashboard-panel">
                <h5><i class="fa fa-pie-chart"></i> {{ lang._('Action Breakdown') }}</h5>
                <div id="actionBreakdown">
                    <div class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i></div>
                </div>
            </div>
            <div class="dashboard-panel">
                <h5><i class="fa fa-trophy"></i> {{ lang._('Top Zones') }}</h5>
                <div id="topZones">
                    <div class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>

        <hr/>
        <h4><i class="fa fa-history"></i> {{ lang._('Change History') }}</h4>
        <div class="history-table-info" id="historyTableInfo"></div>

        <!-- History Table -->
        <table class="table table-condensed table-hover table-striped" id="historyTable">
            <thead>
                <tr>
                    <th style="width:150px;">{{ lang._('Time') }}</th>
                    <th style="width:80px;">{{ lang._('Action') }}</th>
                    <th>{{ lang._('Record') }}</th>
                    <th style="width:60px;">{{ lang._('Type') }}</th>
                    <th>{{ lang._('Old Value') }}</th>
                    <th>{{ lang._('New Value') }}</th>
                    <th>{{ lang._('Account') }}</th>
                    <th style="width:80px;">{{ lang._('Status') }}</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="9" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> {{ lang._('Loading...') }}</td></tr>
            </tbody>
        </table>

        <hr/>
        <div class="history-actions">
            <button class="btn btn-default" id="refreshHistoryBtn"><i class="fa fa-refresh"></i> {{ lang._('Refresh') }}</button>
            <div class="danger-zone">
                <button class="btn btn-warning" id="cleanupHistoryBtn"><i class="fa fa-trash"></i> {{ lang._('Cleanup Old Entries') }}</button>
                <button class="btn btn-danger" id="clearAllHistoryBtn"><i class="fa fa-times"></i> {{ lang._('Clear All') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    updateServiceControlUI('hclouddns');

    var allHistoryRows = []; // cache for client-side filtering

    // Load retention days from settings
    ajaxCall('/api/hclouddns/settings/get', {}, function(data) {
        if (data && data.hclouddns && data.hclouddns.general) {
            $('#retentionDays').text(data.hclouddns.general.historyRetentionDays || '7');
        }
    });

    function loadDashboard() {
        var days = parseInt($('#dashboardRange').val()) || 7;
        loadStats(days);
        loadHistory();
    }

    function getAccountFilter() {
        return $('#dashboardAccount').val() || '';
    }

    function loadStats(days) {
        ajaxCall('/api/hclouddns/history/stats', {days: days}, function(data) {
            if (!data || data.status !== 'ok') {
                $('#statTotal, #statCreates, #statUpdates, #statDeletes, #statReverted, #statAvg').text('-');
                return;
            }

            // Populate account filter (preserve selection)
            var currentAccount = getAccountFilter();
            var $accountSelect = $('#dashboardAccount');
            var accounts = data.byAccount || {};
            $accountSelect.find('option:not(:first)').remove();
            $.each(accounts, function(name) {
                $accountSelect.append('<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>');
            });
            if (currentAccount) {
                $accountSelect.val(currentAccount);
            }

            // Update tiles
            $('#statTotal').text(data.total || 0);
            $('#statCreates').text(data.creates || 0);
            $('#statUpdates').text(data.updates || 0);
            $('#statDeletes').text(data.deletes || 0);
            $('#statReverted').text(data.reverted || 0);
            $('#statAvg').text(data.avgPerDay || 0);

            // Render activity timeline
            renderTimeline(data.byDate || {}, days);

            // Render action breakdown
            renderBreakdown(data.total || 0, data.creates || 0, data.updates || 0, data.deletes || 0);

            // Render top zones
            renderTopZones(data.byZone || {});
        });
    }

    function renderTimeline(byDate, days) {
        var $chart = $('#timelineChart').empty();
        var $labels = $('#timelineLabels').empty();

        // Generate date range
        var dates = [];
        var now = new Date();
        for (var i = days - 1; i >= 0; i--) {
            var d = new Date(now);
            d.setDate(d.getDate() - i);
            dates.push(d.toISOString().split('T')[0]);
        }

        // Find max value for scaling
        var maxVal = 0;
        $.each(dates, function(i, date) {
            var entry = byDate[date] || {create: 0, update: 0, delete: 0};
            var total = (entry.create || 0) + (entry.update || 0) + (entry.delete || 0);
            if (total > maxVal) maxVal = total;
        });
        if (maxVal === 0) maxVal = 1;

        // Render bars
        $.each(dates, function(i, date) {
            var entry = byDate[date] || {create: 0, update: 0, delete: 0};
            var c = entry.create || 0;
            var u = entry.update || 0;
            var d = entry.delete || 0;
            var total = c + u + d;

            var cH = total > 0 ? Math.max(2, (c / maxVal) * 100) : 0;
            var uH = total > 0 ? Math.max(2, (u / maxVal) * 100) : 0;
            var dH = total > 0 ? Math.max(2, (d / maxVal) * 100) : 0;
            if (c === 0) cH = 0;
            if (u === 0) uH = 0;
            if (d === 0) dH = 0;

            var shortDate = date.substring(5);
            var tooltip = date + ': ' + c + ' create, ' + u + ' update, ' + d + ' delete';

            $chart.append(
                '<div class="timeline-bar" title="' + tooltip + '">' +
                    '<div class="timeline-tooltip">' + tooltip + '</div>' +
                    '<div class="timeline-bar-segment delete" style="height:' + dH + '%;"></div>' +
                    '<div class="timeline-bar-segment update" style="height:' + uH + '%;"></div>' +
                    '<div class="timeline-bar-segment create" style="height:' + cH + '%;"></div>' +
                '</div>'
            );

            var showLabel = false;
            if (days <= 7) showLabel = true;
            else if (days <= 30) showLabel = (i % 3 === 0 || i === dates.length - 1);
            else showLabel = (i % 7 === 0 || i === dates.length - 1);

            if (showLabel) {
                $labels.append('<span>' + shortDate + '</span>');
            }
        });
    }

    function renderBreakdown(total, creates, updates, deletes) {
        if (total === 0) {
            $('#actionBreakdown').html('<div class="text-center text-muted">{{ lang._("No data") }}</div>');
            return;
        }

        var html = '';
        var items = [
            {label: '{{ lang._("Create") }}', value: creates, cls: 'create'},
            {label: '{{ lang._("Update") }}', value: updates, cls: 'update'},
            {label: '{{ lang._("Delete") }}', value: deletes, cls: 'delete'}
        ];

        $.each(items, function(i, item) {
            var pct = Math.round((item.value / total) * 100);
            html += '<div class="breakdown-bar">' +
                '<span class="breakdown-label">' + item.label + '</span>' +
                '<div class="breakdown-track"><div class="breakdown-fill ' + item.cls + '" style="width:' + pct + '%;">' + (pct > 10 ? pct + '%' : '') + '</div></div>' +
                '<span class="breakdown-value">' + item.value + '</span>' +
            '</div>';
        });

        $('#actionBreakdown').html(html);
    }

    function renderTopZones(byZone) {
        var zones = [];
        $.each(byZone, function(name, count) {
            zones.push({name: name, count: count});
        });
        zones.sort(function(a, b) { return b.count - a.count; });
        zones = zones.slice(0, 5);

        if (zones.length === 0) {
            $('#topZones').html('<div class="text-center text-muted">{{ lang._("No data") }}</div>');
            return;
        }

        var maxCount = zones[0].count || 1;
        var html = '';
        $.each(zones, function(i, z) {
            var pct = Math.round((z.count / maxCount) * 100);
            html += '<div class="top-zone-bar">' +
                '<span class="top-zone-name" title="' + z.name + '">' + z.name + '</span>' +
                '<div class="top-zone-track"><div class="top-zone-fill" style="width:' + pct + '%;"></div></div>' +
                '<span class="top-zone-count">' + z.count + '</span>' +
            '</div>';
        });

        $('#topZones').html(html);
    }

    function loadHistory() {
        var $tbody = $('#historyTable tbody');
        $tbody.html('<tr><td colspan="9" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> {{ lang._("Loading...") }}</td></tr>');

        ajaxCall('/api/hclouddns/history/searchItem', {}, function(data) {
            if (!data || !data.rows || data.rows.length === 0) {
                allHistoryRows = [];
                renderHistoryTable();
                return;
            }
            allHistoryRows = data.rows;
            renderHistoryTable();
        });
    }

    function renderHistoryTable() {
        var $tbody = $('#historyTable tbody').empty();
        var days = parseInt($('#dashboardRange').val()) || 7;
        var accountFilter = getAccountFilter();
        var cutoff = Math.floor(Date.now() / 1000) - (days * 86400);

        // Filter rows by time range and account
        var filtered = [];
        $.each(allHistoryRows, function(i, row) {
            if (row.timestamp < cutoff) return;
            if (accountFilter && row.accountName !== accountFilter) return;
            filtered.push(row);
        });

        // Info text
        var infoText = filtered.length + ' {{ lang._("entries") }}';
        if (filtered.length !== allHistoryRows.length) {
            infoText += ' ({{ lang._("filtered from") }} ' + allHistoryRows.length + ' {{ lang._("total") }})';
        }
        $('#historyTableInfo').text(infoText);

        if (filtered.length === 0) {
            if (allHistoryRows.length === 0) {
                // True empty state
                $tbody.html(
                    '<tr><td colspan="9">' +
                        '<div class="empty-state">' +
                            '<i class="fa fa-history"></i>' +
                            '<p>{{ lang._("No changes recorded yet.") }}</p>' +
                            '<p class="text-muted" style="font-size:12px;">{{ lang._("Changes appear here automatically when DNS records are modified — through DynDNS updates, failover events, or manual edits on the DNS management page.") }}</p>' +
                        '</div>' +
                    '</td></tr>'
                );
            } else {
                // Filtered empty
                $tbody.html('<tr><td colspan="9" class="text-center text-muted" style="padding:30px;">{{ lang._("No entries match the selected filters. Try a wider time range or different account.") }}</td></tr>');
            }
            return;
        }

        $.each(filtered, function(i, row) {
            var actionClass = {create: 'success', update: 'info', delete: 'danger'}[row.action] || 'default';
            var actionIcon = {create: 'plus', update: 'pencil', delete: 'trash'}[row.action] || 'circle';
            var revertedClass = row.reverted === '1' ? 'text-muted' : '';
            var revertedBadge = row.reverted === '1' ? '<span class="label label-default">{{ lang._("Reverted") }}</span>' : '<span class="label label-primary">{{ lang._("Active") }}</span>';

            var revertBtn = '';
            if (row.reverted !== '1') {
                revertBtn = '<button class="btn btn-xs btn-warning revert-btn" data-uuid="' + row.uuid + '" title="{{ lang._("Revert this change") }}"><i class="fa fa-undo"></i></button>';
            }

            var recordFqdn = row.recordName + '.' + row.zoneName;
            var oldVal = row.oldValue || '-';
            var newVal = row.newValue || '-';

            $tbody.append(
                '<tr class="' + revertedClass + '">' +
                    '<td><small>' + row.timestampFormatted + '</small></td>' +
                    '<td><span class="label label-' + actionClass + '"><i class="fa fa-' + actionIcon + '"></i> ' + row.action + '</span></td>' +
                    '<td><span class="record-code">' + escapeHtml(recordFqdn) + '</span></td>' +
                    '<td><span class="label label-default">' + row.recordType + '</span></td>' +
                    '<td class="value-cell" title="' + escapeHtml(oldVal) + '"><small>' + escapeHtml(oldVal) + '</small></td>' +
                    '<td class="value-cell" title="' + escapeHtml(newVal) + '"><small>' + escapeHtml(newVal) + '</small></td>' +
                    '<td><small>' + escapeHtml(row.accountName || '-') + '</small></td>' +
                    '<td>' + revertedBadge + '</td>' +
                    '<td>' + revertBtn + '</td>' +
                '</tr>'
            );
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    // Load dashboard on page load
    loadDashboard();

    // Filters trigger re-render
    $('#dashboardRange').on('change', function() {
        var days = parseInt($(this).val()) || 7;
        loadStats(days);
        renderHistoryTable(); // client-side re-filter, no extra API call
    });

    $('#dashboardAccount').on('change', function() {
        renderHistoryTable(); // client-side re-filter
    });

    // Refresh button
    $('#refreshDashboardBtn, #refreshHistoryBtn').on('click', function() {
        loadDashboard();
    });

    // Revert history entry
    $(document).on('click', '.revert-btn', function() {
        var $btn = $(this);
        var uuid = $btn.data('uuid');

        BootstrapDialog.confirm({
            title: '{{ lang._("Revert Change") }}',
            message: '{{ lang._("Are you sure you want to revert this DNS change? This will restore the previous value at Hetzner.") }}',
            type: BootstrapDialog.TYPE_WARNING,
            btnOKLabel: '{{ lang._("Revert") }}',
            btnOKClass: 'btn-warning',
            callback: function(result) {
                if (result) {
                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
                    ajaxCall('/api/hclouddns/history/revert/' + uuid, {_: ''}, function(data) {
                        if (data && data.status === 'ok') {
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_SUCCESS,
                                title: '{{ lang._("Change Reverted") }}',
                                message: data.message || '{{ lang._("The DNS change has been reverted successfully.") }}'
                            });
                            loadDashboard();
                        } else {
                            $btn.prop('disabled', false).html('<i class="fa fa-undo"></i>');
                            BootstrapDialog.alert({
                                type: BootstrapDialog.TYPE_DANGER,
                                title: '{{ lang._("Revert Failed") }}',
                                message: data.message || '{{ lang._("Failed to revert the change.") }}'
                            });
                        }
                    });
                }
            }
        });
    });

    // Cleanup old history entries
    $('#cleanupHistoryBtn').click(function() {
        BootstrapDialog.confirm({
            title: '{{ lang._("Cleanup History") }}',
            message: '{{ lang._("Remove entries older than the configured retention period?") }}',
            type: BootstrapDialog.TYPE_WARNING,
            btnOKLabel: '{{ lang._("Cleanup") }}',
            btnOKClass: 'btn-warning',
            callback: function(result) {
                if (!result) return;
                var $btn = $('#cleanupHistoryBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Cleaning...") }}');

                ajaxCall('/api/hclouddns/history/cleanup', {_: ''}, function(data) {
                    $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> {{ lang._("Cleanup Old Entries") }}');

                    if (data && data.status === 'ok') {
                        BootstrapDialog.alert({
                            type: BootstrapDialog.TYPE_SUCCESS,
                            title: '{{ lang._("Cleanup Complete") }}',
                            message: data.message || (data.deleted + ' {{ lang._("old entries removed.") }}')
                        });
                        loadDashboard();
                    } else {
                        BootstrapDialog.alert({
                            type: BootstrapDialog.TYPE_DANGER,
                            title: '{{ lang._("Cleanup Failed") }}',
                            message: data.message || '{{ lang._("Failed to cleanup history.") }}'
                        });
                    }
                });
            }
        });
    });

    // Clear all history
    $('#clearAllHistoryBtn').click(function() {
        BootstrapDialog.confirm({
            title: '{{ lang._("Clear All History") }}',
            message: '{{ lang._("Are you sure you want to delete ALL history entries? This cannot be undone.") }}',
            type: BootstrapDialog.TYPE_DANGER,
            btnOKLabel: '{{ lang._("Clear All") }}',
            btnOKClass: 'btn-danger',
            callback: function(result) {
                if (result) {
                    ajaxCall('/api/hclouddns/history/clearAll', {_: ''}, function(data) {
                        if (data && data.status === 'ok') {
                            BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: data.message});
                            loadDashboard();
                        } else {
                            BootstrapDialog.alert({type: BootstrapDialog.TYPE_DANGER, message: data.message || '{{ lang._("Failed to clear history.") }}'});
                        }
                    });
                }
            }
        });
    });

});
</script>
