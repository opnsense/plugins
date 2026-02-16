{#
    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
    All rights reserved.

    Hetzner Cloud DNS - Change History
#}

<style>
    .content-box-header { padding: 15px 30px; }
    .content-box-main { padding: 20px 30px; }
    .history-stats { display: flex; gap: 20px; margin-bottom: 20px; }
    .history-stat { background: #f8f9fa; padding: 15px 20px; border-radius: 8px; text-align: center; min-width: 120px; }
    .history-stat .number { font-size: 24px; font-weight: 600; }
    .history-stat .label { font-size: 12px; color: #666; margin-top: 5px; }
    .history-stat.creates .number { color: #28a745; }
    .history-stat.updates .number { color: #17a2b8; }
    .history-stat.deletes .number { color: #dc3545; }
    .record-code { font-family: monospace; background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    .value-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .value-cell:hover { white-space: normal; word-break: break-all; }
</style>

<div class="content-box">
    <div class="content-box-header">
        <h3><i class="fa fa-history"></i> {{ lang._('DNS Change History') }}</h3>
    </div>
    <div class="content-box-main">
        <p class="text-muted">
            {{ lang._('Complete log of all DNS changes - both automatic updates (DynDNS, failover) and manual changes (DNS Management). You can revert changes to restore previous values.') }}
        </p>

        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            {{ lang._('History retention is configured in Settings (current:') }} <span id="retentionDays">...</span> {{ lang._('days).') }}
        </div>

        <!-- Statistics -->
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
        </div>

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
                    <th style="width:80px;">{{ lang._('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="9" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> {{ lang._('Loading...') }}</td></tr>
            </tbody>
        </table>

        <hr/>
        <button class="btn btn-default" id="refreshHistoryBtn"><i class="fa fa-refresh"></i> {{ lang._('Refresh') }}</button>
        <button class="btn btn-warning" id="cleanupHistoryBtn"><i class="fa fa-trash"></i> {{ lang._('Cleanup Old Entries') }}</button>
        <button class="btn btn-danger" id="clearAllHistoryBtn"><i class="fa fa-times"></i> {{ lang._('Clear All History') }}</button>
    </div>
</div>

<script>
$(document).ready(function() {
    updateServiceControlUI('hclouddns');

    function loadHistory() {
        var $tbody = $('#historyTable tbody');
        $tbody.html('<tr><td colspan="9" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

        ajaxCall('/api/hclouddns/history/searchItem', {}, function(data) {
            $tbody.empty();

            // Calculate statistics
            var stats = {total: 0, creates: 0, updates: 0, deletes: 0};

            if (!data || !data.rows || data.rows.length === 0) {
                $tbody.html('<tr><td colspan="9" class="text-center text-muted">{{ lang._("No history entries found. Changes will appear here when DNS records are modified.") }}</td></tr>');
                updateStats(stats);
                return;
            }

            $.each(data.rows, function(i, row) {
                stats.total++;
                if (row.action === 'create') stats.creates++;
                else if (row.action === 'update') stats.updates++;
                else if (row.action === 'delete') stats.deletes++;

                var actionClass = {create: 'success', update: 'info', delete: 'danger'}[row.action] || 'default';
                var actionIcon = {create: 'plus', update: 'pencil', delete: 'trash'}[row.action] || 'circle';
                var revertedClass = row.reverted === '1' ? 'text-muted' : '';
                var revertedBadge = row.reverted === '1' ? '<span class="label label-default">Reverted</span>' : '<span class="label label-primary">Active</span>';

                var revertBtn = '';
                if (row.reverted !== '1') {
                    revertBtn = '<button class="btn btn-xs btn-warning revert-btn" data-uuid="' + row.uuid + '" title="{{ lang._("Revert this change") }}"><i class="fa fa-undo"></i></button>';
                } else {
                    revertBtn = '<span class="text-muted">-</span>';
                }

                var recordFqdn = row.recordName + '.' + row.zoneName;
                var oldVal = row.oldValue || '-';
                var newVal = row.newValue || '-';

                $tbody.append(
                    '<tr class="' + revertedClass + '">' +
                        '<td><small>' + row.timestampFormatted + '</small></td>' +
                        '<td><span class="label label-' + actionClass + '"><i class="fa fa-' + actionIcon + '"></i> ' + row.action + '</span></td>' +
                        '<td><span class="record-code">' + recordFqdn + '</span></td>' +
                        '<td><span class="label label-default">' + row.recordType + '</span></td>' +
                        '<td class="value-cell" title="' + escapeHtml(oldVal) + '"><small>' + escapeHtml(oldVal) + '</small></td>' +
                        '<td class="value-cell" title="' + escapeHtml(newVal) + '"><small>' + escapeHtml(newVal) + '</small></td>' +
                        '<td><small>' + (row.accountName || '-') + '</small></td>' +
                        '<td>' + revertedBadge + '</td>' +
                        '<td>' + revertBtn + '</td>' +
                    '</tr>'
                );
            });

            updateStats(stats);
        });
    }

    function updateStats(stats) {
        $('#statTotal').text(stats.total);
        $('#statCreates').text(stats.creates);
        $('#statUpdates').text(stats.updates);
        $('#statDeletes').text(stats.deletes);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    // Load history on page load
    loadHistory();

    // Load retention days from settings
    ajaxCall('/api/hclouddns/settings/get', {}, function(data) {
        if (data && data.hclouddns && data.hclouddns.general) {
            $('#retentionDays').text(data.hclouddns.general.historyRetentionDays || '7');
        }
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
                            loadHistory();
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

    // Refresh history button
    $('#refreshHistoryBtn').click(function() {
        loadHistory();
    });

    // Cleanup old history entries
    $('#cleanupHistoryBtn').click(function() {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> {{ lang._("Cleaning...") }}');

        ajaxCall('/api/hclouddns/history/cleanup', {_: ''}, function(data) {
            $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> {{ lang._("Cleanup Old Entries") }}');

            if (data && data.status === 'ok') {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_SUCCESS,
                    title: '{{ lang._("Cleanup Complete") }}',
                    message: data.message || (data.deleted + ' {{ lang._("old entries removed.") }}')
                });
                loadHistory();
            } else {
                BootstrapDialog.alert({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._("Cleanup Failed") }}',
                    message: data.message || '{{ lang._("Failed to cleanup history.") }}'
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
                    // Call cleanup with 0 days to clear all
                    ajaxCall('/api/hclouddns/history/clearAll', {_: ''}, function(data) {
                        if (data && data.status === 'ok') {
                            BootstrapDialog.alert({type: BootstrapDialog.TYPE_SUCCESS, message: data.message});
                            loadHistory();
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
